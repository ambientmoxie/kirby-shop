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

        // Rebuild every line from Kirby: current price, title and stock. Nothing commercial
        // is taken from the request or from values stored earlier in the session.
        $lines = [];
        if (!$error) {
            $checkout = checkoutLines();
            $lines    = $checkout['lines'];

            if ($checkout['problems']) {
                $error = implode(' ', $checkout['problems']) . ' Please review your order.';
            } elseif (empty($lines)) {
                $error = 'Your cart is empty.';
            }
        }

        if ($error) {
            $session->set('checkout_error', $error);
            $session->set('checkout_values', compact('name', 'surname', 'email', 'address', 'zipcode', 'city', 'country', 'message'));
            go($page->url());
        }

        // Stored as typed: templates and emails escape on output
        $buyerInfo = [
            'name'                   => $name,
            'surname'                => $surname,
            'email'                  => $email,
            'address'                => $address,
            'zipcode'                => $zipcode,
            'city'                   => $city,
            'country'                => $country,
            'additionalInformations' => $message,
        ];

        $session->set('buyerInfo', $buyerInfo);

        if (!isStripeEnabled()) {
            try {
                finalizeOrder($lines, $buyerInfo, $session);
                $session->set('checkout_token', true);
                go($site->find('success')->url());
            } catch (Throwable $e) {
                error_log('[checkout] Order failed: ' . $e->getMessage());
                $session->set('checkout_error', 'Something went wrong. Please try again.');
                go($page->url());
            }
        }

        try {
            $session->set('checkout_token', bin2hex(random_bytes(16)));

            \Stripe\Stripe::setApiKey(option('stripe.secretKey'));

            $line_items = [];
            foreach ($lines as $line) {
                $title        = $line['title'];
                $color        = $line['color'];
                $qty          = $line['quantity'];
                $unit_amount  = (int)round($line['price'] * 100);
                $thumb        = $line['thumb'];

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

            // Stripe fills in {CHECKOUT_SESSION_ID}; the success page uses it to verify the payment
            $stripeSession = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => $line_items,
                'mode'                 => 'payment',
                'success_url'          => $site->find('success')->url() . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'           => $page->url(),
            ]);

            // Snapshot of exactly what Stripe will charge: the order is recorded from this,
            // not from the live cart, so a price edited during payment can't skew it
            $session->set('stripe_session_id', $stripeSession->id);
            $session->set('checkout_lines', $lines);
            go($stripeSession->url);
        } catch (Throwable $e) {
            error_log('[checkout] Stripe session failed: ' . $e->getMessage());
            $session->set('checkout_error', 'Payment could not be started. Please try again.');
            go($page->url());
        }
    }

    // GET: read the flashed error and values, if any
    $error  = $session->get('checkout_error');
    $values = $session->get('checkout_values', []);
    $session->remove('checkout_error');
    $session->remove('checkout_values');

    return compact('error', 'values');
};
