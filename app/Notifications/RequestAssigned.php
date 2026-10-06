<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the technician and to the customer when a visit is scheduled. */
class RequestAssigned extends Notification
{
    public function __construct(public ServiceRequest $request) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->request;
        $when = $r->visit_at?->format('D d M Y, h:i A') ?? 'soon';

        if ($notifiable instanceof \App\Models\Customer) {
            return (new MailMessage)
                ->subject("Site visit scheduled for {$r->number}")
                ->line("Our technician {$r->technician->name} will visit on {$when} to measure and prepare your quotation.")
                ->action('View your request', route('portal.requests.show', $r));
        }

        return (new MailMessage)
            ->subject("Site visit assigned: {$r->number}")
            ->line("Visit {$r->customer->name} ({$r->customer->phone}) on {$when}.")
            ->line($r->site->fullAddress())
            ->action('Open the request', route('requests.show', $r));
    }
}
