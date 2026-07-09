<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page->isHomePage() ? esc($site->title()) : esc($page->title()) . ' — ' . esc($site->title()) ?></title>
    <meta name="robots" content="noindex, nofollow">

    <?php snippet('vite') ?>
</head>

<body>
    <div class="page page--<?= $page->intendedTemplate()->name() ?>">
