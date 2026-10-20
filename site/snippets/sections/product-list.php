<?php $products = page('shop')?->children()->listed() ?>

<?php if ($products?->isNotEmpty()): ?>
    <section class="product-list">

        <div class="product-list__grid">
            <?php foreach ($products as $product): ?>
                <button
                    class="product-list__product"
                    type="button"
                    data-action="add-to-cart"
                    data-id="<?= esc($product->uuid()->id(), 'attr') ?>"
                    <?= r($product->stock()->toInt() === 0, 'disabled') ?>>
                    <?= esc($product->title()) ?>
                </button>
                <?php snippet('components/picture', [
                    'image' => $product->productImage()->toFile(),
                    'sizes' => '300px', // matches --preview-width
                    'class' => 'product-list__image',
                ]) ?>
            <?php endforeach ?>
        </div>

    </section>
<?php endif ?>
