<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * Razorpay Checkout (Orders API + signature verification).
 * Docs: https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/
 */
class RazorpayGateway implements PaymentGateway
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'razorpay';
    }

    public function keyId(): string
    {
        return (string) $this->config['key_id'];
    }

    public function isTestMode(): bool
    {
        return str_starts_with($this->keyId(), 'rzp_test_');
    }

    public function createOrder(Payment $payment): string
    {
        $response = Http::withBasicAuth($this->config['key_id'], $this->config['key_secret'])
            ->acceptJson()
            ->timeout(15)
            ->post($this->config['api'].'/orders', [
                'amount' => (int) round($payment->amount * 100), // paise
                'currency' => $payment->currency,
                'receipt' => 'pay_'.$payment->id,
                'notes' => [
                    'payment_id' => (string) $payment->id,
                    'quote' => $payment->quote->display_number,
                    'purpose' => $payment->purpose,
                ],
            ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new PaymentException('Razorpay could not create the order: '
                .($response->json('error.description') ?? 'HTTP '.$response->status()));
        }

        return $response->json('id');
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) $this->config['key_secret']);

        return hash_equals($expected, $signature);
    }

    public function verifyWebhook(string $body, string $signature): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }
}
