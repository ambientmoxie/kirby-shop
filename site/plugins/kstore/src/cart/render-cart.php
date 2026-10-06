<?php

use Kirby\Http\Response;

function renderCartItemsMarkup(): string
{
    $lines = cartLines();

    if (empty($lines)) {
        return '<p class="cart-drawer__empty">Your cart is empty.</p>';
    }

    // Lines carry the product's current colour and price
    $html = '';
    foreach ($lines as $line) {
        $html .= snippet('components/cart-item', [
            'product'  => $line['product'],
            'quantity' => $line['quantity'],
            'color'    => $line['color'],
            'price'    => $line['price'],
        ], true);
    }

    return $html;
}

function renderCartItems()
{
    return Response::json([
        'html'     => renderCartItemsMarkup(),
        // Fully formatted, currency included: kstore.js drops it into [data-cart-subtotal] as is
        'subtotal' => formatPrice(cartSubtotal()),
        'count'    => cartCount(),
    ]);
}
