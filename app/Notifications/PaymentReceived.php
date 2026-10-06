<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\Pricing\PricingService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification
{
    public function __construct(public Payment $payment) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->payment;
        $q = $p->quote;

        if ($notifiable instanceof Customer) {
            return (new MailMessage)
                ->subject("Payment received – receipt {$p->number}")
                ->line('We received '.PricingService::money($p->amount)." ({$p->purpose_label}) for quotation {$q->display_number}.")
                ->line('Balance due: '.PricingService::money($q->balance()).'.')
                ->action('View receipt', route('portal.payments.receipt', $p));
        }

        return (new MailMessage)
            ->subject('Payment '.PricingService::money($p->amount)." received for {$q->display_number}")
            ->line("{$q->customer_snapshot['name']} paid ".PricingService::money($p->amount)." ({$p->purpose_label}) via {$p->gateway_label}.")
            ->action('Open the quotation', route('quotes.show', $q));
    }
}
