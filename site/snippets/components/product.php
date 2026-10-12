<?php
$product ??= null;
if (!$product) return;

$cover   = $product->productImage()->toFile();
$color   = $product->color()->value() ?? '';
$soldOut = $product->stock()->toInt() === 0;
?>

<article class="product">

    <?php if ($cover): ?>
        <?php // One grid column: 4, 3, 2 then 1 per row (see _product-list.scss) ?>
        <?php snippet('components/picture', [
            'image' => $cover,
            'sizes' => '(max-width: 480px) 100vw, (max-width: 768px) 50vw, (max-width: 1024px) 33vw, 25vw',
            'class' => 'product__image',
        ]) ?>
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