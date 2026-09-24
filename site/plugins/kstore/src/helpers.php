<?php

use Kirby\Cms\Page;

// Cart lines stored in the session: id, color, title, price, thumb, quantity
function cartSessionItems(): array
{
    $cart = kirby()->session()->get('cart', []);
    return is_array($cart) ? $cart : [];
}

// Product page for a cart line id (the page UUID), or null if it no longer exists
function cartProduct(string $id): ?Page
{
    if ($id === '') return null;

    $page = kirby()->page('page://' . $id);
    return $page?->intendedTemplate()->name() === 'product' ? $page : null;
}

// Position of the line matching id + color, or null
function cartLineIndex(array $cart, string $id, string $color): ?int
{
    foreach ($cart as $index => $item) {
        if (($item['id'] ?? '') === $id && ($item['color'] ?? '') === $color) {
            return $index;
        }
    }
    return null;
}

function cartCount(): int
{
    return array_sum(array_map(fn($item) => (int)($item['quantity'] ?? 0), cartSessionItems()));
}

function cartSubtotal(): float
{
    return array_sum(array_map(
        fn($item) => (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 0),
        cartSessionItems()
    ));
}

// Single price format for the whole site (pages, cart, emails): "€ 2 000,00".
// Non-breaking spaces keep the amount from wrapping across lines.
function formatPrice(float $value): string
{
    return "€\u{00A0}" . number_format($value, 2, ',', "\u{00A0}");
}
