<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentNumber;
use App\Services\QuoteWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private QuoteWorkflow $workflow) {}

    /** The configured gateway; falls back to the test gateway when Razorpay keys are missing. */
    public function gateway(): PaymentGateway
    {
        $rzp = config('payments.razorpay');
        if (config('payments.gateway') === 'razorpay' && ! empty($rzp['key_id']) && ! empty($rzp['key_secret'])) {
            return new RazorpayGateway($rzp);
        }

        return new TestGateway;
    }

    public function gatewayFor(Payment $payment): PaymentGateway
    {
        return $payment->gateway === 'razorpay' ? new RazorpayGateway(config('payments.razorpay')) : new TestGateway;
    }

    /** Which online payment options a quote offers right now: purpose => amount. */
    public function options(Quote $quote): array
    {
        if (! $quote->payableOnline()) {
            return [];
        }
        $s = Setting::current();
        $options = [];
        if ($quote->amount_paid <= 0) {
            if ($s->allow_advance && $quote->advance_pct > 0 && $quote->advanceAmount() < $quote->balance()) {
                $options['advance'] = $quote->advanceAmount();
            }
            if ($s->allow_full_payment || ! $options) {
                $options['full'] = $quote->balance();
            }
        } else {
            $options['balance'] = $quote->balance();
        }

        return $options;
    }

    /** Create a pending payment and its gateway order. */
    public function start(Quote $quote, string $purpose): Payment
    {
        $options = $this->options($quote);
        if (! isset($options[$purpose])) {
            throw new PaymentException('This payment option is not available for the quotation.');
        }

        $gateway = $this->gateway();
        $payment = Payment::create([
            'quote_id' => $quote->id,
            'customer_id' => $quote->customer_id,
            'purpose' => $purpose,
            'amount' => $options[$purpose],
            'status' => Payment::CREATED,
            'gateway' => $gateway->name(),
        ]);
        $payment->setRelation('quote', $quote);

        try {
            $payment->update(['gateway_order_id' => $gateway->createOrder($payment)]);
        } catch (PaymentException $e) {
            $payment->update(['status' => Payment::FAILED, 'failure_reason' => mb_substr($e->getMessage(), 0, 250)]);
            throw $e;
        }

        return $payment;
    }

    /** Verify the checkout's response and complete the payment. */
    public function verifyAndComplete(Payment $payment, string $paymentId, string $signature, ?string $method = null): Payment
    {
        if (! $this->gatewayFor($payment)->verifyPayment((string) $payment->gateway_order_id, $paymentId, $signature)) {
            Log::warning('Payment signature mismatch', ['payment' => $payment->id]);
            $payment->update(['status' => Payment::FAILED, 'failure_reason' => 'Signature verification failed']);
            throw new PaymentException('We could not verify this payment. If money was deducted it will be refunded automatically.');
        }

        return $this->complete($payment, $paymentId, $method);
    }

    /** Mark paid exactly once, then run the quote workflow. */
    public function complete(Payment $payment, string $gatewayPaymentId, ?string $method = null, array $meta = []): Payment
    {
        $done = DB::transaction(function () use ($payment, $gatewayPaymentId, $method, $meta) {
            $locked = Payment::lockForUpdate()->find($payment->id);
            if ($locked->status === Payment::PAID) {
                return false;
            }
            $locked->update([
                'status' => Payment::PAID,
                'number' => DocumentNumber::next('RCPT', now()),
                'gateway_payment_id' => $gatewayPaymentId,
                'method' => $method ?? $locked->method,
                'meta' => array_merge($locked->meta ?? [], $meta) ?: null,
                'failure_reason' => null,
                'paid_at' => now(),
            ]);

            return true;
        });

        $payment->refresh();
        if ($done) {
            $this->workflow->paymentReceived($payment);
        }

        return $payment;
    }

    public function fail(Payment $payment, string $reason): void
    {
        if ($payment->status !== Payment::PAID) {
            $payment->update(['status' => Payment::FAILED, 'failure_reason' => mb_substr($reason, 0, 250)]);
        }
    }

    /** Cash / UPI / bank / cheque received by staff. */
    public function recordOffline(Quote $quote, float $amount, string $mode, ?string $reference, User $by): Payment
    {
        if ($amount <= 0 || $amount > $quote->balance() + 0.001) {
            throw new PaymentException('Amount must be between ₹1 and the balance due.');
        }
        $clears = $amount >= $quote->balance() - 0.001;
        $purpose = $quote->amount_paid <= 0 ? ($clears ? 'full' : 'advance') : ($clears ? 'balance' : 'part');

        $payment = Payment::create([
            'quote_id' => $quote->id,
            'customer_id' => $quote->customer_id,
            'purpose' => $purpose,
            'amount' => $amount,
            'status' => Payment::CREATED,
            'gateway' => $mode,
            'reference' => $reference,
            'recorded_by' => $by->id,
        ]);

        return $this->complete($payment, 'offline_'.$payment->id, $mode);
    }

    /**
     * Razorpay webhook: payment.captured / order.paid complete the matching
     * payment (idempotent); payment.failed records the reason.
     */
    public function handleWebhook(string $body, string $signature): string
    {
        $gateway = new RazorpayGateway(config('payments.razorpay'));
        if (! $gateway->verifyWebhook($body, $signature)) {
            throw new PaymentException('Invalid webhook signature.');
        }

        $event = json_decode($body, true) ?? [];
        $entity = $event['payload']['payment']['entity'] ?? null;
        if (! $entity || empty($entity['order_id'])) {
            return 'ignored';
        }
        $payment = Payment::where('gateway', 'razorpay')->where('gateway_order_id', $entity['order_id'])->first();
        if (! $payment) {
            return 'unknown order';
        }

        switch ($event['event'] ?? '') {
            case 'payment.captured':
            case 'order.paid':
                if ((int) round($payment->amount * 100) !== (int) ($entity['amount'] ?? -1)) {
                    Log::error('Razorpay webhook amount mismatch', ['payment' => $payment->id]);

                    return 'amount mismatch';
                }
                $this->complete($payment, $entity['id'], $entity['method'] ?? null, ['webhook' => $event['event']]);

                return 'completed';
            case 'payment.failed':
                $this->fail($payment, $entity['error_description'] ?? 'Payment failed');

                return 'failed';
        }

        return 'ignored';
    }
}
