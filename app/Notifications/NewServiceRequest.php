<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Services\Pricing\PricingService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewServiceRequest extends Notification
{
    public function __construct(public ServiceRequest $request) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->request;

        return (new MailMessage)
            ->subject("New service request {$r->number} from {$r->customer->name}")
            ->line("{$r->customer->name} ({$r->customer->phone}) asked for a site visit.")
            ->line($r->items->count().' item(s), customer estimate '.PricingService::money($r->estimate_total).'.')
            ->action('Assign a technician', route('requests.show', $r));
    }
}
