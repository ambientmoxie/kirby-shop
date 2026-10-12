<?php

use Kirby\Cms\Page;
use Kirby\Content\ImmutableMemoryStorage;
use Kirby\Data\Yaml;
use Kirby\Toolkit\Str;

// An order is a draft page in /orders, named after its orderId (a UUID, also sent to Stripe).
// Its state moves forward one step at a time, each saved before the next:
//
//   pending              created at checkout: the snapshot (lines, prices, buyer) to charge
//   confirmed            payment checked; order number assigned (abandoned carts use none)
//   inventory_processed  stock decremented (stock.php)
//   completed            done; emails tracked apart and retried if they fail
//
// processOrder() is safe to repeat: it resumes from the saved state. A failing step is
// logged, saved in lastError and the order moves to "Issue" until the next try succeeds.
//
// Kirby 5: update() turns the old page object into a read-only copy, and writing through it
// deletes the content files. Hence the &$order references and isWritablePage() checks.

const KSTORE_BUYER_FIELDS = ['name', 'surname', 'email', 'address', 'zipcode', 'city', 'country', 'additionalInformations'];

function findOrder(string $orderId): ?Page
{
    return $orderId !== '' ? page('orders')?->findPageOrDraft($orderId) : null;
}

function orderBuyer(Page $order): array
{
    return array_combine(KSTORE_BUYER_FIELDS, array_map(fn($field) => $order->$field()->value(), KSTORE_BUYER_FIELDS));
}

function buyerFullName(Page|array $buyer): string
{
    $buyer = $buyer instanceof Page ? orderBuyer($buyer) : $buyer;
    return trim(ucwords(strtolower((string)($buyer['name'] ?? ''))) . ' ' . ucwords(strtolower((string)($buyer['surname'] ?? ''))));
}

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

    $orderId = Str::uuid();
    $items   = array_map(fn($line) => [
        'productId' => $line['id'],
        'title'     => $line['title'],
        'color'     => $line['color'],
        'quantity'  => $line['quantity'],
        'price'     => $line['price'],
    ], $lines);
    $buyer = array_combine(KSTORE_BUYER_FIELDS, array_map(fn($field) => (string)($buyerInfo[$field] ?? ''), KSTORE_BUYER_FIELDS));

    kirby()->impersonate('kirby');

    return $ordersPage->createChild([
        'slug'     => $orderId,
        'template' => 'order',
        'isDraft'  => true,
        'content'  => [
            'title'         => 'Pending - ' . buyerFullName($buyerInfo),
            'orderId'       => $orderId,
            'state'         => 'pending',
            'paymentMethod' => $paymentMethod,
            'createdAt'     => date('Y-m-d H:i:s'),
            'reservedUntil' => date('Y-m-d H:i:s', time() + KSTORE_RESERVATION_MINUTES * 60),
            'items'         => Yaml::encode($items),
            'total'         => linesTotal($lines),
        ] + $buyer,
    ]);
}

// $payment: ['method' => 'offline'] or ['method' => 'stripe', 'sessionId', 'amount' (cents), 'currency'],
// already verified with Stripe by the caller
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

function markOrderIssue(Page &$order, string $message): void
{
    $orderId = $order->orderId()->value();
    error_log("[order {$orderId}] {$message}");

    // The order itself failed to save and can't be written again in this request:
    // the log is the record, the next attempt saves the issue
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

// Back out of "Issue" after a successful retry
function clearOrderIssue(Page &$order): void
{
    if ($order->lastError()->isEmpty()) return;

    $order = $order->update(['lastError' => '']);
    if ($order->isUnlisted()) {
        $order = $order->changeStatus('draft');
    }
}

function clearCheckoutSession($session): void
{
    $session->remove('cart');
    $session->remove('pending_order_id');
}
