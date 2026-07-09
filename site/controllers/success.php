<?php

return function ($site, $kirby) {
    $session = $kirby->session();

    if ($session->get('newsletter_token')) {
        $session->remove('newsletter_token');
        return ['type' => 'newsletter'];
    }

    if ($session->get('checkout_token')) {
        // Cart still in session means Stripe payment just landed — finalize now
        if (!empty($session->get('cart'))) {
            $result = finalizeFromStripe();
            if ($result['status'] !== 'success') {
                go($site->find('checkout')->url());
            }
        }
        $session->remove('checkout_token');
        return ['type' => 'checkout'];
    }

    go($site->url());
};
