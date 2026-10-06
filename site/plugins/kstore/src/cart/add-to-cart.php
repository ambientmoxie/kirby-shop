<?php

use Kirby\Http\Response;

function addToCart()
{
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $id       = (string)($data['id'] ?? '');
    $quantity = max(1, (int)($data['quantity'] ?? 1));

    // Only the id and quantity are kept: everything else is read from the product
    // page whenever the cart is shown or checked out (see cartLines/checkoutLines).
    $product = cartProduct($id);
    if (!isPurchasable($product)) {
        return Response::json(['error' => true, 'message' => 'Product not found'], 404);
    }

    $stock = $product->stock()->toInt();
    if ($stock <= 0) {
        return Response::json(['error' => true, 'message' => 'No more stock available for this item'], 409);
    }

    $cart   = cartSessionItems();
    $index  = cartLineIndex($cart, $id);
    $inCart = $index === null ? 0 : $cart[$index]['quantity'];

    $line = [
        'id'       => $id,
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
