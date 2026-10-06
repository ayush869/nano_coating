<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\PdfRenderer;
use App\Services\QuoteWorkflow;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with('quote.customer')->latest('id')->paginate(25);

        return view('staff.invoices.index', compact('invoices'));
    }

    public function store(Request $request, Quote $quote, QuoteWorkflow $workflow)
    {
        if ($quote->status !== Quote::CONVERTED) {
            return back()->with('error', 'Convert the quote to a job before invoicing.');
        }
        $invoice = $workflow->invoice($quote, $request->user());

        return back()->with('ok', "Invoice {$invoice->number} created.");
    }

    public function pdf(Invoice $invoice, PdfRenderer $pdf)
    {
        return $pdf->quote($invoice->quote, $invoice)->stream(PdfRenderer::filename($invoice->quote, $invoice));
    }
}
