<?php

use Kirby\Filesystem\Dir;

// Display number, e.g. "KS-2026-000128" (orders are identified by orderId).
// Only called from confirmOrder(), under the "orders" lock: no lock of its own needed.
// The counter file keeps numbers from being reused after an order is deleted. A missing
// counter (first run, lost in a deploy) restarts from the highest existing order; an
// unreadable one throws rather than silently restarting the sequence.
function nextOrderNumber(): string
{
    $file = kirby()->root('site') . '/storage/order-counter';

    if (is_file($file)) {
        $current = trim((string)file_get_contents($file));
        if (!ctype_digit($current)) {
            throw new Exception("Order counter is unreadable ({$file}).");
        }
        $last = (int)$current;
    } else {
        $last = highestOrderNumber();
    }

    $next = $last + 1;

    Dir::make(dirname($file));
    if (file_put_contents($file, (string)$next) === false) {
        throw new Exception('Order counter could not be written.');
    }

    return sprintf('%s-%s-%06d', option('kstore.orderPrefix', 'KS'), date('Y'), $next);
}

function highestOrderNumber(): int
{
    $highest = 0;
    foreach (page('orders')?->childrenAndDrafts() ?? [] as $order) {
        if (preg_match('/(\d+)$/', $order->orderNumber()->value(), $m)) {
            $highest = max($highest, (int)$m[1]);
        }
    }
    return $highest;
}
