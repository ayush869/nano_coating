<?php

namespace App\Notifications;

use App\Models\Quote;
use App\Services\Pricing\PricingService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells staff the customer accepted or rejected a quotation online. */
class QuoteDecided extends Notification
{
    public function __construct(public Quote $quote) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $q = $this->quote;
        $mail = (new MailMessage)
            ->subject("{$q->customer_snapshot['name']} {$q->status} quotation {$q->display_number}")
            ->line("Quotation {$q->display_number} for ".PricingService::money($q->total)." was {$q->status} by the customer.");
        if ($q->rejection_reason) {
            $mail->line('Reason: '.$q->rejection_reason);
        }

        return $mail->action('Open the quotation', route('quotes.show', $q));
    }
}
