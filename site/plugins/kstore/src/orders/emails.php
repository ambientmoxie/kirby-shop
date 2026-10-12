<?php

use Kirby\Cms\Page;

// Never throws: a failed email doesn't undo a paid order. The error is saved on the
// order (emailError) and sending is retried on the next processOrder() call.
function sendOrderEmailsOnce(Page &$order): void
{
    try {
        sendOrderEmails($order);
        $order = $order->update(['emailsSent' => true, 'emailError' => '']);
    } catch (Throwable $e) {
        error_log("[order {$order->orderId()}] Emails failed: " . $e->getMessage());
        if (isWritablePage($order)) {
            $order = $order->update(['emailError' => date('Y-m-d H:i:s') . ' ' . $e->getMessage()]);
        }
    }
}

// Admin notification, then the buyer's receipt
function sendOrderEmails(Page $order): void
{
    $kirby = kirby();

    $fromAddress = (string)$kirby->option('email.transport.username');
    if ($fromAddress === '') {
        throw new Exception('Email transport username missing.');
    }

    $orderNumber = $order->orderNumber()->value();
    $toUser      = trim($order->email()->value());
    $toName      = trim($order->name()->value());

    $data = orderBuyer($order) + [
        'orderNumber' => $orderNumber,
        'message'     => $order->additionalInformations()->value(),
        'items'       => $order->items()->yaml(),
        'total'       => $order->total()->toFloat(),
    ];

    $kirby->email([
        'template' => 'checkout/checkout-admin',
        'from'     => $fromAddress,
        'replyTo'  => $toUser !== '' ? $toUser : null,
        'to'       => $fromAddress,
        'subject'  => "New order received ({$orderNumber})",
        'data'     => $data,
    ]);

    if ($toUser === '') {
        error_log("[order {$order->orderId()}] Buyer email missing, receipt not sent.");
        return;
    }

    $kirby->email([
        'template' => 'checkout/receipt',
        'from'     => $fromAddress,
        'to'       => [$toUser => $toName !== '' ? $toName : $toUser],
        'subject'  => "Order confirmation ({$orderNumber})",
        'data'     => $data,
    ]);
}
