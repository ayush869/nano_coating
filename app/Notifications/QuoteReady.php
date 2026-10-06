<?php

namespace App\Notifications;

use App\Models\Quote;
use App\Services\PdfRenderer;
use App\Services\Pricing\PricingService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteReady extends Notification
{
    public function __construct(public Quote $quote, public ?string $note = null) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $q = $this->quote;
        $mail = (new MailMessage)
            ->subject("Quotation {$q->display_number} is ready")
            ->greeting("Hello {$q->customer_snapshot['name']},");
        if ($this->note) {
            $mail->line($this->note);
        }

        return $mail
            ->line('Your quotation for '.PricingService::money($q->total).' (incl. GST) is attached, valid till '.$q->valid_till->format('d M Y').'.')
            ->line('You can accept it and pay online in your account.')
            ->action('View, accept & pay', route('portal.quotes.show', $q))
            ->attachData((new PdfRenderer)->quote($q)->output(), PdfRenderer::filename($q), ['mime' => 'application/pdf']);
    }
}
