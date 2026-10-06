@extends('layouts.site')
@section('title', 'Quotation '.$quote->display_number)
@php
    use App\Models\Quote;
    use App\Services\Pricing\PricingService as P;
    $half = P::num($quote->gst_pct / 2);
    $expired = $quote->status === Quote::SENT && $quote->valid_till->copy()->endOfDay()->isPast();
@endphp

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <a href="{{ route('portal.dashboard') }}" class="text-sm text-brand-700">← My account</a>
    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold">Quotation {{ $quote->display_number }}</h1>
                @include('partials.status', ['status' => $quote->status, 'context' => 'customer'])
            </div>
            <p class="text-sm text-slate-500">{{ $quote->date->format('d M Y') }} · valid till {{ $quote->valid_till->format('d M Y') }}</p>
        </div>
        <a href="{{ route('portal.quotes.pdf', $quote) }}" target="_blank" class="btn">Download PDF</a>
    </div>

    {{-- Decision / payment panel --}}
    <div class="card mt-5 p-5" x-data="checkout(@js(['startUrl' => route('portal.payments.start', $quote), 'csrf' => csrf_token()]))">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <div class="text-sm text-slate-500">Total incl. GST</div>
                <div class="text-3xl font-bold text-brand-800">{{ P::money($quote->total) }}</div>
                @if ($quote->amount_paid > 0)
                    <div class="text-sm text-emerald-700">Paid {{ P::money($quote->amount_paid) }} · balance {{ P::money($quote->balance()) }}</div>
                @endif
            </div>
            @if ($quote->total > 0)
                <div class="w-full sm:w-48">
                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ min(100, $quote->amount_paid / $quote->total * 100) }}%"></div></div>
                    <div class="mt-1 text-right text-xs text-slate-500">{{ $quote->paymentStatusLabel() }}</div>
                </div>
            @endif
        </div>

        @if ($expired)
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">This quotation has expired. Please contact us for an updated one.</p>
        @elseif ($quote->status === Quote::REJECTED)
            <p class="mt-4 text-sm text-slate-600">You declined this quotation. Changed your mind? Just call or WhatsApp us.</p>
        @else
            @if ($options)
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach ($options as $purpose => $amount)
                        <button type="button" class="rounded-xl border-2 p-4 text-left transition hover:border-brand-600 {{ $loop->first ? 'border-brand-600 bg-brand-50' : 'border-slate-200' }}"
                                :disabled="busy" @click="pay('{{ $purpose }}')">
                            <div class="text-sm font-medium text-slate-600">
                                {{ ['advance' => 'Pay advance ('.P::num($quote->advance_pct).'%)', 'full' => $quote->status === Quote::SENT ? 'Pay in full' : 'Pay in full', 'balance' => 'Pay balance'][$purpose] }}
                            </div>
                            <div class="text-2xl font-bold">{{ P::money($amount) }}</div>
                            <div class="text-xs text-slate-500">
                                @if ($quote->status === Quote::SENT) Accepts the quotation and confirms your booking. @else UPI, cards, net banking, wallets. @endif
                            </div>
                        </button>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-red-700" x-show="error" x-text="error"></p>
                <p class="mt-2 text-sm text-slate-500" x-show="busy">Opening secure checkout…</p>
                @if ($gateway === 'test')
                    <p class="mt-3 text-xs text-amber-700">Test mode: payments are simulated and no money is taken.</p>
                @else
                    <p class="mt-3 text-xs text-slate-500">Secure payments by Razorpay.</p>
                @endif
            @endif

            @if ($quote->status === Quote::SENT)
                <div class="mt-5 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                    <form method="post" action="{{ route('portal.quotes.accept', $quote) }}" class="space-y-2">
                        @csrf
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="agree" value="1" class="mt-1" required> I accept the quotation and the terms below</label>
                        <button class="btn w-full">Accept, I'll pay later</button>
                    </form>
                    <form method="post" action="{{ route('portal.quotes.reject', $quote) }}" class="space-y-2" x-data="{ open: false }">
                        @csrf
                        <button type="button" class="btn btn-danger w-full" @click="open = !open" x-show="!open">Decline</button>
                        <div x-show="open" x-cloak class="space-y-2">
                            <input class="input" name="reason" placeholder="Tell us why (optional)">
                            <button class="btn btn-danger w-full">Decline quotation</button>
                        </div>
                    </form>
                </div>
            @elseif ($quote->status === Quote::ACCEPTED && ! $options)
                <p class="mt-4 text-sm text-emerald-700">Accepted. We'll call you to schedule the work.</p>
            @endif

            @if ($quote->serviceJob)
                <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm">
                    Work {{ $quote->serviceJob->status === 'done' ? 'completed' : 'scheduled' }}
                    @if ($quote->serviceJob->scheduled_on && $quote->serviceJob->status !== 'done') for <b>{{ $quote->serviceJob->scheduled_on->format('D d M Y') }}</b>@endif
                    @if ($quote->serviceJob->technician) · {{ $quote->serviceJob->technician->name }}@endif
                </p>
            @endif
        @endif

        @if ($quote->invoice)
            <a href="{{ route('portal.invoices.pdf', $quote->invoice) }}" target="_blank" class="btn btn-primary mt-4">Download tax invoice {{ $quote->invoice->number }}</a>
        @endif
    </div>

    {{-- Items --}}
    <div class="card mt-5">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">What's included</h2>
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach ($quote->lines as $line)
                <li class="flex justify-between gap-3 px-4 py-3">
                    <div>
                        <div class="font-medium">{{ $line->name }} @if ($line->qty != 1)<span class="text-slate-500">× {{ P::num($line->qty) }}</span>@endif</div>
                        <div class="text-xs text-slate-500">{{ $line->specification() }}</div>
                        <div class="text-xs text-slate-500">{{ $line->area_text }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-medium">{{ P::money($line->amount) }}</div>
                        @if ($line->min_applied)<div class="text-[11px] text-slate-500">minimum charge</div>@endif
                    </div>
                </li>
            @endforeach
        </ul>
        <dl class="space-y-1 border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
            <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ P::money($quote->subtotal) }}</dd></div>
            @if ($quote->discount_amount > 0)<div class="flex justify-between text-emerald-700"><dt>Discount</dt><dd>- {{ P::money($quote->discount_amount) }}</dd></div>@endif
            @if ($quote->visit_charge > 0)<div class="flex justify-between"><dt>Site visit</dt><dd>{{ P::money($quote->visit_charge) }}</dd></div>@endif
            @if ($quote->min_job_adjustment > 0)<div class="flex justify-between"><dt>Minimum job charge adjustment</dt><dd>{{ P::money($quote->min_job_adjustment) }}</dd></div>@endif
            @if ($quote->intra_state)
                <div class="flex justify-between"><dt>CGST {{ $half }}%</dt><dd>{{ P::money($quote->cgst) }}</dd></div>
                <div class="flex justify-between"><dt>SGST {{ $half }}%</dt><dd>{{ P::money($quote->sgst) }}</dd></div>
            @else
                <div class="flex justify-between"><dt>IGST {{ P::num($quote->gst_pct) }}%</dt><dd>{{ P::money($quote->igst) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-slate-300 pt-1 text-base font-semibold"><dt>Total</dt><dd>{{ P::money($quote->total) }}</dd></div>
        </dl>
    </div>

    @if ($quote->payments->where('status', 'paid')->isNotEmpty())
        <div class="card mt-5">
            <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Payments</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($quote->payments->where('status', 'paid') as $p)
                    <li class="flex items-center justify-between px-4 py-3">
                        <span>{{ $p->paid_at->format('d M Y') }} · {{ $p->purpose_label }}</span>
                        <span class="flex items-center gap-3"><b>{{ P::money($p->amount) }}</b><a class="link" target="_blank" href="{{ route('portal.payments.receipt', $p) }}">Receipt</a></span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($company->terms)
        <div class="mt-5 text-xs whitespace-pre-line text-slate-500"><b>Terms</b><br>{{ $company->terms }}</div>
    @endif
</div>
@endsection
