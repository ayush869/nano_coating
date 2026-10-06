@extends('layouts.staff')
@section('title', 'Rules, GST & payments')

@section('content')
<h1 class="mb-4 text-xl font-semibold">Rules, GST &amp; payments</h1>

<form method="post" action="{{ route('admin.settings.update') }}" class="max-w-3xl space-y-4">
    @csrf @method('PUT')

    <section class="card card-body">
        <h2 class="mb-3 font-semibold">Pricing rules</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">GST %</label>
                <input class="input" type="number" step="0.01" name="gst_pct" value="{{ old('gst_pct', $settings->gst_pct) }}" required>
                <p class="mt-1 text-xs text-slate-500">CGST + SGST within the company's state, IGST otherwise.</p>
            </div>
            <div>
                <label class="label">Quote validity (days)</label>
                <input class="input" type="number" name="valid_days" value="{{ old('valid_days', $settings->valid_days) }}" required>
            </div>
            <div>
                <label class="label">Minimum charge per item (₹)</label>
                <input class="input" type="number" step="0.01" name="min_item" value="{{ old('min_item', $settings->min_item) }}" required>
                <p class="mt-1 text-xs text-slate-500">Each product can override this or switch it off.</p>
            </div>
            <div>
                <label class="label">Minimum charge per job (₹)</label>
                <input class="input" type="number" step="0.01" name="min_job" value="{{ old('min_job', $settings->min_job) }}" required>
                <p class="mt-1 text-xs text-slate-500">Taxable value is raised to this after discount and visit charge.</p>
            </div>
            <div>
                <label class="label">Site visit charge (₹)</label>
                <input class="input" type="number" step="0.01" name="visit_charge" value="{{ old('visit_charge', $settings->visit_charge) }}" required>
                <p class="mt-1 text-xs text-slate-500">Added to customer estimates and new quotes.</p>
            </div>
            <div></div>
            <div>
                <label class="label">Sales discount limit (%)</label>
                <input class="input" type="number" step="0.01" name="discount_limit_sales" value="{{ old('discount_limit_sales', $settings->discount_limit_sales) }}" required>
                <p class="mt-1 text-xs text-slate-500">Higher discounts need admin approval. Technicians need approval for any discount.</p>
            </div>
            <div>
                <label class="label">Admin discount limit (%)</label>
                <input class="input" type="number" step="0.01" name="discount_limit_admin" value="{{ old('discount_limit_admin', $settings->discount_limit_admin) }}" required>
            </div>
        </div>
    </section>

    <section class="card card-body" id="payments">
        <h2 class="mb-3 font-semibold">Online payments</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="allow_advance" value="1" class="mt-1" @checked(old('allow_advance', $settings->allow_advance))>
                <span><span class="font-medium">Allow advance payment</span><br><span class="text-xs text-slate-500">Customer pays part now, balance after the work.</span></span>
            </label>
            <div>
                <label class="label">Advance %</label>
                <input class="input" type="number" step="0.01" min="0" max="100" name="advance_pct" value="{{ old('advance_pct', $settings->advance_pct) }}" required>
            </div>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="allow_full_payment" value="1" class="mt-1" @checked(old('allow_full_payment', $settings->allow_full_payment))>
                <span><span class="font-medium">Allow full payment upfront</span></span>
            </label>
        </div>

        <div class="mt-4 rounded-lg border p-4 text-sm {{ $gateway === 'razorpay' ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
            @if ($gateway === 'razorpay')
                <div class="font-medium text-emerald-900">Razorpay is connected {{ $razorpay['test_mode'] ? '(TEST mode)' : '(LIVE mode)' }} · key {{ $razorpay['key_id'] }}</div>
                <div class="mt-1 text-emerald-900">Webhook: {{ $razorpay['webhook'] ? 'secret set' : 'no secret set — add RAZORPAY_WEBHOOK_SECRET' }}</div>
            @else
                <div class="font-medium text-amber-900">Using the built-in test gateway: customers see a simulated checkout and no money moves.</div>
                <div class="mt-1 text-amber-900">To accept real payments (or test with Razorpay's own test mode), add your keys to <code>.env</code>:</div>
                <pre class="mt-2 overflow-x-auto rounded bg-white p-2 text-xs">PAYMENT_GATEWAY=razorpay
RAZORPAY_KEY_ID=rzp_test_xxxxxxxxxxxxxx
RAZORPAY_KEY_SECRET=xxxxxxxxxxxxxxxxxxxxxxxx
RAZORPAY_WEBHOOK_SECRET=choose-a-secret</pre>
            @endif
            <div class="mt-2 text-xs text-slate-600">Webhook URL for the Razorpay dashboard (events: payment.captured, payment.failed, order.paid):<br><code class="break-all">{{ $razorpay['webhook_url'] }}</code></div>
        </div>
    </section>

    <section class="card card-body">
        <h2 class="mb-3 font-semibold">Automation &amp; website</h2>
        <div class="space-y-3 text-sm">
            <label class="flex items-start gap-2">
                <input type="checkbox" name="auto_job_on_payment" value="1" class="mt-1" @checked(old('auto_job_on_payment', $settings->auto_job_on_payment))>
                <span><span class="font-medium">Create the job automatically when the customer pays</span><br><span class="text-xs text-slate-500">The visiting technician is assigned by default.</span></span>
            </label>
            <label class="flex items-start gap-2">
                <input type="checkbox" name="auto_invoice_on_done" value="1" class="mt-1" @checked(old('auto_invoice_on_done', $settings->auto_invoice_on_done))>
                <span><span class="font-medium">Issue the tax invoice automatically when a job is marked Done</span></span>
            </label>
            <label class="flex items-start gap-2">
                <input type="checkbox" name="portal_enabled" value="1" class="mt-1" @checked(old('portal_enabled', $settings->portal_enabled))>
                <span><span class="font-medium">Accept new requests from the website</span></span>
            </label>
            <div>
                <label class="label">Note shown with customer estimates</label>
                <textarea class="input" name="estimate_note" rows="2">{{ old('estimate_note', $settings->estimate_note) }}</textarea>
            </div>
        </div>
    </section>

    <p class="text-xs text-slate-500">Saved quotes keep the rules they were created with.</p>
    <button class="btn btn-primary">Save settings</button>
</form>
@endsection
