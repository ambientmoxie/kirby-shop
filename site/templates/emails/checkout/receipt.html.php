<?php
/** @var string $name */
/** @var string $surname */
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

$greeting = $fullName !== '' ? esc($fullName) : 'there';
$orderLabel = !empty($orderNumber) ? ('Order #' . esc($orderNumber)) : 'Your order';
?>
<!doctype html>
<html lang="en">
  <body style="font-family: Arial, sans-serif; line-height:1.4;">
    <h2 style="margin:0 0 12px;"><?= $orderLabel ?> confirmed</h2>

    <p style="margin:0 0 10px;">Hi <?= $greeting ?>,</p>
    <p style="margin:0 0 16px;">
      Thank you for your purchase. Here is a summary of your order:
    </p>

    <hr style="margin:16px 0;">

    <h3 style="margin:0 0 8px;">Items</h3>

    <?php if (empty($items)): ?>
      <p style="margin:0;">(No items found.)</p>
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

    <hr style="margin:16px 0;">

    <p style="margin:0;">
      — <?= site()->title() ?>
    </p>
  </body>
</html>
