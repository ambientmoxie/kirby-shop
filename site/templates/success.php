<?php snippet('layout/header') ?>

<?php
$message = match ($type) {
    'newsletter' => "Congrats, you're on the list.",
    'checkout'   => 'Your order has been placed.',
    default      => 'Thank you.',
};
?>

<section class="success">
    <h1 class="success__title"><?= esc($message) ?></h1>
    <a href="<?= $site->url() ?>" class="success__button">Back to home</a>
</section>

<?php snippet('layout/footer') ?>
