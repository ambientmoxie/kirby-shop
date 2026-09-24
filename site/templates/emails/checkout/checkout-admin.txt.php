<?php
$fullName = trim(($name ?? '') . ' ' . ($surname ?? ''));
$email    = $email ?? '';

$address  = $address ?? '';
$zipcode  = $zipcode ?? '';
$city     = $city ?? '';
$country  = $country ?? '';

$message  = trim($message ?? '');

$items = is_array($items ?? null) ? $items : [];
$total = (float)($total ?? 0);

$orderLabel = !empty($orderNumber ?? null) ? ('Order #' . $orderNumber) : 'New order';
?>
<?= $orderLabel ?>


Name: <?= $fullName ?>

Email: <?= $email ?>


------------------------------
SHIPPING DETAILS
------------------------------
<?= $address ?>

<?= trim($zipcode . ' ' . $city) ?>

<?= $country ?>


------------------------------
ITEMS
------------------------------
<?php if (empty($items)): ?>
(No items found in cart.)
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


<?php if ($message !== ''): ?>
------------------------------
CUSTOMER NOTE
------------------------------
<?= $message ?>

<?php endif; ?>
