<?php

use Kirby\Http\Response;

function removeFromCart()
{
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $cart  = cartSessionItems();
    $index = cartLineIndex($cart, (string)($data['id'] ?? ''), (string)($data['color'] ?? ''));

    if ($index !== null) {
        array_splice($cart, $index, 1);
    }

    kirby()->session()->set('cart', $cart);
    return Response::json($cart);
}
