<?php

use Kirby\Data\Yaml;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;

// Immutable internal identifier of an order (UUID v4). It is also the order page's
// slug, so an order is found directly by its id, and it is what external systems
// (Stripe metadata) refer to. Never derived from the number of existing orders.
function newOrderId(): string
{
    return Str::uuid();
}

function findOrder(string $orderId): ?Kirby\Cms\Page
{
    return $orderId !== '' ? page('orders')?->findPageOrDraft($orderId) : null;
}

// Human-facing order number, e.g. "KS-2026-000128". For display only: orders are
// identified by their orderId.
//
// Concurrency: the last number lives in a counter file that is read, incremented and
// written while holding an exclusive flock(), so two orders finalised at the same
// moment are serialised and can never receive the same number.
//
// If the counter file is missing (first run, lost during a deploy) it is seeded from
// the highest number among existing orders, so numbering continues without reusing
// a number. A counter that exists but can't be read stops the order instead of
// silently restarting the sequence.
function nextOrderNumber(): string
{
    $file = kirby()->root('site') . '/storage/order-counter';
    Dir::make(dirname($file));

    $isNew  = !is_file($file);
    $handle = fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new Exception('Order counter could not be opened or locked.');
    }

    try {
        $current = trim((string)stream_get_contents($handle));

        if ($current === '' && $isNew) {
            $last = highestOrderNumber();
        } elseif (ctype_digit($current)) {
            $last = (int)$current;
        } else {
            throw new Exception("Order counter is unreadable ({$file}).");
        }

        $next = $last + 1;

        rewind($handle);
        ftruncate($handle, 0);
        if (fwrite($handle, (string)$next) === false || !fflush($handle)) {
            throw new Exception('Order counter could not be written.');
        }
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    return sprintf('%s-%s-%06d', option('kstore.orderPrefix', 'KS'), date('Y'), $next);
}

// Highest sequence among existing orders: the trailing digits of their orderNumber,
// or of the title for orders created before orderNumber existed ("#0042 - ...").
function highestOrderNumber(): int
{
    $highest = 0;
    foreach (page('orders')?->childrenAndDrafts() ?? [] as $order) {
        $source = $order->orderNumber()->or($order->title())->value();
        if (preg_match('/(\d+)(?!.*\d)/', explode(' - ', $source)[0], $m)) {
            $highest = max($highest, (int)$m[1]);
        }
    }
    return $highest;
}

