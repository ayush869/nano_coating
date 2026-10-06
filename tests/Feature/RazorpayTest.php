<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Quote;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

/**
 * Razorpay Orders API + checkout signature + webhooks, with the HTTP call
 * faked. Signatures are computed exactly as Razorpay documents them.
 */
class RazorpayTest extends FlowTestCase
{
    private const SECRET = 'test_secret_abc123';
    private const WEBHOOK_SECRET = 'whsec_xyz';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payments.gateway' => 'razorpay',
            'payments.razorpay.key_id' => 'rzp_test_1234567890',
            'payments.razorpay.key_secret' => self::SECRET,
            'payments.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    private function fakeOrders(int $status = 200, array $body = ['id' => 'order_TEST123', 'status' => 'created']): void
    {
        Http::fake(['api.razorpay.com/v1/orders' => Http::response($body, $status)]);
    }

    private function startPayment(): array
    {
        $this->fakeOrders();
        $quote = $this->sentQuote([$this->sofaLine()]);

        return [$quote, $this->actingAs($this->customer, 'customer')->postJson(route('portal.payments.start', $quote), ['purpose' => 'advance'])->assertOk()->json()];
    }

    #[Test]
    public function creates_an_order_and_returns_checkout_options(): void
    {
        [$quote, $start] = $this->startPayment();

        $this->assertSame('razorpay', $start['gateway']);
        $this->assertSame('order_TEST123', $start['options']['order_id']);
        $this->assertSame('rzp_test_1234567890', $start['options']['key']);
        $this->assertSame(147500, $start['options']['amount']); // ₹1,475.00 = 50% of ₹2,950.00, in paise

        Http::assertSent(fn ($r) => $r->url() === 'https://api.razorpay.com/v1/orders'
            && $r['amount'] === 147500 && $r['currency'] === 'INR' && $r['receipt'] === 'pay_1');
    }

    #[Test]
    public function valid_checkout_signature_completes_the_payment(): void
    {
        [$quote, $start] = $this->startPayment();
        $signature = hash_hmac('sha256', 'order_TEST123|pay_ABC', self::SECRET);

        $this->postJson($start['verify_url'], [
            'razorpay_payment_id' => 'pay_ABC', 'razorpay_order_id' => 'order_TEST123', 'razorpay_signature' => $signature,
        ])->assertOk()->assertJson(['redirect' => route('portal.quotes.show', $quote)]);

        $payment = Payment::first();
        $this->assertSame([Payment::PAID, 'pay_ABC', 'razorpay'], [$payment->status, $payment->gateway_payment_id, $payment->gateway]);
        $this->assertSame(Quote::CONVERTED, $quote->fresh()->status);
        $this->assertSame(1475.0, $quote->fresh()->amount_paid);
    }

    #[Test]
    public function tampered_signature_is_rejected(): void
    {
        [$quote, $start] = $this->startPayment();

        $this->postJson($start['verify_url'], [
            'razorpay_payment_id' => 'pay_ABC', 'razorpay_order_id' => 'order_TEST123', 'razorpay_signature' => str_repeat('0', 64),
        ])->assertStatus(422);

        $this->assertSame(Payment::FAILED, Payment::first()->status);
        $this->assertSame(0.0, $quote->fresh()->amount_paid);
    }

    #[Test]
    public function webhook_completes_once_and_rejects_bad_signatures(): void
    {
        [$quote] = $this->startPayment();
        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_WEB', 'order_id' => 'order_TEST123', 'amount' => 147500, 'method' => 'upi', 'status' => 'captured',
        ]]]]);
        $send = fn ($sig) => $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $sig,
        ], $body);

        $send('bad')->assertStatus(400);
        $this->assertSame(Payment::CREATED, Payment::first()->status);

        $good = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);
        $send($good)->assertOk()->assertJson(['status' => 'completed']);
        $send($good)->assertOk(); // duplicate delivery

        $payment = Payment::first();
        $this->assertSame([Payment::PAID, 'upi'], [$payment->status, $payment->method]);
        $this->assertSame(1, Payment::where('status', 'paid')->count());
        $this->assertSame(1475.0, $quote->fresh()->amount_paid, 'paid exactly once');
    }

    #[Test]
    public function webhook_with_wrong_amount_is_ignored(): void
    {
        $this->startPayment();
        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_X', 'order_id' => 'order_TEST123', 'amount' => 100,
        ]]]]);

        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, self::WEBHOOK_SECRET),
        ], $body)->assertOk()->assertJson(['status' => 'amount mismatch']);

        $this->assertSame(Payment::CREATED, Payment::first()->status);
    }

    #[Test]
    public function gateway_errors_are_shown_and_recorded(): void
    {
        $this->fakeOrders(401, ['error' => ['description' => 'Authentication failed']]);
        $quote = $this->sentQuote([$this->sofaLine()]);

        $this->actingAs($this->customer, 'customer')->postJson(route('portal.payments.start', $quote), ['purpose' => 'full'])
            ->assertStatus(422)->assertJson(['message' => 'Razorpay could not create the order: Authentication failed']);
        $this->assertSame(Payment::FAILED, Payment::first()->status);
    }

    #[Test]
    public function missing_keys_fall_back_to_the_test_gateway(): void
    {
        config(['payments.razorpay.key_secret' => null]);
        Http::fake();
        $quote = $this->sentQuote([$this->sofaLine()]);
        $start = $this->actingAs($this->customer)->postJson(route('portal.payments.start', $quote), ['purpose' => 'advance'])->json();

        $this->assertSame('test', $start['gateway']);
        Http::assertNothingSent();
    }
}
