<?php

return [
    /*
    | "test"     – built-in test gateway: a simulated checkout, no money moves.
    | "razorpay" – Razorpay Checkout. Use rzp_test_ keys for test mode and
    |              rzp_live_ keys in production. Falls back to "test" when
    |              the keys are missing.
    */
    'gateway' => env('PAYMENT_GATEWAY', 'test'),

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'api' => 'https://api.razorpay.com/v1',
    ],
];
