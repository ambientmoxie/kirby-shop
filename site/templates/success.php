<?php
$message = match ($type) {
    'newsletter' => "Congrats, you're on the list.",
    'checkout'   => 'Your order has been placed.',
    default      => 'Thank you.',
};
?>
<?php snippet('layout/head') ?>
<?php snippet('layout/header') ?>

<section class="success">
    <h1 class="success__title"><?= esc($message) ?></h1>
</section>

<?php snippet('layout/footer') ?>
<?php snippet('layout/foot') ?>
