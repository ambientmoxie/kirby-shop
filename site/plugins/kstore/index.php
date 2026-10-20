<?php

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/orders/lifecycle.php';
require_once __DIR__ . '/src/orders/stock.php';
require_once __DIR__ . '/src/orders/number.php';
require_once __DIR__ . '/src/orders/emails.php';
require_once __DIR__ . '/src/stripe.php';
require_once __DIR__ . '/src/cart/add-to-cart.php';
require_once __DIR__ . '/src/cart/update-cart-item.php';
require_once __DIR__ . '/src/cart/render-cart.php';

Kirby::plugin('kirbyshop/kstore', [
    // Read as option('kirbyshop.kstore.…'), set in config.php as 'kirbyshop.kstore.stripe' => [...]
    'options' => [
        'orderPrefix' => 'KS', // order numbers: KS-2026-000128
        'stripe'      => [
            'publicKey' => null,
            'secretKey' => null,
        ],
    ],
    // A file with the same name in site/ (blueprints, controllers, snippets, templates) overrides these
    'blueprints' => [
        'pages/product'                => __DIR__ . '/blueprints/pages/product.yml',
        'pages/order'                  => __DIR__ . '/blueprints/pages/order.yml',
        'pages/orders'                 => __DIR__ . '/blueprints/pages/orders.yml',
        'kstore/tabs/orders'           => __DIR__ . '/blueprints/tabs/orders.yml',
        'kstore/fields/stripe-enabled' => __DIR__ . '/blueprints/fields/stripe-enabled.yml',
    ],
    'controllers' => [
        'checkout' => require __DIR__ . '/controllers/checkout.php',
        'success'  => require __DIR__ . '/controllers/success.php',
    ],
    'snippets' => [
        'kstore/cart-item' => __DIR__ . '/snippets/cart-item.php',
    ],
    // Kirby looks emails up as {name}.html and {name}.text
    'templates' => [
        'emails/checkout/receipt.html'        => __DIR__ . '/templates/emails/checkout/receipt.html.php',
        'emails/checkout/receipt.text'        => __DIR__ . '/templates/emails/checkout/receipt.text.php',
        'emails/checkout/checkout-admin.html' => __DIR__ . '/templates/emails/checkout/checkout-admin.html.php',
        'emails/checkout/checkout-admin.text' => __DIR__ . '/templates/emails/checkout/checkout-admin.text.php',
    ],
    'routes' => [
        [
            'pattern' => 'kstore/cart/add',
            'method'  => 'POST',
            'action'  => fn() => addToCart(),
        ],
        [
            'pattern' => 'kstore/cart/update',
            'method'  => 'POST',
            'action'  => fn() => updateCartItem(),
        ],
        [
            'pattern' => 'kstore/cart/render',
            'method'  => 'GET',
            'action'  => fn() => renderCartItems(),
        ],
    ],
]);
