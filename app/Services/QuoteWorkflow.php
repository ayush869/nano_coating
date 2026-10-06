<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Notifications\PaymentReceived;
use App\Notifications\QuoteDecided;
use App\Notifications\QuoteReady;
use App\Services\Pricing\PricingService;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Every status move after a quote is saved, in one place:
 * send → accept / reject → (payment) → job → done → invoice → balance paid.
 */
class QuoteWorkflow
{
    public function __construct(private SmsSender $sms) {}

    /** Draft → Sent. Returns an error message if it cannot be sent. */
    public function send(Quote $quote): ?string
    {
        if ($quote->status !== Quote::DRAFT) {
            return null;
        }
        if (! $quote->canMoveTo(Quote::SENT)) {
            return 'The discount is waiting for admin approval, so this quote cannot be sent yet.';
        }
        $quote->update(['status' => Quote::SENT, 'sent_at' => now()]);
        $quote->serviceRequest?->update(['status' => ServiceRequest::QUOTED]);

        $quote->customer->notify(new QuoteReady($quote));
        $this->sms->send($quote->customer->phone, 'Your quotation '.$quote->display_number.' for '
            .PricingService::money($quote->total).' is ready. View, accept and pay: '.route('portal.quotes.show', $quote));

        return null;
    }

    public function accept(Quote $quote, string $by): void
    {
        if ($quote->status !== Quote::SENT) {
            return;
        }
        $quote->update(['status' => Quote::ACCEPTED, 'decided_at' => now(), 'decided_by' => $by]);
        if ($by === 'customer') {
            Notification::send($this->staffFor($quote), new QuoteDecided($quote));
        }
    }

    public function reject(Quote $quote, string $by, ?string $reason = null): void
    {
        if ($quote->status !== Quote::SENT) {
            return;
        }
        $quote->update(['status' => Quote::REJECTED, 'decided_at' => now(), 'decided_by' => $by, 'rejection_reason' => $reason]);
        $quote->serviceRequest?->update(['status' => ServiceRequest::CLOSED]);
        if ($by === 'customer') {
            Notification::send($this->staffFor($quote), new QuoteDecided($quote));
        }
    }

    /** Accepted → Converted to job. The request's technician is the default. */
    public function convertToJob(Quote $quote, ?int $technicianId = null, ?string $date = null, ?string $notes = null): ?ServiceJob
    {
        if ($quote->status !== Quote::ACCEPTED || $quote->serviceJob) {
            return $quote->serviceJob;
        }

        return DB::transaction(function () use ($quote, $technicianId, $date, $notes) {
            $job = ServiceJob::create([
                'quote_id' => $quote->id,
                'technician_id' => $technicianId ?? $quote->serviceRequest?->technician_id,
                'scheduled_on' => $date,
                'notes' => $notes,
            ]);
            $quote->update(['status' => Quote::CONVERTED]);

            return $job;
        });
    }

    /** A payment was completed (online or offline). */
    public function paymentReceived(Payment $payment): void
    {
        $quote = $payment->quote()->first();
        $quote->refreshAmountPaid();

        // Paying is accepting.
        if ($quote->status === Quote::SENT) {
            $this->accept($quote, $payment->recorded_by ? 'staff' : 'customer');
            $quote->refresh();
        }
        if ($quote->status === Quote::ACCEPTED && Setting::current()->auto_job_on_payment) {
            $this->convertToJob($quote);
        }

        $payment->customer->notify(new PaymentReceived($payment));
        Notification::send(User::where('role', User::ADMIN)->where('active', true)->get(), new PaymentReceived($payment));
        $this->sms->send($payment->customer->phone, 'Received '.PricingService::money($payment->amount).' for '
            .$quote->display_number.'. Receipt '.$payment->number.'. Thank you!');
    }

    /** Job finished: optionally issue the invoice straight away. */
    public function jobCompleted(ServiceJob $job): void
    {
        $quote = $job->quote;
        $quote->serviceRequest?->update(['status' => ServiceRequest::CLOSED]);
        if (Setting::current()->auto_invoice_on_done && $quote->status === Quote::CONVERTED) {
            $this->invoice($quote, null);
        }
    }

    public function invoice(Quote $quote, ?User $by): ?Invoice
    {
        if ($quote->status !== Quote::CONVERTED) {
            return $quote->invoice;
        }
        $invoice = DB::transaction(function () use ($quote, $by) {
            $invoice = Invoice::create([
                'number' => DocumentNumber::next('INV', now()),
                'quote_id' => $quote->id,
                'service_job_id' => $quote->serviceJob?->id,
                'date' => now()->toDateString(),
                'created_by' => $by?->id,
            ]);
            $quote->update(['status' => Quote::INVOICED]);

            return $invoice;
        });
        $quote->customer->notify(new InvoiceIssued($invoice));

        return $invoice;
    }

    public static function publicPdfUrl(Quote $quote): string
    {
        return URL::temporarySignedRoute('quotes.public-pdf', $quote->valid_till->copy()->addDays(90), ['quote' => $quote->id]);
    }

    /** Admins, plus the technician who visited. */
    private function staffFor(Quote $quote)
    {
        $users = User::where('role', User::ADMIN)->where('active', true)->get();
        if ($tech = $quote->serviceRequest?->technician) {
            $users->push($tech);
        }

        return $users->unique('id');
    }
}
