<?php
// Default cart line, rendered by renderCartItemsMarkup(). Override it with
// site/snippets/kstore/cart-item.php. kstore.js relies on the data-cart-* attributes:
// keep them in any override.
$product  ??= null;
$quantity ??= 1;
if (!$product) return;

$color ??= $product->color()->value() ?? '';
$price ??= $product->price()->toFloat();

$cover = $product->productImage()->toFile();
?>

<article
    class="cart-item"
    data-cart-item="<?= esc($product->uuid()->id(), 'attr') ?>">

    <?php if ($cover): ?>
        <img class="cart-item__image" src="<?= $cover->resize(160)->url() ?>" alt="<?= esc($cover->alt()->value(), 'attr') ?>" loading="lazy">
    <?php endif ?>

    <div class="cart-item__meta">

        <h3 class="cart-item__name"><?= esc($product->title()->value()) ?></h3>

        <?php if ($color !== ''): ?>
            <p class="cart-item__color"><?= esc($color) ?></p>
        <?php endif ?>

        <p class="cart-item__price">
            <?= formatPrice($price) ?>
            <span class="cart-item__qty" data-cart-qty="<?= $quantity ?>">x <?= $quantity ?></span>
        </p>

        <div class="cart-item__actions">
            <button type="button" class="cart-item__action" data-cart-action="decrease">Decrease</button>
            <button type="button" class="cart-item__action" data-cart-action="increase">Increase</button>
            <button type="button" class="cart-item__action" data-cart-action="remove">Remove</button>
        </div>

    </div>

</article>
