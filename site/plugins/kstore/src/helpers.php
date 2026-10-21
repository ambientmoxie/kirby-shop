<?php

use Kirby\Cms\Page;
use Kirby\Content\VersionCache;
use Kirby\Filesystem\Dir;

// Runs $fn while holding an exclusive lock shared by every PHP process on this server
// (flock on site/storage/locks/{name}.lock), so read → check → write sequences from
// different requests can't interleave. Once the lock is held, Kirby's in-memory content
// cache is cleared: content read earlier in this request may have changed on disk.
// Re-entrant: a nested call for a lock this request already holds just runs $fn
// (flock-ing the same file twice from one process would wait on itself forever).
function withLock(string $name, callable $fn): mixed
{
    static $held = [];
    if (isset($held[$name])) {
        return $fn();
    }

    $file = kirby()->root('site') . "/storage/locks/{$name}.lock";
    Dir::make(dirname($file));

    $handle = fopen($file, 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new Exception("Lock '{$name}' could not be acquired.");
    }

    $held[$name] = true;

    try {
        VersionCache::reset();
        return $fn();
    } finally {
        unset($held[$name]);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

// The cart in the session: [product page UUID => quantity], nothing else. Title, colour,
// price and stock are always read from the product page, so an edit in the Panel
// applies to carts that already contain the product.
function cartSessionItems(): array
{
    $cart = kirby()->session()->get('cart', []);
    if (!is_array($cart)) return [];

    return array_filter($cart,
    fn($qty, $id) => $id !== '' && is_int($qty) && $qty > 0,
    ARRAY_FILTER_USE_BOTH);
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

// Session lines resolved against Kirby, with the product's current values.
// Lines whose product is gone or no longer purchasable are left out.
function cartLines(): array
{
    $lines = [];
    foreach (cartSessionItems() as $id => $qty) {
        $product = cartProduct((string)$id);
        if (!isPurchasable($product)) continue;

        $lines[] = [
            'id'       => (string)$id,
            'product'  => $product,
            'color'    => (string)$product->color()->value(),
            'price'    => $product->price()->toFloat(),
            'quantity' => $qty,
        ];
    }
    return $lines;
}

function cartCount(): int
{
    return array_sum(cartSessionItems());
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
    $lines    = [];
    $problems = [];
    $cart     = [];

    foreach (cartSessionItems() as $id => $qty) {
        $id      = (string)$id;
        $product = cartProduct($id);

        if (!isPurchasable($product)) {
            $problems[] = 'A product in your cart is no longer available and has been removed.';
            continue;
        }

        $title = $product->title()->value();
        $stock = $product->stock()->toInt();

        if ($stock < 1) {
            $problems[] = "{$title} is out of stock and has been removed.";
            continue;
        }

        if ($stock < $qty) {
            $problems[] = "Only {$stock} × {$title} left: your quantity has been reduced.";
            $qty = $stock;
        }

        $cart[$id] = $qty;
        $lines[]   = [
            'id'       => $id,
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
