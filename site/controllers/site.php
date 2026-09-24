<?php

function handleNewsletterSubscription($site, $kirby)
{
    $session  = $kirby->session();
    $referer  = $_SERVER['HTTP_REFERER'] ?? $site->url();

    // Honeypot
    if (!empty($kirby->request()->get('website'))) {
        go($referer);
    }

    $email = trim((string)$kirby->request()->get('email'));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $session->set('newsletter_error', 'Please enter a valid email address.');
        go($referer);
    }

    $existing = $site->newsletter()->toStructure()->toArray();

    foreach ($existing as $entry) {
        if (strtolower((string)($entry['value'] ?? '')) === strtolower($email)) {
            $session->set('newsletter_token', true);
            go($site->find('success')->url());
        }
    }

    $existing[] = [
        'date'  => date('Y-m-d'),
        'value' => $email,
    ];

    $kirby->impersonate('kirby');

    try {
        $site->update(['newsletter' => $existing]);
    } catch (Throwable $e) {
        $session->set('newsletter_error', 'Something went wrong. Please try again.');
        go($referer);
    }

    $fromAddress = (string)$kirby->option('email.transport.username');

    if ($fromAddress !== '') {
        try {
            $kirby->email([
                'template' => 'newsletter/newsletter-confirm',
                'from'     => $fromAddress,
                'to'       => $email,
                'subject'  => "You're on the list",
                'data'     => ['email' => $email],
            ]);
        } catch (Throwable $e) {
            error_log('[newsletter] Confirmation email failed: ' . $e->getMessage());
        }
    }

    $session->set('newsletter_token', true);
    go($site->find('success')->url());
}

return function ($site, $kirby) {
    $session = $kirby->session();
    if ($kirby->request()->is('POST') && $kirby->request()->get('_form') === 'newsletter') {
        handleNewsletterSubscription($site, $kirby);
    }
};
