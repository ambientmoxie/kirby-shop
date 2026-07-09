<?php

use Kirby\Http\Response;

function cartSessionItems(): array
{
    $session = kirby()->session();
    $cart = $session->get('cart', []);
    return is_array($cart) ? $cart : [];
}

function formatEUR(float $value): string
{
    return number_format($value, 2, ',', ' ');
}

function cartSubtotal(): float
{
    return array_reduce(
        cartSessionItems(),
        fn($carry, $item) => $carry + ((float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 0)),
        0.0
    );
}

function renderCartItemsMarkup(): string
{
    $items = cartSessionItems();

    if (empty($items)) {
        return '<p class="cart__empty">Your cart is empty.</p>';
    }

    $html = '';
    foreach ($items as $item) {
        if (($item['id'] ?? '') === '') continue;

        $product = kirby()->page('page://' . $item['id']);
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
    $count = array_reduce(cartSessionItems(), fn($carry, $item) => $carry + (int)($item['quantity'] ?? 0), 0);

    return Response::json([
        'html'     => renderCartItemsMarkup(),
        // Bare amount: each template renders its own currency suffix
        'subtotal' => formatEUR(cartSubtotal()),
        'count'    => $count,
    ]);
}
