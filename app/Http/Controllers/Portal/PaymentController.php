<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Quote;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentService;
use App\Services\Payments\TestGateway;
use App\Services\PdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /** Create the order and return what the browser needs to open checkout. */
    public function start(Request $request, Quote $quote)
    {
        $customer = $request->user('customer');
        abort_unless($quote->customer_id === $customer->id, 404);
        $data = $request->validate(['purpose' => ['required', Rule::in(array_keys(Payment::PURPOSES))]]);

        try {
            $payment = $this->payments->start($quote, $data['purpose']);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($payment->gateway === 'test') {
            return response()->json(['gateway' => 'test', 'redirect' => route('portal.payments.test', $payment)]);
        }

        $company = Company::current();

        return response()->json([
            'gateway' => 'razorpay',
            'options' => [
                'key' => config('payments.razorpay.key_id'),
                'amount' => (int) round($payment->amount * 100),
                'currency' => $payment->currency,
                'name' => $company->name,
                'description' => $payment->purpose_label.' · '.$quote->display_number,
                'order_id' => $payment->gateway_order_id,
                'prefill' => ['name' => $customer->name, 'email' => $customer->email, 'contact' => '+91'.$customer->phone],
                'notes' => ['quote' => $quote->display_number],
                'theme' => ['color' => '#0f766e'],
            ],
            'verify_url' => route('portal.payments.verify', $payment),
            'failed_url' => route('portal.payments.failed', $payment),
        ]);
    }

    /** Razorpay Checkout handler posts here. */
    public function verify(Request $request, Payment $payment)
    {
        $this->authorizeOwner($request, $payment);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
        ]);
        abort_unless($data['razorpay_order_id'] === $payment->gateway_order_id, 422);

        try {
            $this->payments->verifyAndComplete($payment, $data['razorpay_payment_id'], $data['razorpay_signature']);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        session()->flash('ok', 'Payment successful. Receipt '.$payment->fresh()->number.'.');

        return response()->json(['redirect' => route('portal.quotes.show', $payment->quote_id)]);
    }

    public function failed(Request $request, Payment $payment)
    {
        $this->authorizeOwner($request, $payment);
        $this->payments->fail($payment, (string) $request->input('reason', 'Cancelled by customer'));

        return response()->json(['ok' => true]);
    }

    /** Built-in test gateway: a simulated checkout page. */
    public function testCheckout(Request $request, Payment $payment)
    {
        $this->authorizeOwner($request, $payment);
        abort_unless($payment->gateway === 'test' && $payment->status === Payment::CREATED, 404);

        return view('portal.payments.test-checkout', ['payment' => $payment->load('quote'), 'company' => Company::current()]);
    }

    public function testComplete(Request $request, Payment $payment)
    {
        $this->authorizeOwner($request, $payment);
        abort_unless($payment->gateway === 'test' && $payment->status === Payment::CREATED, 404);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['success', 'failure'])],
            'method' => ['nullable', Rule::in(['upi', 'card', 'netbanking', 'wallet'])],
        ]);

        if ($data['outcome'] === 'failure') {
            $this->payments->fail($payment, 'Simulated failure on the test checkout');

            return redirect()->route('portal.quotes.show', $payment->quote_id)->with('error', 'Payment failed (test). No money was taken.');
        }

        $paymentId = 'pay_test_'.Str::random(14);
        $signature = (new TestGateway)->sign($payment->gateway_order_id, $paymentId);
        $this->payments->verifyAndComplete($payment, $paymentId, $signature, $data['method'] ?? 'upi');

        return redirect()->route('portal.quotes.show', $payment->quote_id)
            ->with('ok', 'Payment successful (test mode). Receipt '.$payment->fresh()->number.'.');
    }

    public function receipt(Request $request, Payment $payment)
    {
        $this->authorizeOwner($request, $payment);
        abort_unless($payment->status === Payment::PAID, 404);

        return (new PdfRenderer)->receipt($payment)->stream(str_replace('/', '-', $payment->number).'.pdf');
    }

    /** Razorpay → server. Verified by HMAC signature; CSRF exempt. */
    public function webhook(Request $request)
    {
        try {
            $result = $this->payments->handleWebhook($request->getContent(), (string) $request->header('X-Razorpay-Signature'));
        } catch (PaymentException $e) {
            Log::warning('Razorpay webhook rejected: '.$e->getMessage());

            return response()->json(['message' => 'invalid signature'], 400);
        }

        return response()->json(['status' => $result]);
    }

    private function authorizeOwner(Request $request, Payment $payment): void
    {
        abort_unless($payment->customer_id === $request->user('customer')->id, 404);
    }
}
