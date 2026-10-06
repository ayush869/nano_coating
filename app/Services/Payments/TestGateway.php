<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Built-in test gateway: a simulated checkout page, no money moves. It goes
 * through exactly the same verify → complete path as Razorpay, signed with
 * the app key, so the whole flow can be tested without a Razorpay account.
 */
class TestGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'test';
    }

    public function createOrder(Payment $payment): string
    {
        return 'order_test_'.Str::random(14);
    }

    public function sign(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, (string) config('app.key'));
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        return hash_equals($this->sign($orderId, $paymentId), $signature);
    }

    public function verifyWebhook(string $body, string $signature): bool
    {
        return false;
    }
}
