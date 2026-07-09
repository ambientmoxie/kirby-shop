<?php snippet('header') ?>

<?php
$message = match ($type) {
    'newsletter' => "Congrats, you're on the list.",
    'checkout'   => 'Your order has been placed.',
    default      => 'Thank you.',
};
?>

<section class="cta">
    <h1 class="cta__title"><?= esc($message) ?></h1>
    <a href="<?= $site->url() ?>" class="cta__button">back to home</a>
</section>

<?php snippet('footer') ?>
