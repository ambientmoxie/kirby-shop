<?php snippet('layout/head') ?>

<header class="header">
    <div class="header__catchphrase">Self-hosted e-commerce</div>
    <a class="header__logo" href="<?= $site->url() ?>" aria-label="Home"><?= asset('assets/images/logo.svg')->read() ?></a>
    <button type="button" class="header__bag-button" data-cart-open>Bag <span class="header__bag-count">(<?= cartCount() ?>)</span></button>
</header>