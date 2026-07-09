<?php
$product ??= null;
if (!$product) return;

$cover   = $product->productImage()->toFile();
$hover   = $product->hoverImage()->toFile();
$price   = $product->price()->toFloat();
$stock   = $product->stock()->toInt();
$color   = $product->color()->value() ?? '';
$soldOut = $stock === 0;
$isNew   = $product->isNew()->toBool();
?>

<article
    class="product"
    data-cart-item="<?= esc($product->uuid()->id()) ?>"
    data-cart-color="<?= esc($color) ?>">

    <?php if ($isNew): ?>
        <span class="product__badge">new product</span>
    <?php endif ?>

    <?php if ($cover): ?>
        <div class="product__image">
            <?php snippet('picture', ['image' => $cover, 'sizes' => '(max-width: 640px) 100vw, 33vw', 'class' => 'product__image-default']) ?>
            <?php if ($hover): ?>
                <?php snippet('picture', ['image' => $hover, 'sizes' => '(max-width: 640px) 100vw, 33vw', 'class' => 'product__image-hover']) ?>
            <?php endif ?>
        </div>
    <?php endif ?>

    <div class="product__footer">

        <h3 class="product__name">
            <a href="<?= $product->url() ?>"><?= esc($product->title()->value()) ?></a>
        </h3>

        <?php if ($color !== ''): ?>
            <h3 class="product__color"><?= esc($color) ?></h3>
        <?php endif ?>

        <button
            class="product__button"
            type="button"
            data-action="add-to-cart"
            data-id="<?= esc($product->uuid()->id()) ?>"
            data-title="<?= esc($product->title()->value()) ?>"
            data-price="<?= $price ?>"
            data-stock="<?= $stock ?>"
            data-thumb="<?= esc($cover?->resize(100)?->url() ?? '') ?>"
            data-color="<?= esc($color) ?>"
            <?= r($soldOut, 'disabled', '') ?>><?= r($soldOut, 'Out of stock', 'Add to cart') ?> | <?= esc($price) ?> EUR</button>

    </div>

</article>
