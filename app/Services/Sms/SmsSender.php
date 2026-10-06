<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Sends text messages. The "log" driver writes them to the application log,
 * which is enough for development and testing. To go live, add a provider
 * branch here (MSG91, Twilio, Gupshup…) and set SMS_DRIVER.
 */
class SmsSender
{
    public function send(string $phone, string $message): void
    {
        match (config('sms.driver')) {
            'null' => null,
            default => Log::info('[SMS to '.$phone.'] '.$message),
        };
    }
}
