<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Services\PdfRenderer;
use App\Services\Pricing\PricingService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceIssued extends Notification
{
    public function __construct(public Invoice $invoice) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $q = $this->invoice->quote;
        $mail = (new MailMessage)
            ->subject("Tax invoice {$this->invoice->number}")
            ->line('Thank you! The work is complete and your tax invoice is attached.');
        if ($q->balance() > 0) {
            $mail->line('Balance due: '.PricingService::money($q->balance()).'. You can pay it online.')
                ->action('Pay balance', route('portal.quotes.show', $q));
        }

        return $mail->attachData((new PdfRenderer)->quote($q, $this->invoice)->output(),
            PdfRenderer::filename($q, $this->invoice), ['mime' => 'application/pdf']);
    }
}
