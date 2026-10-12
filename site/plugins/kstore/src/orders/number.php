<?php

use Kirby\Filesystem\Dir;

// Display number, e.g. "KS-2026-000128" (orders are identified by orderId).
// The counter file is read and incremented under flock(), so two orders confirmed at the
// same moment never share a number. A missing counter (first run, lost in a deploy)
// restarts from the highest existing order; an unreadable one throws rather than
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

// Also reads old "#0042 - ..." titles from before orderNumber existed
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
