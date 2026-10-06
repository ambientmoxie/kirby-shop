<?php

return function ($site, $kirby) {
    $session = $kirby->session();

    if ($session->get('newsletter_token')) {
        $session->remove('newsletter_token');
        return ['type' => 'newsletter'];
    }

    // Back from Stripe: the payment is checked with Stripe, and the order is found through
    // the order id Stripe returns, not through this browser's session
    $stripeSessionId = (string)$kirby->request()->get('session_id');
    if ($stripeSessionId !== '') {
        try {
            $order = finalizeFromStripe($stripeSessionId);
        } catch (Throwable $e) {
            error_log('[success] Finalization failed: ' . $e->getMessage());
            $order = null;
        }

        if (!$order || $order->state()->value() === 'pending') {
            $session->set('checkout_error', 'Your payment could not be confirmed. If you have been charged, please contact us.');
            go($site->find('checkout')->url());
        }

        // Only empty the cart of the visitor who placed this order
        if ($session->get('pending_order_id') === $order->orderId()->value()) {
            clearCheckoutSession($session);
        }

        return ['type' => 'checkout'];
    }

    // Offline payment: the checkout controller already placed the order
    if ($session->get('checkout_token')) {
        $session->remove('checkout_token');
        return ['type' => 'checkout'];
    }

    go($site->url());
};
