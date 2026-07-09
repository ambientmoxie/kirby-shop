<?php

require_once __DIR__ . '/src/checkout.php';
require_once __DIR__ . '/src/cart/add-to-cart.php';
require_once __DIR__ . '/src/cart/update-cart-item.php';
require_once __DIR__ . '/src/cart/remove-from-cart.php';
require_once __DIR__ . '/src/cart/render-cart.php';

Kirby::plugin('ambientmoxie/kstore', [
    'routes' => [
        // Cart — add item
        [
            'pattern' => 'kstore/cart/add',
            'method'  => 'POST',
            'action'  => fn() => addToCart(),
        ],

        // Cart — update quantity
        [
            'pattern' => 'kstore/cart/update',
            'method'  => 'POST',
            'action'  => fn() => updateCartItem(),
        ],

        // Cart — remove item
        [
            'pattern' => 'kstore/cart/remove',
            'method'  => 'POST',
            'action'  => fn() => removeFromCart(),
        ],

        // Cart — render HTML for partial JS updates
        [
            'pattern' => 'kstore/cart/render',
            'method'  => 'GET',
            'action'  => fn() => renderCartItems(),
        ],
    ],
]);
