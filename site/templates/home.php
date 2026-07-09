<?php snippet('header') ?>

<?php $products = site()->find('shop')->children()->listed() ?>

<?php if ($products->isNotEmpty()): ?>
    <section class="product-list">
        <div class="product-list__products" data-carousel>
            <?php foreach ($products as $product): ?>
                <?php snippet('components/product', ['product' => $product]) ?>
            <?php endforeach ?>
        </div>
        <div class="product-list__nav">
            <button type="button" class="product-list__arrow" data-carousel-prev aria-label="Previous products">Previous</button>
            <button type="button" class="product-list__arrow" data-carousel-next aria-label="Next products">Next</button>
        </div>
    </section>
<?php endif ?>

<section class="cta">
    <h1 class="cta__title">Self-hosted commerce. No SaaS fees. Full control of your shop.</h1>
    <a href="https://github.com/ambientmoxie/kirby-shop" target="_blank" class="cta__button">download on github</a>
</section>

<?php snippet('footer') ?>
