<?php
// Templates can pass their own $intro; others get this default.
// $intro is printed as HTML so it can hold live markup (the checkout subtotal):
// escape anything that comes from content or the request before passing it.
$intro ??= 'A self-hosted e-commerce starter built on Kirby CMS with Stripe Checkout. Designed for developers who need to ship a cost-effective online shop for clients with moderate needs. Get the source on <a href="https://github.com/ambientmoxie/kirby-shop" target="_blank">GitHub</a>.';
?>

<header class="header">
    <h1 class="header__title"><a href="<?= $site->url() ?>" aria-label="<?= esc($site->title(), 'attr') ?>"><?= asset('assets/svg/logo.svg')->read() ?></a></h1>
    <p class="header__intro"><?= $intro ?></p>
    <button type="button" class="header__cart" data-cart-open>Cart <span data-cart-count>(<?= cartCount() ?>)</span></button>
</header>
