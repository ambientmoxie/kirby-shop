<?php $products = page('shop')?->children()->listed() ?>

<?php if ($products?->isNotEmpty()): ?>
    <section class="product-list">

        <div class="product-list__header">
            <h2 class="product-list__title">All products</h2>
            <div class="product-list__nav">
                <button type="button" class="product-list__arrow product-list__arrow--prev" data-carousel-prev aria-label="Previous products">
                    <?= asset('assets/images/caret.svg')->read() ?>
                </button>
                <button type="button" class="product-list__arrow" data-carousel-next aria-label="Next products">
                    <?= asset('assets/images/caret.svg')->read() ?>
                </button>
            </div>
        </div>

        <div class="product-list__products" data-carousel>
            <?php foreach ($products as $product): ?>
                <?php snippet('components/product', ['product' => $product]) ?>
            <?php endforeach ?>
        </div>

    </section>
<?php endif ?>
