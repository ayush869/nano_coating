<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Quote;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentService;
use App\Services\PdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'paid');
        $payments = Payment::with('quote', 'customer', 'recorder')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')->paginate(30)->withQueryString();

        return view('staff.payments.index', [
            'payments' => $payments,
            'status' => $status,
            'gateway' => app(PaymentService::class)->gateway()->name(),
        ]);
    }

    /** Record cash / UPI / bank / cheque received offline. */
    public function store(Request $request, Quote $quote, PaymentService $payments)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'mode' => ['required', Rule::in(array_keys(Payment::OFFLINE))],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        if (! in_array($quote->status, [Quote::SENT, Quote::ACCEPTED, Quote::CONVERTED, Quote::INVOICED], true)) {
            return back()->with('error', 'Payments can only be recorded on sent or accepted quotes.');
        }

        try {
            $payment = $payments->recordOffline($quote, (float) $data['amount'], $data['mode'], $data['reference'] ?? null, $request->user());
        } catch (PaymentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', "Payment recorded. Receipt {$payment->number}.");
    }

    public function receipt(Payment $payment)
    {
        abort_unless($payment->status === Payment::PAID, 404);

        return (new PdfRenderer)->receipt($payment)->stream(str_replace('/', '-', $payment->number).'.pdf');
    }
}
