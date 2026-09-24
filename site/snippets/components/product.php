<?php
$product ??= null;
if (!$product) return;

$cover   = $product->productImage()->toFile();
$color   = $product->color()->value() ?? '';
$soldOut = $product->stock()->toInt() === 0;
?>

<article class="product">

    <?php if ($product->isNew()->toBool()): ?>
        <span class="product__badge">New product</span>
    <?php endif ?>

    <h3 class="product__name"><?= esc(trim($product->title() . ' ' . $color)) ?></h3>

    <?php if ($product->shortDescription()->isNotEmpty()): ?>
        <p class="product__description"><?= esc($product->shortDescription()) ?></p>
    <?php endif ?>

    <?php if ($cover): ?>
        <?php snippet('components/picture', ['image' => $cover, 'sizes' => '500px', 'class' => 'product__image']) ?>
    <?php endif ?>

    <div class="product__footer">
        <span class="product__price"><?= formatPrice($product->price()->toFloat()) ?></span>

        <button
            class="product__button"
            type="button"
            data-action="add-to-cart"
            data-id="<?= esc($product->uuid()->id()) ?>"
            <?= r($soldOut, 'disabled') ?>>
            <?= r($soldOut, 'Out of stock', 'Add item to cart') ?>
            <span class="product__arrow" aria-hidden="true"><?= asset('assets/images/arrow.svg')->read() ?></span>
        </button>
    </div>

</article>
