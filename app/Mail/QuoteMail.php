<?php

namespace App\Mail;

use App\Http\Controllers\QuoteShareController;
use App\Models\Company;
use App\Models\Quote;
use App\Services\PdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote, public ?string $note = null) {}

    public function envelope(): Envelope
    {
        $company = Company::current();

        return new Envelope(
            subject: "Quotation {$this->quote->display_number} from {$company->name}",
            replyTo: $company->email ? [$company->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.quote', with: [
            'company' => Company::current(),
            'link' => QuoteShareController::publicUrl($this->quote),
        ]);
    }

    public function attachments(): array
    {
        $pdf = (new PdfRenderer)->quote($this->quote)->output();

        return [Attachment::fromData(fn () => $pdf, PdfRenderer::filename($this->quote))->withMime('application/pdf')];
    }
}
