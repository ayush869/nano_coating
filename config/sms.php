<?php

return [
    /*
    | "log" writes messages to storage/logs/laravel.log. Add a driver in
    | App\Services\Sms\SmsManager to connect an SMS provider (MSG91, Twilio…).
    */
    'driver' => env('SMS_DRIVER', 'log'),

    // Show the one-time code on the login screen. Never enable in production.
    'show_otp' => env('OTP_SHOW_ON_SCREEN', env('APP_ENV') === 'local'),

    'otp_ttl_minutes' => 10,
    'otp_max_attempts' => 5,
];
