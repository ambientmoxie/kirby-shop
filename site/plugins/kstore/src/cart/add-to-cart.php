<?php

use Kirby\Http\Response;

function addToCart()
{
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $id       = (string)($data['id'] ?? '');
    $quantity = max(1, (int)($data['quantity'] ?? 1));

    // Everything except the id and quantity comes from the product page, never from
    // the request: a price sent by the browser could be edited before checkout.
    $product = cartProduct($id);
    if (!$product) {
        return Response::json(['error' => true, 'message' => 'Product not found'], 404);
    }

    $stock = $product->stock()->toInt();
    if ($stock <= 0) {
        return Response::json(['error' => true, 'message' => 'No more stock available for this item'], 409);
    }

    $color = (string)$product->color()->value();
    $cart  = cartSessionItems();
    $index = cartLineIndex($cart, $id, $color);
    $inCart = $index === null ? 0 : (int)$cart[$index]['quantity'];

    $line = [
        'id'       => $id,
        'color'    => $color,
        'title'    => $product->title()->value(),
        'price'    => $product->price()->toFloat(),
        'thumb'    => $product->productImage()->toFile()?->resize(100)->url() ?? '',
        'quantity' => min($inCart + $quantity, $stock),
    ];

    if ($index === null) {
        $cart[] = $line;
    } else {
        $cart[$index] = $line;
    }

    kirby()->session()->set('cart', $cart);
    return Response::json($cart);
}
