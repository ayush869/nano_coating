<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Customer sign-in with a one-time code sent to their mobile. */
class AuthController extends Controller
{
    public function show()
    {
        return view('portal.auth.login');
    }

    public function sendCode(Request $request, OtpService $otp)
    {
        $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = Customer::normalisePhone($request->input('phone'));
        if (! preg_match('/^[6-9]\d{9}$/', $phone)) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid 10-digit mobile number.']);
        }

        $code = $otp->send($phone, $request->ip());
        $request->session()->put('otp_phone', $phone);

        $redirect = redirect()->route('customer.login.verify');

        return config('sms.show_otp') ? $redirect->with('otp_dev', $code) : $redirect;
    }

    public function showVerify(Request $request)
    {
        if (! $request->session()->has('otp_phone')) {
            return redirect()->route('customer.login');
        }

        return view('portal.auth.verify', ['phone' => $request->session()->get('otp_phone')]);
    }

    public function verify(Request $request, OtpService $otp)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $phone = $request->session()->get('otp_phone');
        if (! $phone) {
            return redirect()->route('customer.login');
        }

        $otp->verify($phone, $request->input('code'));

        $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => '']);
        $customer->update(['phone_verified_at' => $customer->phone_verified_at ?? now(), 'last_login_at' => now()]);

        Auth::guard('customer')->login($customer, remember: true);
        $request->session()->forget('otp_phone');
        $request->session()->regenerate();

        if ($customer->name === '') {
            return redirect()->route('portal.welcome');
        }

        return $this->intendedWithin('account', route('portal.dashboard'));
    }

    public function showProfile(Request $request)
    {
        $customer = $request->user('customer')->load('sites');

        return view('portal.profile', ['customer' => $customer, 'welcome' => $request->routeIs('portal.welcome')]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'size:15'],
        ]);
        $request->user('customer')->update($data);

        return $this->intendedWithin('account', route('portal.dashboard'))->with('ok', 'Profile saved.');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
