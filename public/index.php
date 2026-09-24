<?php
// https://getkirby.com/docs/guide/configuration/custom-folder-setup

require __DIR__ . '/../kirby/bootstrap.php';

$kirby = new Kirby([
    'roots' => [
        'index'   => __DIR__,
        'base'    => dirname(__DIR__),
        'content' => dirname(__DIR__) . '/content',
        'site'    => dirname(__DIR__) . '/site',
        'kirby'   => dirname(__DIR__) . '/kirby'
    ]
]);

echo $kirby->render();
