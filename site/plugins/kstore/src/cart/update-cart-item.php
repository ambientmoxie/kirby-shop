<?php

use Kirby\Http\Response;

function updateCartItem()
{
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $id       = (string)($data['id'] ?? '');
    $color    = (string)($data['color'] ?? '');
    $quantity = (int)($data['quantity'] ?? 1);

    $cart  = cartSessionItems();
    $index = cartLineIndex($cart, $id, $color);

    if ($index === null) {
        return Response::json(['error' => true, 'message' => 'Item not found in cart'], 404);
    }

    // Capped by the live stock; zero or less removes the line
    $stock    = cartProduct($id)?->stock()->toInt() ?? 0;
    $quantity = min($quantity, $stock);

    if ($quantity <= 0) {
        array_splice($cart, $index, 1);
    } else {
        $cart[$index]['quantity'] = $quantity;
    }

    kirby()->session()->set('cart', $cart);
    return Response::json($cart);
}
