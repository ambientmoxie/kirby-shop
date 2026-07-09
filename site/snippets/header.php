<?php snippet('head') ?>

<?php
$cartCount = array_reduce(
    cartSessionItems(),
    fn($carry, $item) => $carry + (int)($item['quantity'] ?? 0),
    0
);
?>

<header class="header">
    <div class="header__title">
        <a href="<?= $site->url() ?>">
            kirbyshop self-hosted shop starter
        </a>
    </div>
    <button type="button" class="header__bag-button" data-cart-open>Bag <span class="header__bag-count">(<?= $cartCount ?>)</span></button>
</header>

<?php snippet('components/cart-drawer') ?>
