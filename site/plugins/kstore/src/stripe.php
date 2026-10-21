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
    \Stripe\Stripe::setApiKey(option('kirbyshop.kstore.stripe.secretKey'));
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

// Opens the Stripe payment page for a pending order and returns its URL. $lines are the
// order's lines from checkoutLines(). On failure nothing was charged: the pending order
// is deleted (freeing its reservation) and null is returned.
function startStripeCheckout(Page $order, array $lines): ?string
{
    $orderId = $order->orderId()->value();

    try {
        \Stripe\Stripe::setApiKey(option('kirbyshop.kstore.stripe.secretKey'));

        $lineItems = [];
        foreach ($lines as $line) {
            $productData = ['name' => $line['color'] !== '' ? "{$line['title']} — {$line['color']}" : $line['title']];

            // Stripe only accepts public https images (not localhost)
            if (str_starts_with($line['thumb'], 'https://')) {
                $productData['images'] = [$line['thumb']];
            }

            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => $productData,
                    'unit_amount'  => (int)round($line['price'] * 100),
                ],
                'quantity' => $line['quantity'],
            ];
        }

        // The order id travels with the payment: Stripe hands it back with the paid
        // session, which is how the payment is matched to its order.
        // Stripe fills in {CHECKOUT_SESSION_ID}; the success page uses it to verify the payment.
        $stripeSession = \Stripe\Checkout\Session::create([
            // Payment methods come from the Stripe Dashboard (stripe-php ≥ 22 rejects payment_method_types)
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            // Ends before the order's stock reservation does: see stock.php
            'expires_at'           => time() + KSTORE_STRIPE_SESSION_MINUTES * 60,
            'client_reference_id'  => $orderId,
            'metadata'             => ['order_id' => $orderId],
            'success_url'          => site()->find('success')->url() . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => site()->find('checkout')->url(),
        ]);
    } catch (Throwable $e) {
        error_log("[order {$orderId}] Stripe session failed: " . $e->getMessage());
        try {
            $order->delete();
        } catch (Throwable $e) {
            error_log("[order {$orderId}] Pending order could not be deleted: " . $e->getMessage());
        }
        return null;
    }

    // Under the lock: reservations are read from the order files while it is held,
    // so a checkout must never see this file half-written
    try {
        withLock('orders', fn() => $order->update(['stripeSessionId' => $stripeSession->id]));
    } catch (Throwable $e) {
        // Not blocking: the payment is matched through the metadata order id
        error_log("[order {$orderId}] Stripe session id not saved: " . $e->getMessage());
    }

    return $stripeSession->url;
}

// If a visitor leaves Stripe without completing the payment and tries to checkout again,
// the previous order could still reserve stock and block the new one.
// Before creating a new order, the previous pending order is released: its Stripe session
// is expired to prevent payment, then the order is deleted.
// If the payment was already completed, the Stripe session ID is returned to redirect
// the visitor to the success page and avoid charging twice.
// If Stripe cannot be reached, the previous order is kept and checkout is stopped.
function releasePreviousCheckout(string $orderId): ?string
{
    if ($orderId === '') return null;

    return withLock('orders', function () use ($orderId) {
        $order = findOrder($orderId);
        if (!$order || $order->state()->value() !== 'pending' || $order->paymentMethod()->value() !== 'stripe') {
            return null;
        }

        $sessionId = $order->stripeSessionId()->value();
        if ($sessionId === '') {
            // shortcut: without the session id it can't be expired, so the order is kept (it may
            // still be paid) and its reservation lapses on its own; rare (id save failed)
            error_log("[order {$orderId}] Previous checkout has no Stripe session id, not released.");
            return null;
        }

        $stripeSession = stripeSession($sessionId);
        if ($stripeSession->payment_status === 'paid') {
            return $sessionId;
        }
        if ($stripeSession->status === 'open') {
            $stripeSession->expire();
        }

        kirby()->impersonate('kirby');
        $order->delete();
        return null;
    });
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
    $expired = page('orders')?->drafts()->filter(
        fn($order) =>
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
