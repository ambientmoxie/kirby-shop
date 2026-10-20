<?php

use Kirby\Http\Response;

function updateCartItem()
{
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $id       = (string)($data['id'] ?? '');
    $quantity = (int)($data['quantity'] ?? 1);

    $cart = cartSessionItems();
    if (!isset($cart[$id])) {
        return Response::json(['error' => true, 'message' => 'Item not found in cart'], 404);
    }

    // Capped by the live stock; zero or less removes the line
    $stock    = cartProduct($id)?->stock()->toInt() ?? 0;
    $quantity = min($quantity, $stock);

    if ($quantity <= 0) {
        unset($cart[$id]);
    } else {
        $cart[$id] = $quantity;
    }

    kirby()->session()->set('cart', $cart);
    return Response::json($cart);
}
