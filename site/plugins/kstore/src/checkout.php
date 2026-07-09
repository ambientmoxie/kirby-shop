<?php

use Kirby\Data\Yaml;
use Kirby\Toolkit\Str;

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
            'subject'  => "New order received (#{$orderNumber})",
            'data'     => $data,
        ]);

        if ($toUser !== '') {
            $kirby->email([
                'template' => 'checkout/receipt',
                'from'     => $fromAddress,
                'to'       => [$toUser => $toName !== '' ? $toName : $toUser],
                'subject'  => "Order confirmation (#{$orderNumber})",
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

    $existingOrders = $ordersPage->childrenAndDrafts()->count();
    $orderNumber    = str_pad((string)($existingOrders + 1), 4, '0', STR_PAD_LEFT);

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
    $slug = Str::slug($orderNumber . '-' . ($buyerInfo['name'] ?? '') . '-' . ($buyerInfo['surname'] ?? '') . '-' . substr(uniqid(), -5));

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
        'slug'     => $slug,
        'template' => 'order',
        'isDraft'  => true,
        'content'  => [
            'title'                  => "#{$orderNumber} - {$capitalizedName} {$capitalizedSurname}",
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

    return $orderNumber;
}

function finalizeFromStripe(): array
{
    $session   = kirby()->session();
    $token     = $session->get('checkout_token');
    $cart      = $session->get('cart', []);
    $buyerInfo = $session->get('buyerInfo', []);

    if (!$token || empty($cart) || empty($buyerInfo)) {
        return ['status' => 'error', 'message' => 'Nothing to finalize.'];
    }

    $session->remove('checkout_token');

    try {
        $orderNumber = finalizeOrder($cart, $buyerInfo, $session);
        return ['status' => 'success', 'orderNumber' => $orderNumber];
    } catch (Throwable $e) {
        error_log('[finalize] Error: ' . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
