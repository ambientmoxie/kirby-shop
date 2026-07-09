<?php

use Kirby\Http\Response;

function addToCart()
{
    // --------------------------------------------------
    // READ PAYLOAD FROM FRONTEND
    // --------------------------------------------------
    $data = json_decode(file_get_contents('php://input'), true);

    $thumb       = $data['thumb'] ?? '';
    $id          = $data['id'] ?? '';
    $title       = $data['title'] ?? '';
    $quantity    = (int)($data['quantity'] ?? 1);
    $price       = (float)($data['price'] ?? 0);
    $stock       = (int)($data['stock'] ?? 0);

    // Selected color key (ex: "red", "black", ...)
    $color       = isset($data['color']) ? (string)$data['color'] : '';

    $session = kirby()->session();
    $cart    = $session->get('cart', []);

    // Block adding the product when stock is depleted
    if ($stock <= 0) {
        return Response::json([
            'error'   => true,
            'message' => 'No more stock available for this item',
        ], 409);
    }

    $existingProduct = null;
    foreach ($cart as &$item) {
        $sameId    = ($item['id'] ?? '') === $id;
        $sameColor = ($item['color'] ?? '') === $color;

        if ($sameId && $sameColor) {
            $existingProduct = &$item;
            break;
        }
    }

    if ($existingProduct) {
        $newQuantity = (int)($existingProduct['quantity'] ?? 0) + $quantity;

        // Remove the item entirely if quantity goes to zero or below
        if ($newQuantity <= 0) {
            $cart = array_values(
                array_filter($cart, function ($entry) use ($id, $color) {
                    return ($entry['id'] ?? '') !== $id || ($entry['color'] ?? '') !== $color;
                })
            );

            $session->set('cart', $cart);
            return Response::json($cart);
        }

        // Cap by stock (if stock > 0)
        if ($stock > 0) {
            $newQuantity = min($newQuantity, $stock);
        }

        $existingProduct['quantity'] = $newQuantity;

        // Keep the latest metadata synced (optional but safe)
        $existingProduct['thumb'] = $thumb;
        $existingProduct['title'] = $title;
        $existingProduct['price'] = $price;
        $existingProduct['stock'] = $stock;
        $existingProduct['color'] = $color;

    } else {

        // Ensure quantity >= 1, then cap by stock (if stock > 0)
        $newQuantity = max($quantity, 1);
        if ($stock > 0) {
            $newQuantity = min($newQuantity, $stock);
        }

        // Append new cart entry
        $cart[] = [
            'thumb'    => $thumb,
            'id'       => $id,
            'title'    => $title,
            'price'    => $price,
            'quantity' => $newQuantity,
            'stock'    => $stock,
            'color'    => $color,
        ];
    }

    // Save & respond
    $session->set('cart', $cart);

    return Response::json($cart);
}
