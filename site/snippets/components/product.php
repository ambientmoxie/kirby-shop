<?php
$product ??= null;
if (!$product) return;

$cover   = $product->productImage()->toFile();
$color   = $product->color()->value() ?? '';
$soldOut = $product->stock()->toInt() === 0;
?>

<article class="product">

    <?php if ($cover): ?>
        <?php snippet('components/picture', ['image' => $cover, 'sizes' => '500px', 'class' => 'product__image']) ?>
    <?php endif ?>

    <div class="product__footer">

        <?php if ($product->shortDescription()->isNotEmpty()): ?>
            <p class="product__description"><?= esc($product->shortDescription()) ?></p>
        <?php endif ?>

        <button
            class="product__button"
            type="button"
            data-action="add-to-cart"
            data-id="<?= esc($product->uuid()->id()) ?>"
            <?= r($soldOut, 'disabled') ?>>
            <?= r($soldOut, 'Out of stock', 'Add to cart') ?>
        </button>

        <span class="product__price"><?= formatPrice($product->price()->toFloat()) ?></span>
    </div>

</article>