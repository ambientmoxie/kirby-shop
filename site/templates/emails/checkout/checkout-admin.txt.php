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

$formatEUR = function ($value) {
  $n = (float)($value ?? 0);
  return number_format($n, 2, ',', ' ') . ' EUR';
};

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
  Unit: <?= $formatEUR($unit) ?> | Line: <?= $formatEUR($line) ?>

<?php endforeach; ?>

TOTAL: <?= $formatEUR($total) ?>
<?php endif; ?>


<?php if ($message !== ''): ?>
------------------------------
CUSTOMER NOTE
------------------------------
<?= $message ?>

<?php endif; ?>
