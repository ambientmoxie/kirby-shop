<?php

use Kirby\Cms\Page;

// Cart lines stored in the session: only id (page UUID) and quantity. Title, colour,
// price and stock are always read from the product page, so an edit in the Panel
// applies to carts that already contain the product.
function cartSessionItems(): array
{
    $cart = kirby()->session()->get('cart', []);
    if (!is_array($cart)) return [];

    // Normalise, merging duplicate ids (older carts were keyed on id + colour)
    $lines = [];
    foreach ($cart as $item) {
        $id  = (string)($item['id'] ?? '');
        $qty = (int)($item['quantity'] ?? 0);
        if ($id === '' || $qty < 1) continue;

        $lines[$id] = ($lines[$id] ?? 0) + $qty;
    }

    return array_map(
        fn($id, $qty) => ['id' => $id, 'quantity' => $qty],
        array_keys($lines),
        $lines
    );
}

// Product page for a cart line id (the page UUID), or null if it no longer exists
function cartProduct(string $id): ?Page
{
    if ($id === '') return null;

    $page = kirby()->page('page://' . $id);
    return $page?->intendedTemplate()->name() === 'product' ? $page : null;
}

// A product can be sold only when it is published and has a price set
function isPurchasable(?Page $product): bool
{
    return $product !== null && !$product->isDraft() && $product->price()->isNotEmpty();
}

// Position of the line for this product id, or null
function cartLineIndex(array $cart, string $id): ?int
{
    foreach ($cart as $index => $item) {
        if (($item['id'] ?? '') === $id) {
            return $index;
        }
    }
    return null;
}

// Session lines resolved against Kirby, with the product's current values.
// Lines whose product is gone or no longer purchasable are left out.
function cartLines(): array
{
    $lines = [];
    foreach (cartSessionItems() as $item) {
        $product = cartProduct($item['id']);
        if (!isPurchasable($product)) continue;

        $lines[] = [
            'id'       => $item['id'],
            'product'  => $product,
            'title'    => $product->title()->value(),
            'color'    => (string)$product->color()->value(),
            'price'    => $product->price()->toFloat(),
            'stock'    => $product->stock()->toInt(),
            'thumb'    => $product->productImage()->toFile()?->resize(100)->url() ?? '',
            'quantity' => $item['quantity'],
        ];
    }
    return $lines;
}

function cartCount(): int
{
    return array_sum(array_column(cartSessionItems(), 'quantity'));
}

function cartSubtotal(): float
{
    return linesTotal(cartLines());
}

function linesTotal(array $lines): float
{
    return array_sum(array_map(fn($line) => $line['price'] * $line['quantity'], $lines));
}

// Authoritative lines for placing an order, rebuilt from Kirby at the moment of checkout.
// Returns ['lines' => [...], 'problems' => [...]]. Any problem means the cart no longer
// matches what the customer saw, so the session cart is corrected and checkout must stop
// and show the problems, rather than charge for something different.
function checkoutLines(): array
{
    $session  = kirby()->session();
    $items    = cartSessionItems();
    $lines    = [];
    $problems = [];
    $cart     = [];

    foreach ($items as $item) {
        $product = cartProduct($item['id']);

        if (!isPurchasable($product)) {
            $problems[] = 'A product in your cart is no longer available and has been removed.';
            continue;
        }

        $title = $product->title()->value();
        $stock = $product->stock()->toInt();
        $qty   = $item['quantity'];

        if ($stock < 1) {
            $problems[] = "{$title} is out of stock and has been removed.";
            continue;
        }

        if ($stock < $qty) {
            $problems[] = "Only {$stock} × {$title} left: your quantity has been reduced.";
            $qty = $stock;
        }

        $cart[]  = ['id' => $item['id'], 'quantity' => $qty];
        $lines[] = [
            'id'       => $item['id'],
            'title'    => $title,
            'color'    => (string)$product->color()->value(),
            'price'    => $product->price()->toFloat(),
            'thumb'    => $product->productImage()->toFile()?->resize(100)->url() ?? '',
            'quantity' => $qty,
        ];
    }

    if ($problems) {
        $session->set('cart', $cart);
    }

    return ['lines' => $lines, 'problems' => $problems];
}

// Single price format for the whole site (pages, cart, emails): "€ 2 000,00".
// Non-breaking spaces keep the amount from wrapping across lines.
function formatPrice(float $value): string
{
    return "€\u{00A0}" . number_format($value, 2, ',', "\u{00A0}");
}
