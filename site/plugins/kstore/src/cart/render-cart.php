<?php

use Kirby\Http\Response;

function renderCartItemsMarkup(): string
{
    $items = cartSessionItems();

    if (empty($items)) {
        return '<p class="cart-drawer__empty">Your cart is empty.</p>';
    }

    $html = '';
    foreach ($items as $item) {
        $product = cartProduct((string)($item['id'] ?? ''));
        if (!$product) continue;

        // Colour and price come from the stored line, not the page: the line is
        // keyed on id + colour, so rendering the page's current values would
        // break update/remove whenever a product is edited after being added.
        $html .= snippet('components/cart-item', [
            'product'  => $product,
            'quantity' => (int)($item['quantity'] ?? 1),
            'color'    => (string)($item['color'] ?? ''),
            'price'    => (float)($item['price'] ?? 0),
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
