<?php $products = page('shop')?->children()->listed() ?>

<?php if ($products?->isNotEmpty()): ?>
    <section class="product-list">

        <div class="product-list__grid">
            <?php foreach ($products as $product): ?>
                <?php snippet('components/product', ['product' => $product]) ?>
            <?php endforeach ?>
        </div>

    </section>
<?php endif ?>
