<?php
$product  ??= null;
$quantity ??= 1;
if (!$product) return;

// Fall back to the page only when the caller has no stored line to pass
$color ??= $product->color()->value() ?? '';
$price ??= $product->price()->toFloat();

$cover = $product->productImage()->toFile();
?>

<article
    class="cart-item"
    data-cart-item="<?= esc($product->uuid()->id()) ?>"
    data-cart-color="<?= esc($color) ?>">

    <?php if ($cover): ?>
        <div class="cart-item__image">
            <?php snippet('picture', ['image' => $cover, 'sizes' => '72px']) ?>
        </div>
    <?php endif ?>

    <div class="cart-item__meta">

        <h3 class="cart-item__name"><?= esc($product->title()->value()) ?></h3>

        <?php if ($color !== ''): ?>
            <p class="cart-item__color"><?= esc($color) ?></p>
        <?php endif ?>

        <p class="cart-item__price">
            <?= number_format($price, 2) ?> EUR
            <span class="cart-item__qty" data-cart-qty="<?= $quantity ?>">x <?= $quantity ?></span>
        </p>

        <div class="cart-item__actions">
            <button type="button" data-cart-action="decrease">decrease</button>
            <button type="button" data-cart-action="increase">increase</button>
            <button type="button" data-cart-action="remove">remove</button>
        </div>

    </div>

</article>
