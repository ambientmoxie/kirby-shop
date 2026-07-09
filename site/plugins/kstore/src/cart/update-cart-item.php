<?php

use Kirby\Http\Response;

function updateCartItem()
{
    $data     = json_decode(file_get_contents('php://input'), true);
    $id       = $data['id'] ?? '';
    $color    = $data['color'] ?? '';
    $quantity = (int)($data['quantity'] ?? 1);

    $session = kirby()->session();
    $cart    = $session->get('cart', []);

    // If quantity drops to zero, remove the item entirely
    if ($quantity <= 0) {
        $cart = array_values(array_filter($cart, function ($item) use ($id, $color) {
            return !(($item['id'] ?? '') === $id && ($item['color'] ?? '') === $color);
        }));

        $session->set('cart', $cart);
        return Response::json($cart);
    }

    // Find the item by id + color and set the new quantity
    $found = false;
    foreach ($cart as &$item) {
        if (($item['id'] ?? '') === $id && ($item['color'] ?? '') === $color) {
            $stock = (int)($item['stock'] ?? 0);
            $item['quantity'] = $stock > 0 ? min($quantity, $stock) : $quantity;
            $found = true;
            break;
        }
    }
    unset($item);

    if (!$found) {
        return Response::json(['error' => true, 'message' => 'Item not found in cart'], 404);
    }

    $session->set('cart', $cart);
    return Response::json($cart);
}
