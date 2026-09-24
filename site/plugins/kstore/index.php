<?php

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/checkout.php';
require_once __DIR__ . '/src/cart/add-to-cart.php';
require_once __DIR__ . '/src/cart/update-cart-item.php';
require_once __DIR__ . '/src/cart/remove-from-cart.php';
require_once __DIR__ . '/src/cart/render-cart.php';

Kirby::plugin('kirbyshop/kstore', [
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
            'pattern' => 'kstore/cart/remove',
            'method'  => 'POST',
            'action'  => fn() => removeFromCart(),
        ],
        // Cart HTML, subtotal and count for partial JS updates
        [
            'pattern' => 'kstore/cart/render',
            'method'  => 'GET',
            'action'  => fn() => renderCartItems(),
        ],
    ],
]);
