<?php

namespace App\Services;

use App\Models\Company;
use App\Models\OtpCode;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * One-time codes for customer sign-in. Codes are hashed, expire after a few
 * minutes, and lock after too many wrong attempts.
 */
class OtpService
{
    public function __construct(private SmsSender $sms) {}

    /** @return string the plain code (only shown on screen when sms.show_otp is on) */
    public function send(string $phone, ?string $ip = null): string
    {
        $recent = OtpCode::where('phone', $phone)->where('created_at', '>', now()->subMinute())->exists();
        if ($recent) {
            throw ValidationException::withMessages(['phone' => 'Please wait a minute before asking for another code.']);
        }
        if (OtpCode::where('phone', $phone)->where('created_at', '>', now()->subHour())->count() >= 6) {
            throw ValidationException::withMessages(['phone' => 'Too many codes requested. Try again in an hour.']);
        }

        $code = (string) random_int(100000, 999999);
        OtpCode::where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('sms.otp_ttl_minutes')),
            'ip' => $ip,
        ]);

        $this->sms->send($phone, "$code is your ".Company::current()->name.' login code. It expires in '
            .config('sms.otp_ttl_minutes').' minutes. Do not share it.');

        return $code;
    }

    public function verify(string $phone, string $code): void
    {
        $otp = OtpCode::where('phone', $phone)->whereNull('consumed_at')->latest('id')->first();

        if (! $otp || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'The code has expired. Ask for a new one.']);
        }
        if ($otp->attempts >= config('sms.otp_max_attempts')) {
            throw ValidationException::withMessages(['code' => 'Too many wrong attempts. Ask for a new code.']);
        }
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages(['code' => 'That code is not right.']);
        }

        $otp->update(['consumed_at' => now()]);
    }
}
