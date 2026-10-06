<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(PaymentService $payments)
    {
        $rzp = config('payments.razorpay');

        return view('admin.settings', [
            'settings' => Setting::current(),
            'gateway' => $payments->gateway()->name(),
            'razorpay' => [
                'configured' => ! empty($rzp['key_id']) && ! empty($rzp['key_secret']),
                'key_id' => $rzp['key_id'] ? substr($rzp['key_id'], 0, 12).'…' : null,
                'test_mode' => str_starts_with((string) $rzp['key_id'], 'rzp_test_'),
                'webhook' => ! empty($rzp['webhook_secret']),
                'webhook_url' => route('webhooks.razorpay'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'gst_pct' => ['required', 'numeric', 'min:0', 'max:50'],
            'min_item' => ['required', 'numeric', 'min:0'],
            'min_job' => ['required', 'numeric', 'min:0'],
            'visit_charge' => ['required', 'numeric', 'min:0'],
            'valid_days' => ['required', 'integer', 'min:1', 'max:365'],
            'discount_limit_sales' => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_limit_admin' => ['required', 'numeric', 'min:0', 'max:100'],
            'advance_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'estimate_note' => ['nullable', 'string', 'max:1000'],
        ]);
        foreach (['allow_advance', 'allow_full_payment', 'auto_job_on_payment', 'auto_invoice_on_done', 'portal_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        if (! $data['allow_advance'] && ! $data['allow_full_payment']) {
            $data['allow_full_payment'] = true; // customers must be able to pay somehow
        }

        Setting::current()->update($data);

        return back()->with('ok', 'Settings saved. New quotes will use them; saved quotes keep theirs.');
    }
}
