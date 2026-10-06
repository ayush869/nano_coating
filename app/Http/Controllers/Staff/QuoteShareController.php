<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Quote;
use App\Notifications\QuoteReady;
use App\Services\PdfRenderer;
use App\Services\Pricing\PricingService;
use App\Services\QuoteWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class QuoteShareController extends Controller
{
    /** Staff view / download. */
    public function download(Request $request, Quote $quote, PdfRenderer $pdf)
    {
        abort_unless(QuoteController::canAccess($request->user(), $quote), 403);

        return $pdf->quote($quote)->stream(PdfRenderer::filename($quote));
    }

    /** Customer view through the signed link. */
    public function pdf(Quote $quote, PdfRenderer $pdf)
    {
        abort_if($quote->status === Quote::DRAFT, 404);

        return $pdf->quote($quote)->stream(PdfRenderer::filename($quote));
    }

    public function whatsapp(Request $request, Quote $quote, QuoteWorkflow $workflow)
    {
        abort_unless(QuoteController::canAccess($request->user(), $quote), 403);
        if ($error = $workflow->send($quote)) {
            return back()->with('error', $error);
        }

        $company = Company::current();
        $text = "Hello {$quote->customer_snapshot['name']},\n\n"
            ."Thank you for choosing {$company->name}. Your quotation {$quote->display_number} for "
            .PricingService::money($quote->total)." (incl. GST) is ready.\n\n"
            ."View, accept and pay online: ".route('portal.quotes.show', $quote)."\n"
            .'Download PDF: '.QuoteWorkflow::publicPdfUrl($quote)."\n\n"
            .'Valid till '.$quote->valid_till->format('d M Y').'.';

        $number = $quote->whatsappNumber();

        return redirect()->away('https://wa.me/'.($number ?? '').'?text='.rawurlencode($text));
    }

    public function email(Request $request, Quote $quote, QuoteWorkflow $workflow)
    {
        abort_unless(QuoteController::canAccess($request->user(), $quote), 403);
        $data = $request->validate([
            'to' => ['required', 'email'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        $wasDraft = $quote->status === Quote::DRAFT;
        if ($error = $workflow->send($quote)) {
            return back()->with('error', $error);
        }
        // Sending a draft already emailed the customer's own address.
        $alreadySent = $wasDraft && $data['to'] === $quote->customer->email && empty($data['message']);
        if (! $alreadySent) {
            Notification::route('mail', $data['to'])->notify(new QuoteReady($quote, $data['message'] ?? null));
        }

        return back()->with('ok', "Quotation sent to {$data['to']}.");
    }
}
