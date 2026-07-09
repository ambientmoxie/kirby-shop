<?php
/** @var string $name */
/** @var string $surname */
/** @var string $email */
/** @var string $address */
/** @var string $zipcode */
/** @var string $city */
/** @var string $country */
/** @var string $message */
/** @var array  $items */
/** @var float|int $total */
/** @var string|null $orderNumber */

$fullName = trim(($name ?? '') . ' ' . ($surname ?? ''));
$items = is_array($items ?? null) ? $items : [];
$total = (float)($total ?? 0);

$formatEUR = function ($value) {
  $n = (float)($value ?? 0);
  return number_format($n, 2, ',', ' ') . ' EUR';
};

$orderLabel = !empty($orderNumber) ? ('Order #' . esc($orderNumber)) : 'New order';
?>
<!doctype html>
<html lang="en">
  <body style="font-family: Arial, sans-serif; line-height:1.4;">
    <h2 style="margin:0 0 12px;"><?= $orderLabel ?></h2>

    <p style="margin:0 0 6px;"><strong>Name:</strong> <?= esc($fullName) ?></p>
    <p style="margin:0 0 6px;"><strong>Email:</strong> <?= esc($email ?? '') ?></p>

    <hr style="margin:16px 0;">

    <h3 style="margin:0 0 8px;">Shipping details</h3>
    <p style="margin:0;">
      <?= esc($address ?? '') ?><br>
      <?= esc($zipcode ?? '') ?> <?= esc($city ?? '') ?><br>
      <?= esc($country ?? '') ?>
    </p>

    <hr style="margin:16px 0;">

    <h3 style="margin:0 0 8px;">Items</h3>

    <?php if (empty($items)): ?>
      <p style="margin:0;">(No items found in cart.)</p>
    <?php else: ?>
      <table cellpadding="0" cellspacing="0" border="0" style="width:100%; border-collapse: collapse;">
        <thead>
          <tr>
            <th align="left" style="padding:8px 0; border-bottom:1px solid #ddd;">Product</th>
            <th align="left" style="padding:8px 0; border-bottom:1px solid #ddd;">Color</th>
            <th align="right" style="padding:8px 0; border-bottom:1px solid #ddd;">Qty</th>
            <th align="right" style="padding:8px 0; border-bottom:1px solid #ddd;">Unit</th>
            <th align="right" style="padding:8px 0; border-bottom:1px solid #ddd;">Line</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <?php
              $title = esc($it['title'] ?? 'Product');
              $color = esc($it['color'] ?? '');
              $qty   = (int)($it['quantity'] ?? 1);
              $unit  = (float)($it['price'] ?? 0);
              $line  = $unit * $qty;
            ?>
            <tr>
              <td style="padding:8px 0; border-bottom:1px solid #f0f0f0;"><?= $title ?></td>
              <td style="padding:8px 0; border-bottom:1px solid #f0f0f0;"><?= $color !== '' ? $color : '—' ?></td>
              <td align="right" style="padding:8px 0; border-bottom:1px solid #f0f0f0;"><?= $qty ?></td>
              <td align="right" style="padding:8px 0; border-bottom:1px solid #f0f0f0;"><?= $formatEUR($unit) ?></td>
              <td align="right" style="padding:8px 0; border-bottom:1px solid #f0f0f0;"><?= $formatEUR($line) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <p style="margin:12px 0 0; text-align:right;">
        <strong>Total: <?= $formatEUR($total) ?></strong>
      </p>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
      <hr style="margin:16px 0;">
      <h3 style="margin:0 0 8px;">Customer note</h3>
      <p style="margin:0; white-space: pre-line;"><?= esc($message) ?></p>
    <?php endif; ?>
  </body>
</html>
