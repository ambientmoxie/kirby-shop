<?php

return function ($page, $site, $kirby) {
    $session = $kirby->session();

    if ($kirby->request()->is('POST')) {
        $error = null;

        // Honeypot
        if (!empty($kirby->request()->get('website'))) {
            go($page->url());
        }

        // Every field as typed, also flashed back to the form on error
        $fields = ['name', 'surname', 'email', 'address', 'zipcode', 'city', 'country', 'message'];
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = trim((string)$kirby->request()->get($field));
        }

        // Required fields: all but the message
        foreach (array_diff_key($values, ['message' => true]) as $key => $val) {
            if ($val === '') {
                $error = 'Please fill the required field: ' . ucfirst($key);
                break;
            }
        }

        if (!$error && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
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
            $session->set('checkout_values', $values);
            go($page->url());
        }

        // Back from Stripe without paying (closed tab, Back link): release that order first,
        // so its reservation doesn't block this one
        try {
            $paidSessionId = releasePreviousCheckout((string)$session->get('pending_order_id'));
        } catch (Throwable $e) {
            error_log('[checkout] Previous checkout could not be released: ' . $e->getMessage());
            $session->set('checkout_error', 'Something went wrong. Please try again.');
            $session->set('checkout_values', $values);
            go($page->url());
        }

        // It was paid in the meantime: the success page confirms it, nothing is charged twice
        if ($paidSessionId) {
            go($site->find('success')->url() . '?session_id=' . urlencode($paidSessionId));
        }

        // Stored as typed: templates and emails escape on output
        $buyerInfo = array_diff_key($values, ['message' => true]) + ['additionalInformations' => $values['message']];

        // The order is saved before any payment, with the lines above as its snapshot:
        // whatever happens next (payment, browser closed, failure) there is a record of it.
        // Creating it reserves its stock, or fails if other checkouts hold the last units.
        try {
            $placed = placeOrder($lines, $buyerInfo, isStripeEnabled() ? 'stripe' : 'offline');
        } catch (Throwable $e) {
            error_log('[checkout] Pending order could not be created: ' . $e->getMessage());
            $session->set('checkout_error', 'Something went wrong. Please try again.');
            go($page->url());
        }

        if ($placed['problems']) {
            $session->set('checkout_error', implode(' ', $placed['problems']) . ' Please adjust your cart or try again in a few minutes.');
            $session->set('checkout_values', $values);
            go($page->url());
        }

        $order   = $placed['order'];
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

        $stripeUrl = startStripeCheckout($order, $lines);
        if (!$stripeUrl) {
            $session->set('checkout_error', 'Payment could not be started. Please try again.');
            go($page->url());
        }

        go($stripeUrl);
    }

    // GET: read the flashed error and values, if any
    $error  = $session->get('checkout_error');
    $values = $session->get('checkout_values', []);
    $session->remove('checkout_error');
    $session->remove('checkout_values');

    return compact('error', 'values');
};