function isStripeEnabled(): bool
{
    $field = site()->stripeEnabled();
    $raw   = $field ? $field->value() : null;
    $value = is_bool($raw) ? $raw : strtolower(trim((string)$raw));

    if ($value === true) return true;
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function sendOrderEmails(array $buyerInfo, array $items, float $total, string $orderNumber): void
{
    $kirby = kirby();

    $fromAddress = (string)$kirby->option('email.transport.username');
    if ($fromAddress === '') {
        error_log('[checkout] Email transport username missing.');
        return;
    }

    $toUser = trim((string)($buyerInfo['email'] ?? ''));
    $toName = trim((string)($buyerInfo['name'] ?? ''));

    $data = [
        'orderNumber' => $orderNumber,
        'name'        => (string)($buyerInfo['name'] ?? ''),
        'surname'     => (string)($buyerInfo['surname'] ?? ''),
        'email'       => (string)($buyerInfo['email'] ?? ''),
        'address'     => (string)($buyerInfo['address'] ?? ''),
        'zipcode'     => (string)($buyerInfo['zipcode'] ?? ''),
        'city'        => (string)($buyerInfo['city'] ?? ''),
        'country'     => (string)($buyerInfo['country'] ?? ''),
        'message'     => (string)($buyerInfo['additionalInformations'] ?? ''),
        'items'       => $items,
        'total'       => $total,
    ];

    try {
        $kirby->email([
            'template' => 'checkout/checkout-admin',
            'from'     => $fromAddress,
            'replyTo'  => $toUser !== '' ? $toUser : null,
            'to'       => $fromAddress,
            'subject'  => "New order received ({$orderNumber})",
            'data'     => $data,
        ]);

        if ($toUser !== '') {
            $kirby->email([
                'template' => 'checkout/receipt',
                'from'     => $fromAddress,
                'to'       => [$toUser => $toName !== '' ? $toName : $toUser],
                'subject'  => "Order confirmation ({$orderNumber})",
                'data'     => $data,
            ]);
        } else {
            error_log('[checkout] Buyer email missing — user receipt not sent.');
        }
    } catch (Throwable $e) {
        error_log('[checkout] Email failed: ' . $e->getMessage());
    }
}

function finalizeOrder(array $cart, array $buyerInfo, $session): string
{
    $ordersPage = page('orders');
    if (!$ordersPage) {
        throw new Exception('Orders page not found.');
    }

    $itemsData  = [];
    $emailItems = [];
    $totalPrice = 0.0;

    foreach ($cart as $item) {
        $title = (string)($item['title'] ?? '');
        $color = (string)($item['color'] ?? '');
        $qty   = (int)($item['quantity'] ?? 0);
        $price = (float)($item['price'] ?? 0);

        if ($qty < 1) continue;

        $totalPrice += $price * $qty;

        $itemsData[] = [
            'title'    => $color !== '' ? "{$title} — {$color}" : $title,
            'quantity' => $qty,
            'price'    => $price,
        ];

        $emailItems[] = [
            'title'    => $title !== '' ? $title : 'Product',
            'color'    => $color,
            'quantity' => $qty,
            'price'    => $price,
        ];
    }

    if (empty($itemsData)) {
        throw new Exception('No valid items to create order.');
    }

    $capitalizedName    = ucwords(strtolower((string)($buyerInfo['name'] ?? '')));
    $capitalizedSurname = ucwords(strtolower((string)($buyerInfo['surname'] ?? '')));

    $orderId     = newOrderId();
    $orderNumber = nextOrderNumber();

    kirby()->impersonate('kirby');

    foreach ($cart as $item) {
        $uuid = $item['id'] ?? null;
        if (!$uuid) continue;

        $productPage = kirby()->page('page://' . $uuid);
        if (!$productPage || !$productPage->stock()->exists()) continue;

        $currentStock = (int)$productPage->stock()->int();
        $qty          = (int)($item['quantity'] ?? 0);
        if ($qty < 1) continue;

        $productPage->update(['stock' => max(0, $currentStock - $qty)]);
    }

    $ordersPage->createChild([
        'slug'     => $orderId,
        'template' => 'order',
        'isDraft'  => true,
        'content'  => [
            'title'                  => "{$orderNumber} - {$capitalizedName} {$capitalizedSurname}",
            'orderId'                => $orderId,
            'orderNumber'            => $orderNumber,
            'items'                  => Yaml::encode($itemsData),
            'name'                   => (string)($buyerInfo['name'] ?? ''),
            'surname'                => (string)($buyerInfo['surname'] ?? ''),
            'email'                  => (string)($buyerInfo['email'] ?? ''),
            'address'                => (string)($buyerInfo['address'] ?? ''),
            'zipcode'                => (string)($buyerInfo['zipcode'] ?? ''),
            'city'                   => (string)($buyerInfo['city'] ?? ''),
            'country'                => (string)($buyerInfo['country'] ?? ''),
            'additionalInformations' => (string)($buyerInfo['additionalInformations'] ?? ''),
        ],
    ]);

    sendOrderEmails($buyerInfo, $emailItems, $totalPrice, $orderNumber);

    $session->remove('cart');
    $session->remove('buyerInfo');
    $session->remove('checkout_token');
    $session->remove('stripe_session_id');
    $session->remove('checkout_lines');

    return $orderNumber;
}

// Called on the success page when coming back from Stripe. The order is only
// created once Stripe confirms this exact session was paid for the cart's total:
// reaching the success URL alone proves nothing.
function finalizeFromStripe(?string $stripeSessionId): array
{
    $session   = kirby()->session();
    $lines     = $session->get('checkout_lines', []);
    $buyerInfo = $session->get('buyerInfo', []);

    if (
        !$session->get('checkout_token') || empty($lines) || empty($buyerInfo) ||
        !$stripeSessionId || $stripeSessionId !== $session->get('stripe_session_id')
    ) {
        return ['status' => 'error', 'message' => 'Nothing to finalize.'];
    }

    try {
        \Stripe\Stripe::setApiKey(option('stripe.secretKey'));
        $stripeSession = \Stripe\Checkout\Session::retrieve($stripeSessionId);
    } catch (Throwable $e) {
        error_log('[finalize] Stripe lookup failed: ' . $e->getMessage());
        return ['status' => 'error', 'message' => 'Payment could not be verified.'];
    }

    // Compared with the lines Stripe was asked to charge, not the live cart
    $expectedTotal = (int)round(linesTotal($lines) * 100);
    if ($stripeSession->payment_status !== 'paid' || $stripeSession->amount_total !== $expectedTotal) {
        return ['status' => 'error', 'message' => 'Payment not completed.'];
    }

    $session->remove('checkout_token');

    try {
        $orderNumber = finalizeOrder($lines, $buyerInfo, $session);
        return ['status' => 'success', 'orderNumber' => $orderNumber];
    } catch (Throwable $e) {
        error_log('[finalize] Error: ' . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
