<?php

use Kirby\Http\Response;

function removeFromCart()
{
    $data = json_decode(file_get_contents('php://input'), true);

    $productId = $data['id'] ?? '';
    $color     = $data['color'] ?? '';

    $session = kirby()->session();
    $cart = $session->get('cart', []);

    $cart = array_values(array_filter($cart, function ($item) use ($productId, $color) {
        $sameId    = ($item['id'] ?? '') === $productId;
        $sameColor = ($item['color'] ?? '') === $color;

        // Remove only the exact line
        return !($sameId && $sameColor);
    }));

    $session->set('cart', $cart);

    return Response::json($cart);
}
