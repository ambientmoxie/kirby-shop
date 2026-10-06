<?php

use Kirby\Cms\Page;
use Kirby\Content\ImmutableMemoryStorage;
use Kirby\Data\Yaml;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;

function isStripeEnabled(): bool
{
    $field = site()->stripeEnabled();
    $raw   = $field ? $field->value() : null;
    $value = is_bool($raw) ? $raw : strtolower(trim((string)$raw));

    if ($value === true) return true;
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

// Immutable internal identifier of an order (UUID v4). It is also the order page's
// slug, so an order is found directly by its id, and it is what external systems
// (Stripe metadata) refer to. Never derived from the number of existing orders.
function newOrderId(): string
{
    return Str::uuid();
}

function findOrder(string $orderId): ?Page
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

function buyerFullName(Page|array $buyer): string
{
    $name    = $buyer instanceof Page ? $buyer->name()->value() : ($buyer['name'] ?? '');
    $surname = $buyer instanceof Page ? $buyer->surname()->value() : ($buyer['surname'] ?? '');
    return trim(ucwords(strtolower((string)$name)) . ' ' . ucwords(strtolower((string)$surname)));
}

// ORDER LIFECYCLE
// ---------------
// An order is a page in /orders whose "state" field moves forward one step at a time,
// each step saved before the next one starts:
//
//   pending              created at checkout, before any payment. Holds the snapshot
//                        (lines, prices, total, buyer) that Stripe is asked to charge.
//   confirmed            paid on Stripe (amount checked against the snapshot) or, without
//                        Stripe, accepted for offline payment. The order number is assigned
//                        here, so abandoned checkouts don't use up numbers.
//   inventory_processed  stock decremented for every line (progress saved per product in
//                        stockApplied, so a retry never decrements the same product twice)
//   completed            all commerce state is saved
//
// Emails are tracked apart from the state (emailsSent / emailError): a failed email
// never undoes a valid order, and is retried on the next processOrder() call.
//
// processOrder() is safe to call any number of times for the same order (Stripe webhook
// redelivery, success page reload): it runs under the "orders" lock, reloads the order
// from disk, and resumes from its saved state. A failing step is logged, saved in
// lastError, and the order is moved to the Panel's "Issue" column (unlisted); the next
// call retries from that same step.
//
// Writing pages safely: in Kirby 5, update() returns a new page object and turns the
// previous one into an immutable in-memory copy (the same happens to an object whose
// write failed). Writing through such a stale object makes Kirby move its content out
// of the files, which deletes them. So the steps below take the order by reference,
// keeping the caller's variable on the latest object even when a step fails halfway,
// and nothing is ever written through an object that isWritablePage() rejects.

function isWritablePage(Page $page): bool
{
    return !($page->storage() instanceof ImmutableMemoryStorage);
}

function createPendingOrder(array $lines, array $buyerInfo, string $paymentMethod): Page
{
    $ordersPage = page('orders');
    if (!$ordersPage) {
        throw new Exception('Orders page not found.');
    }

    $orderId = newOrderId();
    $items   = array_map(fn($line) => [
        'productId' => $line['id'],
        'title'     => $line['title'],
        'color'     => $line['color'],
        'quantity'  => $line['quantity'],
        'price'     => $line['price'],
    ], $lines);

    kirby()->impersonate('kirby');

    return $ordersPage->createChild([
        'slug'     => $orderId,
        'template' => 'order',
        'isDraft'  => true,
        'content'  => [
            'title'                  => 'Pending - ' . buyerFullName($buyerInfo),
            'orderId'                => $orderId,
            'state'                  => 'pending',
            'paymentMethod'          => $paymentMethod,
            'createdAt'              => date('Y-m-d H:i:s'),
            'items'                  => Yaml::encode($items),
            'total'                  => linesTotal($lines),
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
}

// $payment describes what confirms the order:
//   ['method' => 'offline']
//   ['method' => 'stripe', 'sessionId' => ..., 'amount' => cents, 'currency' => 'eur']
// The caller is responsible for having verified a Stripe payment with Stripe itself.
function processOrder(string $orderId, array $payment): ?Page
{
    return withLock('orders', function () use ($orderId, $payment) {
        kirby()->impersonate('kirby');

        $order = findOrder($orderId);
        if (!$order) {
            error_log("[order {$orderId}] Not found, nothing processed.");
            return null;
        }

        try {
            if ($order->state()->value() === 'pending') {
                confirmOrder($order, $payment);
            }
            if ($order->state()->value() === 'confirmed') {
                applyInventory($order);
            }
            if ($order->state()->value() === 'inventory_processed') {
                $order = $order->update(['state' => 'completed']);
            }
        } catch (Throwable $e) {
            markOrderIssue($order, $e->getMessage());
            return $order;
        }

        if ($order->state()->value() !== 'completed') {
            return $order;
        }

        clearOrderIssue($order);

        if (!$order->emailsSent()->toBool()) {
            sendOrderEmailsOnce($order);
        }

        return $order;
    });
}

function confirmOrder(Page &$order, array $payment): void
{
    $method = $order->paymentMethod()->value();

    if (($payment['method'] ?? '') !== $method) {
        throw new Exception("Order expects a {$method} payment, got " . ($payment['method'] ?? 'none') . '.');
    }

    if ($method === 'stripe') {
        $expected = (int)round($order->total()->toFloat() * 100);
        $amount   = (int)($payment['amount'] ?? -1);
        $currency = (string)($payment['currency'] ?? '');
        $stored   = $order->stripeSessionId()->value();

        if ($stored !== '' && $stored !== ($payment['sessionId'] ?? '')) {
            throw new Exception("Payment is for Stripe session {$payment['sessionId']}, order belongs to {$stored}.");
        }
        if ($amount !== $expected || $currency !== 'eur') {
            throw new Exception("Stripe charged {$amount} {$currency}, order total is {$expected} eur.");
        }
    }

    $orderNumber = nextOrderNumber();

    $order = $order->update([
        'state'           => 'confirmed',
        'orderNumber'     => $orderNumber,
        'title'           => $orderNumber . ' - ' . buyerFullName($order),
        'stripeSessionId' => (string)($payment['sessionId'] ?? $order->stripeSessionId()->value()),
        'confirmedAt'     => date('Y-m-d H:i:s'),
    ]);
}

function applyInventory(Page &$order): void
{
    $applied = $order->stockApplied()->split();

    foreach ($order->items()->toStructure() as $item) {
        $productId = $item->productId()->value();
        if ($productId === '' || in_array($productId, $applied, true)) continue;

        $product = cartProduct($productId);
        if ($product && !isWritablePage($product)) {
            throw new Exception("Product {$productId} can't be written safely in this request.");
        }

        if ($product) {
            $qty = $item->quantity()->toInt();
            $product->update(['stock' => max(0, $product->stock()->toInt() - $qty)]);
        } else {
            error_log("[order {$order->orderId()}] Product {$productId} no longer exists, stock not updated.");
        }

        // Saved after each product: a retry resumes after the last one updated
        $applied[] = $productId;
        $order = $order->update(['stockApplied' => implode(', ', $applied)]);
    }

    $order = $order->update(['state' => 'inventory_processed']);
}

function markOrderIssue(Page &$order, string $message): void
{
    $orderId = $order->orderId()->value();
    error_log("[order {$orderId}] {$message}");

    // The failure was writing the order itself: its object can't be written again in
    // this request. The log above is the record; the next attempt records the issue.
    if (!isWritablePage($order)) {
        error_log("[order {$orderId}] Issue not saved on the order (it could not be written); it will be on the next attempt.");
        return;
    }

    try {
        $order = $order->update(['lastError' => date('Y-m-d H:i:s') . ' ' . $message]);
        if (!$order->isUnlisted()) {
            $order = $order->changeStatus('unlisted');
        }
    } catch (Throwable $e) {
        error_log("[order {$orderId}] Could not record the issue: " . $e->getMessage());
    }
}

// After a successful retry, take the order out of "Issue" again
function clearOrderIssue(Page &$order): void
{
    if ($order->lastError()->isEmpty()) return;

    $order = $order->update(['lastError' => '']);
    if ($order->isUnlisted()) {
        $order = $order->changeStatus('draft');
    }
}

function sendOrderEmailsOnce(Page &$order): void
{
    try {
        sendOrderEmails($order);
        $order = $order->update(['emailsSent' => true, 'emailError' => '']);
    } catch (Throwable $e) {
        error_log("[order {$order->orderId()}] Emails failed: " . $e->getMessage());
        if (isWritablePage($order)) {
            $order = $order->update(['emailError' => date('Y-m-d H:i:s') . ' ' . $e->getMessage()]);
        }
    }
}

// Throws on failure so the caller can record it
function sendOrderEmails(Page $order): void
{
    $kirby = kirby();

    $fromAddress = (string)$kirby->option('email.transport.username');
    if ($fromAddress === '') {
        throw new Exception('Email transport username missing.');
    }

    $orderNumber = $order->orderNumber()->value();
    $toUser      = trim($order->email()->value());
    $toName      = trim($order->name()->value());

    $data = [
        'orderNumber' => $orderNumber,
        'name'        => $order->name()->value(),
        'surname'     => $order->surname()->value(),
        'email'       => $order->email()->value(),
        'address'     => $order->address()->value(),
        'zipcode'     => $order->zipcode()->value(),
        'city'        => $order->city()->value(),
        'country'     => $order->country()->value(),
        'message'     => $order->additionalInformations()->value(),
        'items'       => $order->items()->yaml(),
        'total'       => $order->total()->toFloat(),
    ];

    $kirby->email([
        'template' => 'checkout/checkout-admin',
        'from'     => $fromAddress,
        'replyTo'  => $toUser !== '' ? $toUser : null,
        'to'       => $fromAddress,
        'subject'  => "New order received ({$orderNumber})",
        'data'     => $data,
    ]);

    if ($toUser === '') {
        error_log("[order {$order->orderId()}] Buyer email missing, receipt not sent.");
        return;
    }

    $kirby->email([
        'template' => 'checkout/receipt',
        'from'     => $fromAddress,
        'to'       => [$toUser => $toName !== '' ? $toName : $toUser],
        'subject'  => "Order confirmation ({$orderNumber})",
        'data'     => $data,
    ]);
}

// The cart and checkout keys of the visitor whose order has been placed
function clearCheckoutSession($session): void
{
    $session->remove('cart');
    $session->remove('pending_order_id');
}

// Asks Stripe for the checkout session and, if it is paid, processes the order named
// in its metadata. The payment is verified with Stripe itself: a session id coming
// from a URL proves nothing on its own. Returns null when nothing can be confirmed.
function finalizeFromStripe(string $stripeSessionId): ?Page
{
    try {
        \Stripe\Stripe::setApiKey(option('stripe.secretKey'));
        $stripeSession = \Stripe\Checkout\Session::retrieve($stripeSessionId);
    } catch (Throwable $e) {
        error_log('[stripe] Session lookup failed: ' . $e->getMessage());
        return null;
    }

    $orderId = (string)($stripeSession->metadata['order_id'] ?? '');

    if ($stripeSession->payment_status !== 'paid') {
        error_log("[order {$orderId}] Stripe session {$stripeSessionId} is not paid ({$stripeSession->payment_status}).");
        return null;
    }

    return processOrder($orderId, [
        'method'    => 'stripe',
        'sessionId' => $stripeSession->id,
        'amount'    => $stripeSession->amount_total,
        'currency'  => $stripeSession->currency,
    ]);
}
