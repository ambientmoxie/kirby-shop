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

        // The order is saved before any payment, with the lines above as its snapshot:
        // whatever happens next (payment, browser closed, failure) there is a record of it
        try {
            $order = createPendingOrder($lines, $buyerInfo, isStripeEnabled() ? 'stripe' : 'offline');
        } catch (Throwable $e) {
            error_log('[checkout] Pending order could not be created: ' . $e->getMessage());
            $session->set('checkout_error', 'Something went wrong. Please try again.');
            go($page->url());
        }

        $orderId = $order->orderId()->value();
        $session->set('pending_order_id', $orderId);

        // No Stripe: the vendor collects payment, so the order is confirmed right away.
        // Once confirmed, any later problem (stock, email) is for the admin to resolve
        // from the Panel; the customer has placed their order either way.
        if (!isStripeEnabled()) {
            try {
                $order = processOrder($orderId, ['method' => 'offline']);
            } catch (Throwable $e) {
                error_log("[order {$orderId}] Processing failed: " . $e->getMessage());
                $order = null;
            }

            if (!$order || $order->state()->value() === 'pending') {
                $session->set('checkout_error', 'Something went wrong. Please try again.');
                go($page->url());
            }

            clearCheckoutSession($session);
            $session->set('checkout_token', true);
            go($site->find('success')->url());
        }

        try {
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

            // The order id travels with the payment: Stripe hands it back with the paid
            // session, which is how the payment is matched to its order.
            // Stripe fills in {CHECKOUT_SESSION_ID}; the success page uses it to verify the payment.
            $stripeSession = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => $line_items,
                'mode'                 => 'payment',
                'client_reference_id'  => $orderId,
                'metadata'             => ['order_id' => $orderId],
                'success_url'          => $site->find('success')->url() . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'           => $page->url(),
            ]);
        } catch (Throwable $e) {
            // Nothing was charged: the pending order has nothing to record
            error_log("[order {$orderId}] Stripe session failed: " . $e->getMessage());
            try {
                $order->delete();
            } catch (Throwable $e) {
                error_log("[order {$orderId}] Pending order could not be deleted: " . $e->getMessage());
            }
            $session->set('checkout_error', 'Payment could not be started. Please try again.');
            go($page->url());
        }

        try {
            $order->update(['stripeSessionId' => $stripeSession->id]);
        } catch (Throwable $e) {
            // Not blocking: the payment is matched through the metadata order id
            error_log("[order {$orderId}] Stripe session id not saved: " . $e->getMessage());
        }

        go($stripeSession->url);
    }

    // GET: read the flashed error and values, if any
    $error  = $session->get('checkout_error');
    $values = $session->get('checkout_values', []);
    $session->remove('checkout_error');
    $session->remove('checkout_values');

    return compact('error', 'values');
};
