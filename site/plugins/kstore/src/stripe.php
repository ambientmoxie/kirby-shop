<?php

use Kirby\Cms\Page;

// Shorter than the stock reservation (stock.php), so a session can't be paid after its
// reservation lapses. The gap gives the customer time to reach the success page.
const KSTORE_STRIPE_SESSION_MINUTES = 31; // Stripe minimum is 30

function isStripeEnabled(): bool
{
    return site()->stripeEnabled()->toBool();
}

function stripeSession(string $sessionId): \Stripe\Checkout\Session
{
    \Stripe\Stripe::setApiKey(option('stripe.secretKey'));
    return \Stripe\Checkout\Session::retrieve($sessionId);
}

function stripePayment(\Stripe\Checkout\Session $stripeSession): array
{
    return [
        'method'    => 'stripe',
        'sessionId' => $stripeSession->id,
        'amount'    => $stripeSession->amount_total,
        'currency'  => $stripeSession->currency,
    ];
}

// Called from the success page. The payment is checked with Stripe itself: a session id
// from a URL proves nothing. Returns null when nothing can be confirmed.
function finalizeFromStripe(string $stripeSessionId): ?Page
{
    try {
        $stripeSession = stripeSession($stripeSessionId);
    } catch (Throwable $e) {
        error_log('[stripe] Session lookup failed: ' . $e->getMessage());
        return null;
    }

    $orderId = (string)($stripeSession->metadata['order_id'] ?? '');

    if ($stripeSession->payment_status !== 'paid') {
        error_log("[order {$orderId}] Stripe session {$stripeSessionId} is not paid ({$stripeSession->payment_status}).");
        return null;
    }

    return processOrder($orderId, stripePayment($stripeSession));
}

// Run at each checkout for pending Stripe orders whose reservation has expired: paid ones
// are finalized (the customer never reached the success page), unpaid ones deleted.
// If Stripe can't be reached, the order is left for the next checkout.
function reconcileExpiredCheckouts(): void
{
    $expired = page('orders')?->drafts()->filter(fn($order) =>
        $order->state()->value() === 'pending' &&
        $order->paymentMethod()->value() === 'stripe' &&
        (strtotime($order->reservedUntil()->value()) ?: 0) <= time()
    )->values() ?? [];

    foreach ($expired as $order) {
        $orderId   = $order->orderId()->value();
        $sessionId = $order->stripeSessionId()->value();

        if ($sessionId === '') {
            error_log("[order {$orderId}] Expired checkout without a Stripe session id: check it in the Stripe dashboard.");
            continue;
        }

        try {
            $stripeSession = stripeSession($sessionId);
        } catch (Throwable $e) {
            error_log("[order {$orderId}] Stripe lookup for expired checkout failed: " . $e->getMessage());
            continue;
        }

        if ($stripeSession->payment_status === 'paid') {
            processOrder($orderId, stripePayment($stripeSession));
        } elseif ($stripeSession->status === 'expired') {
            kirby()->impersonate('kirby');
            $order->delete();
        }
    }
}
