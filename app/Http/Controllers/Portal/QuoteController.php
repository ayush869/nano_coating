<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\Payments\PaymentService;
use App\Services\PdfRenderer;
use App\Services\QuoteWorkflow;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function show(Request $request, Quote $quote, PaymentService $payments)
    {
        $this->authorizeOwner($request, $quote);
        if ($quote->status === Quote::SUPERSEDED && $quote->supersededBy) {
            return redirect()->route('portal.quotes.show', $quote->supersededBy)
                ->with('ok', 'This quotation was revised. Here is the latest version.');
        }
        if (! $quote->viewed_at) {
            $quote->update(['viewed_at' => now()]);
        }
        $quote->load('lines', 'payments', 'invoice', 'serviceJob.technician');

        $gateway = $payments->gateway();

        return view('portal.quotes.show', [
            'quote' => $quote,
            'company' => Company::current(),
            'options' => $payments->options($quote),
            'gateway' => $gateway->name(),
        ]);
    }

    public function pdf(Request $request, Quote $quote)
    {
        $this->authorizeOwner($request, $quote);

        return (new PdfRenderer)->quote($quote)->stream(PdfRenderer::filename($quote));
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        $this->authorizeOwner($request, $invoice->quote);

        return (new PdfRenderer)->quote($invoice->quote, $invoice)->stream(PdfRenderer::filename($invoice->quote, $invoice));
    }

    public function accept(Request $request, Quote $quote, QuoteWorkflow $workflow)
    {
        $this->authorizeOwner($request, $quote);
        $request->validate(['agree' => ['accepted']], ['agree.accepted' => 'Please tick that you agree to the terms.']);
        if ($quote->status !== Quote::SENT || $quote->valid_till->copy()->endOfDay()->isPast()) {
            return back()->with('error', 'This quotation can no longer be accepted online. Please contact us for a revised one.');
        }
        $workflow->accept($quote, 'customer');

        return back()->with('ok', 'Thank you! Quotation accepted. Pay the advance below to confirm your booking.');
    }

    public function reject(Request $request, Quote $quote, QuoteWorkflow $workflow)
    {
        $this->authorizeOwner($request, $quote);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:250']]);
        if ($quote->status !== Quote::SENT) {
            return back()->with('error', 'This quotation can no longer be declined online.');
        }
        $workflow->reject($quote, 'customer', $data['reason'] ?? null);

        return back()->with('ok', 'We have noted your decision. Thank you for considering us.');
    }

    private function authorizeOwner(Request $request, Quote $quote): void
    {
        abort_unless(
            $quote->customer_id === $request->user('customer')->id
                && in_array($quote->status, [...Quote::CUSTOMER_VISIBLE, Quote::SUPERSEDED], true),
            404
        );
    }
}
