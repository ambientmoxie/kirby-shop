<?php
// Templates can pass their own $tagline and $intro; the home page uses these defaults.
// $intro is printed as HTML so it can hold live markup (the checkout subtotal):
// escape anything that comes from content or the request before passing it.
$tagline ??= 'self hosted e-commerce starter';
$intro   ??= 'A self-hosted e-commerce starter built on Kirby CMS with Stripe Checkout. Designed for developers who need to ship a cost-effective online shop for clients with moderate needs. Get the source on <a href="https://github.com/ambientmoxie/kirby-shop" target="_blank">GitHub</a>.';
?>
<?php snippet('layout/head') ?>

<header class="header">
    <h1 class="header__title"><a href="<?= $site->url() ?>">krbshp</a></h1>
    <p class="header__tagline"><?= esc($tagline) ?></p>
    <p class="header__intro"><?= $intro ?></p>

    <button type="button" class="cart-button" data-cart-open>Cart <span data-cart-count>(<?= cartCount() ?>)</span></button>
</header>