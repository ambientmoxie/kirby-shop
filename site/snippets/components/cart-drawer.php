<aside class="cart-drawer" id="cart-drawer" aria-hidden="true">

    <div class="cart-drawer__backdrop" data-cart-close></div>

    <div class="cart-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="cart-drawer-title">

        <div class="cart-drawer__head">
            <h2 class="cart-drawer__title" id="cart-drawer-title">Your cart</h2>
            <button type="button" class="cart-drawer__close" data-cart-close aria-label="Close cart">Close</button>
        </div>

        <div class="cart-drawer__items" id="cart-items">
            <?= renderCartItemsMarkup() ?>
        </div>

        <div class="cart-drawer__foot">
            <p class="cart-drawer__subtotal">
                Subtotal
                <span><span data-cart-subtotal><?= formatEUR(cartSubtotal()) ?></span> EUR</span>
            </p>
            <a href="<?= site()->find('checkout')->url() ?>" class="cart-drawer__checkout">Checkout</a>
        </div>

    </div>

</aside>
