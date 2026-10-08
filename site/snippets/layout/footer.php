<footer class="footer">
    <?php snippet('components/newsletter') ?>

    <p class="footer__copyright">&copy; <?= date('Y') ?> <?= $site->title()->esc() ?></p>
</footer>

<?php snippet('components/cart-drawer') ?>

<?php snippet('layout/foot') ?>
