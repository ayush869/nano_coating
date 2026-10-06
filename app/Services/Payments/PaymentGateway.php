<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    /** "razorpay" or "test" — stored on the payment. */
    public function name(): string;

    /** Create an order at the gateway and return its id. */
    public function createOrder(Payment $payment): string;

    /** Check the signature the checkout returned for a completed payment. */
    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool;

    /** Check a webhook body against its signature header. */
    public function verifyWebhook(string $body, string $signature): bool;
}
