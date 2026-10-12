<?php

use Kirby\Cms\Page;
use Kirby\Data\Data;
use Kirby\Data\Yaml;

// A pending order reserves its units while the customer pays on Stripe, until reservedUntil.
// Available stock = stock − units reserved by unexpired pending orders. An expired
// reservation just stops counting, no cron needed.
//
// Reserving (placeOrder) and decrementing (applyInventory) both run under the "orders"
// lock, so two checkouts can never get the same last unit.
//
// Stock is never clamped: a shortage (reservation lapsed, stock lowered in the Panel)
// changes nothing and sends the order to "Issue", to restock or refund.
// Limit: Panel stock edits aren't locked and can overwrite a decrement made at the same moment.

const KSTORE_RESERVATION_MINUTES = 40; // must outlast the Stripe session

// Units reserved per product id, read from disk so they're current under the lock
function reservedStock(): array
{
    $root = page('orders')?->root();
    if (!$root) return [];

    $reserved = [];
    foreach (glob($root . '/_drafts/*/order.txt') ?: [] as $file) {
        try {
            $data  = Data::read($file);
            $items = Yaml::decode($data['items'] ?? '');
        } catch (Throwable $e) {
            error_log("[stock] Unreadable order file {$file}: " . $e->getMessage());
            continue;
        }

        if (($data['state'] ?? '') !== 'pending') continue;
        if ((strtotime((string)($data['reserveduntil'] ?? '')) ?: 0) <= time()) continue;

        foreach ($items as $item) {
            $item = array_change_key_case((array)$item);
            $id   = (string)($item['productid'] ?? '');
            if ($id !== '') {
                $reserved[$id] = ($reserved[$id] ?? 0) + (int)($item['quantity'] ?? 0);
            }
        }
    }
    return $reserved;
}

// Reserves the lines and creates the pending order, or says why it can't:
// ['order' => Page|null, 'problems' => string[]]
function placeOrder(array $lines, array $buyerInfo, string $paymentMethod): array
{
    return withLock('orders', function () use ($lines, $buyerInfo, $paymentMethod) {
        reconcileExpiredCheckouts();

        $reserved = reservedStock();
        $problems = [];

        foreach ($lines as $line) {
            $stock     = cartProduct($line['id'])?->stock()->toInt() ?? 0;
            $available = $stock - ($reserved[$line['id']] ?? 0);

            if ($available < $line['quantity']) {
                $problems[] = $available > 0
                    ? "Only {$available} × {$line['title']} available right now."
                    : "{$line['title']} is not available right now.";
            }
        }

        if ($problems) {
            return ['order' => null, 'problems' => $problems];
        }

        return ['order' => createPendingOrder($lines, $buyerInfo, $paymentMethod), 'problems' => []];
    });
}

// Decrements stock for good. All lines are checked before any change, so a shortage
// changes nothing. Progress is saved per product (stockApplied): a retry never
// decrements the same product twice.
function applyInventory(Page &$order): void
{
    $applied   = $order->stockApplied()->split();
    $reserved  = reservedStock();
    $toApply   = [];
    $shortages = [];

    foreach ($order->items()->toStructure() as $item) {
        $productId = $item->productId()->value();
        if ($productId === '' || in_array($productId, $applied, true)) continue;

        $product = cartProduct($productId);
        if ($product && !isWritablePage($product)) {
            throw new Exception("Product {$productId} can't be written safely in this request.");
        }

        $qty = $item->quantity()->toInt();
        if ($product) {
            $available = $product->stock()->toInt() - ($reserved[$productId] ?? 0);
            if ($available < $qty) {
                $shortages[] = "{$item->title()}: {$qty} ordered, {$available} available";
            }
        }

        $toApply[] = [$productId, $product, $qty];
    }

    if ($shortages) {
        throw new Exception('Stock conflict, no stock was changed. ' . implode('; ', $shortages) . '. Restock or refund.');
    }

    foreach ($toApply as [$productId, $product, $qty]) {
        if ($product) {
            $product->update(['stock' => $product->stock()->toInt() - $qty]);
        } else {
            error_log("[order {$order->orderId()}] Product {$productId} no longer exists, stock not updated.");
        }

        $applied[] = $productId;
        $order = $order->update(['stockApplied' => implode(', ', $applied)]);
    }

    $order = $order->update(['state' => 'inventory_processed']);
}
