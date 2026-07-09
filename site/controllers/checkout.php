<?php

return function ($page, $site, $kirby) {
    $session = $kirby->session();

    if ($kirby->request()->is('POST')) {
        $error = null;

        // Honeypot
        if (!empty($kirby->request()->get('website'))) {
            go($page->url());
        }

        $name    = trim((string)$kirby->request()->get('name'));
        $surname = trim((string)$kirby->request()->get('surname'));
        $email   = trim((string)$kirby->request()->get('email'));
        $address = trim((string)$kirby->request()->get('address'));
        $zipcode = trim((string)$kirby->request()->get('zipcode'));
        $city    = trim((string)$kirby->request()->get('city'));
        $country = trim((string)$kirby->request()->get('country'));
        $message = trim((string)$kirby->request()->get('message'));

        // Timing check
        $formStart = (int)$kirby->request()->get('form_start');
        if ($formStart > 0 && (time() * 1000 - $formStart) < 3000) {
            $error = 'You submitted too quickly.';
        }

        // Required fields
        if (!$error) {
            foreach (compact('name', 'surname', 'email', 'address', 'zipcode', 'city', 'country') as $key => $val) {
                if ($val === '') {
                    $error = 'Please fill the required field: ' . ucfirst($key);
                    break;
                }
            }
        }

        if (!$error && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        }

        $cart = $session->get('cart', []);
        if (!$error && (empty($cart) || !is_array($cart))) {
            $error = 'Your cart is empty.';
        }

        if ($error) {
            $session->set('checkout_error', $error);
            $session->set('checkout_values', compact('name', 'surname', 'email', 'address', 'zipcode', 'city', 'country', 'message'));
            go($page->url());
        }

        $buyerInfo = [
            'name'                   => esc($name),
            'surname'                => esc($surname),
            'email'                  => esc($email),
            'address'                => esc($address),
            'zipcode'                => esc($zipcode),
            'city'                   => esc($city),
            'country'                => esc($country),
            'additionalInformations' => esc($message),
        ];

        $session->set('buyerInfo', $buyerInfo);

        if (!isStripeEnabled()) {
            try {
                finalizeOrder($cart, $buyerInfo, $session);
                $session->set('checkout_token', true);
                go($site->find('success')->url());
            } catch (Throwable $e) {
                $session->set('checkout_error', $e->getMessage());
                go($page->url());
            }
        }

        // Stripe path — stock check
        foreach ($cart as $index => $item) {
            $uuid = $item['id'] ?? null;
            if (!$uuid) { unset($cart[$index]); continue; }

            $productPage = $kirby->page('page://' . $uuid);
            if (!$productPage) { unset($cart[$index]); continue; }

            $requestedQty = (int)($item['quantity'] ?? 0);
            $available    = (int)$productPage->stock()->int();

            if ($requestedQty < 1 || $available < $requestedQty) {
                unset($cart[$index]);
            }
        }

        $cart = array_values($cart);
        $session->set('cart', $cart);

        if (empty($cart)) {
            $session->set('checkout_error', 'Some products were unavailable and have been removed. Your cart is now empty.');
            go($page->url());
        }

        try {
            $session->set('checkout_token', bin2hex(random_bytes(16)));

            \Stripe\Stripe::setApiKey(option('stripe.secretKey'));

            $line_items = [];
            foreach ($cart as $item) {
                $title        = (string)($item['title'] ?? 'Product');
                $color        = (string)($item['color'] ?? '');
                $qty          = max(1, (int)($item['quantity'] ?? 1));
                $unit_amount  = (int)round((float)($item['price'] ?? 0) * 100);
                $thumb        = (string)($item['thumb'] ?? '');

                $displayName  = $color !== '' ? "{$title} — {$color}" : $title;
                $product_data = ['name' => $displayName];

                if ($thumb && str_starts_with($thumb, 'https://')) {
                    $product_data['images'] = [$thumb];
                }

                $line_items[] = [
                    'price_data' => [
                        'currency'     => 'eur',
                        'product_data' => $product_data,
                        'unit_amount'  => $unit_amount,
                    ],
                    'quantity' => $qty,
                ];
            }

            $stripeSession = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => $line_items,
                'mode'                 => 'payment',
                'success_url'          => $site->find('success')->url(),
                'cancel_url'           => $page->url(),
            ]);

            go($stripeSession->url);
        } catch (Throwable $e) {
            $session->set('checkout_error', $e->getMessage());
            go($page->url());
        }
    }

    // GET — read flashed error and values if any
    $error  = $session->get('checkout_error');
    $values = $session->get('checkout_values', []);
    $session->remove('checkout_error');
    $session->remove('checkout_values');

    return compact('error', 'values');
};
