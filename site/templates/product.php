<?php snippet('header') ?>

<?php
$cover = $page->productImage()->toFile();
$hover = $page->hoverImage()->toFile();
?>

<div class="product-detail">

    <div class="product-detail__info">
        <h1 class="product-detail__title"><?= esc($page->title()->value()) ?></h1>
        <?php if ($page->color()->isNotEmpty()): ?>
            <p class="product-detail__color"><?= esc($page->color()->value() ?? '') ?></p>
        <?php endif ?>
        <p class="product-detail__price">€ <?= number_format($page->price()->toFloat(), 2) ?></p>
        <?php if ($page->shortDescription()->isNotEmpty()): ?>
            <p class="product-detail__short-description"><?= esc($page->shortDescription()->value()) ?></p>
        <?php endif ?>
        <button
            class="product-detail__btn"
            style="cursor: <?= r($page->stock()->toInt() === 0, 'not-allowed', 'pointer') ?>"
            type="button"
            data-action="add-to-cart"
            data-id="<?= esc($page->uuid()->id()) ?>"
            data-title="<?= esc($page->title()->value()) ?>"
            data-price="<?= $page->price()->toFloat() ?>"
            data-stock="<?= $page->stock()->toInt() ?>"
            data-thumb="<?= esc($cover?->resize(100)?->url() ?? '') ?>"
            data-color="<?= esc($page->color()->value() ?? '') ?>"><?= r($page->stock()->toInt() === 0, 'Out of stock', 'Add to cart') ?></button>
        <p class="product-detail__legal">
            Free returns within 30 days. Taxes included.
        </p>
    </div>

    <div class="product-detail__gallery">
        <?php if ($cover): ?>
            <?php snippet('picture', ['image' => $cover, 'sizes' => '(max-width: 768px) 100vw, 60vw']) ?>
        <?php endif ?>
        <?php if ($hover): ?>
            <?php snippet('picture', ['image' => $hover, 'sizes' => '(max-width: 768px) 100vw, 60vw']) ?>
        <?php endif ?>
    </div>

</div>

<?php snippet('footer') ?>
