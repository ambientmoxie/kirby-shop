<?php
$fullName = trim(($name ?? '') . ' ' . ($surname ?? ''));
$items = is_array($items ?? null) ? $items : [];
$total = (float)($total ?? 0);

$orderLabel = !empty($orderNumber ?? null) ? ('Order #' . $orderNumber) : 'Your order';
?>
<?= $orderLabel ?> confirmed

Hi <?= $fullName !== '' ? $fullName : 'there' ?>,

Thank you for your purchase. Here is a summary of your order:

------------------------------
ITEMS
------------------------------
<?php if (empty($items)): ?>
(No items found.)
<?php else: ?>
<?php foreach ($items as $it): ?>
<?php
  $title = $it['title'] ?? 'Product';
  $color = $it['color'] ?? '';
  $qty   = (int)($it['quantity'] ?? 1);
  $unit  = (float)($it['price'] ?? 0);
  $line  = $unit * $qty;
?>
- <?= $title ?><?= $color !== '' ? " — {$color}" : '' ?> x<?= $qty ?>
  Unit: <?= formatPrice($unit) ?> | Line: <?= formatPrice($line) ?>

<?php endforeach; ?>

TOTAL: <?= formatPrice($total) ?>
<?php endif; ?>


— <?= site()->title() ?>
