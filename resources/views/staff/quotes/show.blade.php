@extends('layouts.staff')
@section('title', $quote->display_number)
@php
    use App\Models\Payment;
    use App\Models\Quote;
    use App\Services\Pricing\PricingService as P;
    use App\Support\Media;
    $c = $quote->customer_snapshot;
    $half = P::num($quote->gst_pct / 2);
    $staff = auth()->user()->hasRole('admin', 'sales');
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold">{{ $quote->display_number }}</h1>
            @include('partials.status', ['status' => $quote->status])
            @if ($quote->amount_paid > 0)
                <span class="badge {{ $quote->isFullyPaid() ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800' }}">{{ $quote->paymentStatusLabel() }}</span>
            @endif
            @if ($quote->awaitingApproval())
                <span class="badge bg-amber-100 text-amber-800">Discount needs approval</span>
            @endif
        </div>
        <div class="mt-1 text-sm text-slate-500">
            {{ $quote->date->format('d M Y') }} · valid till {{ $quote->valid_till->format('d M Y') }} · by {{ $quote->creator->name }}
            @if ($quote->serviceRequest) · from visit <a class="link" href="{{ route('requests.show', $quote->serviceRequest) }}">{{ $quote->serviceRequest->number }}</a>@endif
            @if ($quote->viewed_at) · <span class="text-emerald-700">seen by customer {{ $quote->viewed_at->diffForHumans() }}</span>@endif
        </div>
    </div>
    <div class="text-right">
        <div class="text-2xl font-semibold text-brand-800">{{ P::money($quote->total) }}</div>
        <div class="text-xs text-slate-500">incl. GST @if ($quote->amount_paid > 0) · balance {{ P::money($quote->balance()) }} @endif</div>
    </div>
</div>

@if ($quote->status === Quote::SUPERSEDED && $quote->supersededBy)
    <div class="mb-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm">
        Replaced by <a class="link" href="{{ route('quotes.show', $quote->supersededBy) }}">{{ $quote->supersededBy->display_number }}</a>.
    </div>
@endif
@if ($quote->status === Quote::REJECTED && $quote->rejection_reason)
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Customer's reason: {{ $quote->rejection_reason }}</div>
@endif

{{-- Actions --}}
<div class="card card-body mb-4">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('quotes.pdf', $quote) }}" target="_blank" class="btn">View PDF</a>

        @if ($quote->isEditable())
            <a href="{{ route('quotes.edit', $quote) }}" class="btn">Edit</a>
        @endif

        @if (in_array($quote->status, [Quote::DRAFT, Quote::SENT]) && ! $quote->awaitingApproval())
            <a href="{{ route('quotes.whatsapp', $quote) }}" target="_blank" class="btn border-emerald-300 text-emerald-800 hover:bg-emerald-50"
               onclick="setTimeout(() => location.reload(), 1500)">{{ $quote->status === Quote::DRAFT ? 'Send' : 'Resend' }} on WhatsApp</a>
            <div x-data="{ open: false }" class="contents">
                <button type="button" class="btn" @click="open = !open">Email</button>
                <form x-cloak x-show="open" method="post" action="{{ route('quotes.email', $quote) }}" class="flex w-full flex-wrap gap-2">
                    @csrf
                    <input class="input flex-1" type="email" name="to" value="{{ $c['email'] ?? '' }}" placeholder="customer@email.com" required>
                    <input class="input flex-[2]" name="message" placeholder="Optional message">
                    <button class="btn btn-primary">Send email</button>
                </form>
            </div>
        @endif

        @foreach (Quote::TRANSITIONS[$quote->status] ?? [] as $to)
            @if ($quote->canMoveTo($to) && ($staff || $to === Quote::SENT))
                <form method="post" action="{{ route('quotes.status', $quote) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $to }}">
                    <button class="btn {{ $to === Quote::ACCEPTED ? 'btn-primary' : ($to === Quote::REJECTED ? 'btn-danger' : '') }}">
                        {{ ['sent' => 'Mark as sent', 'accepted' => 'Customer accepted', 'rejected' => 'Customer rejected', 'expired' => 'Mark expired'][$to] }}
                    </button>
                </form>
            @endif
        @endforeach

        @if ($quote->canRevise())
            <form method="post" action="{{ route('quotes.revise', $quote) }}" onsubmit="return confirm('Create revision -R{{ $quote->revisions->max('revision') + 1 }}? This quote will be marked superseded.')">
                @csrf
                <button class="btn">Revise</button>
            </form>
        @endif

        @if ($quote->awaitingApproval() && auth()->user()->isAdmin())
            <form method="post" action="{{ route('admin.quotes.approve', $quote) }}">
                @csrf
                <button class="btn btn-primary">Approve {{ P::num($quote->discountPct()) }}% discount</button>
            </form>
        @endif

        @if ($quote->status === Quote::CONVERTED && $staff)
            <form method="post" action="{{ route('invoices.store', $quote) }}" onsubmit="return confirm('Create the tax invoice now?')">
                @csrf
                <button class="btn btn-primary">Create invoice</button>
            </form>
        @endif
        @if ($quote->invoice && $staff)
            <a href="{{ route('invoices.pdf', $quote->invoice) }}" target="_blank" class="btn btn-primary">Invoice {{ $quote->invoice->number }}</a>
        @endif
        @if ($quote->serviceJob)
            <a href="{{ route('jobs.show', $quote->serviceJob) }}" class="btn">Job: {{ $quote->serviceJob->status_label }}</a>
        @endif
    </div>

    @if ($quote->awaitingApproval())
        <p class="mt-3 text-sm text-amber-800">
            The {{ P::num($quote->discountPct()) }}% discount is above the creator's limit. An admin must approve it before the quote can be sent.
        </p>
    @endif

    @if ($quote->status === Quote::ACCEPTED && $staff)
        <form method="post" action="{{ route('quotes.convert', $quote) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-4">
            @csrf
            <div class="text-sm font-medium sm:col-span-4">Convert to job</div>
            <div>
                <label class="label">Technician</label>
                <select class="input" name="technician_id">
                    <option value="">Same as visit / assign later</option>
                    @foreach ($technicians as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                </select>
            </div>
            <div><label class="label">Scheduled on</label><input class="input" type="date" name="scheduled_on"></div>
            <div><label class="label">Notes for technician</label><input class="input" name="notes"></div>
            <div class="flex items-end"><button class="btn btn-primary w-full">Convert to job</button></div>
        </form>
    @endif
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4">
        <div class="card card-body">
            <div class="label">Customer</div>
            @if ($staff)<a href="{{ route('customers.show', $quote->customer_id) }}" class="link">{{ $c['name'] }}</a>@else<div class="font-medium">{{ $c['name'] }}</div>@endif
            <a href="tel:{{ $c['phone'] }}" class="block text-sm">{{ $c['phone'] }}</a>
            @if (!empty($c['email']))<div class="text-sm">{{ $c['email'] }}</div>@endif
            <div class="label mt-3">Site{{ !empty($c['site_label']) ? ' · '.$c['site_label'] : '' }}</div>
            <div class="text-sm">{{ $c['address'] }}</div>
            <div class="mt-2 text-xs text-slate-500">Place of supply {{ $c['state_name'] }} ({{ $quote->place_of_supply }}) · {{ $quote->intra_state ? 'CGST + SGST' : 'IGST' }}</div>
            @if ($quote->notes)
                <div class="label mt-3">Notes</div>
                <div class="text-sm whitespace-pre-line">{{ $quote->notes }}</div>
            @endif
            @if ($quote->revisions->count() > 1)
                <div class="label mt-3">Revisions</div>
                <ul class="text-sm">
                    @foreach ($quote->revisions as $r)
                        <li class="flex justify-between">
                            <a class="link {{ $r->is($quote) ? 'underline' : '' }}" href="{{ route('quotes.show', $r) }}">{{ $r->display_number }}</a>
                            <span class="text-slate-500">{{ P::money($r->total) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Payments --}}
        <div class="card card-body">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-semibold">Payments</h2>
                <span class="text-sm">{{ P::money($quote->amount_paid) }} / {{ P::money($quote->total) }}</span>
            </div>
            <div class="mb-3 h-2 rounded-full bg-slate-100">
                <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $quote->total > 0 ? min(100, $quote->amount_paid / $quote->total * 100) : 0 }}%"></div>
            </div>
            <ul class="space-y-2 text-sm">
                @forelse ($quote->payments as $p)
                    <li class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-medium">{{ P::money($p->amount) }} <span class="font-normal text-slate-500">· {{ $p->purpose_label }}</span></div>
                            <div class="text-xs text-slate-500">
                                {{ $p->gateway_label }}{{ $p->method ? ' · '.strtoupper($p->method) : '' }}{{ $p->reference ? ' · '.$p->reference : '' }}
                                · {{ ($p->paid_at ?? $p->created_at)->format('d M, h:i A') }}
                                @if ($p->recorder) · by {{ $p->recorder->name }} @endif
                            </div>
                            @if ($p->failure_reason)<div class="text-xs text-red-700">{{ $p->failure_reason }}</div>@endif
                        </div>
                        @if ($p->status === Payment::PAID && $staff)
                            <a class="link text-xs" target="_blank" href="{{ route('payments.receipt', $p) }}">{{ $p->number }}</a>
                        @else
                            @include('partials.status', ['status' => $p->status])
                        @endif
                    </li>
                @empty
                    <li class="text-slate-500">No payments yet.
                        @if ($onlineOptions) The customer can pay online from their account. @endif
                    </li>
                @endforelse
            </ul>

            @if ($staff && $quote->balance() > 0 && in_array($quote->status, [Quote::SENT, Quote::ACCEPTED, Quote::CONVERTED, Quote::INVOICED]))
                <details class="mt-3 border-t border-slate-100 pt-3">
                    <summary class="link cursor-pointer text-sm">Record cash / UPI / bank payment</summary>
                    <form method="post" action="{{ route('payments.store', $quote) }}" class="mt-3 grid grid-cols-2 gap-2">
                        @csrf
                        <div><label class="label">Amount</label><input class="input" type="number" step="0.01" min="1" max="{{ $quote->balance() }}" name="amount" value="{{ $quote->amount_paid > 0 ? $quote->balance() : $quote->advanceAmount() }}" required></div>
                        <div>
                            <label class="label">Mode</label>
                            <select class="input" name="mode">@foreach (Payment::OFFLINE as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                        </div>
                        <div class="col-span-2"><label class="label">Reference (UTR / cheque no.)</label><input class="input" name="reference"></div>
                        <div class="col-span-2"><button class="btn btn-primary w-full">Record payment</button></div>
                    </form>
                </details>
            @endif
        </div>
    </div>

    <div class="card lg:col-span-2">
        <ul class="divide-y divide-slate-100">
            @foreach ($quote->lines as $i => $line)
                <li class="px-4 py-3">
                    <div class="flex gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $i + 1 }}. {{ $line->name }} @if ($line->qty != 1)<span class="text-slate-500">× {{ P::num($line->qty) }}</span>@endif</div>
                            <div class="text-xs text-slate-500">{{ $line->specification() }}</div>
                            @if ($summary = $line->inputSummary())<div class="text-xs text-slate-500">{{ $summary }}</div>@endif
                            <div class="text-xs text-slate-600">{{ $line->area_text }}</div>
                            <div class="mt-0.5 font-mono text-[11px] break-words text-slate-500">{{ $line->formula }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold">{{ P::money($line->amount) }}</div>
                            @if ($line->min_applied)<span class="badge bg-amber-100 text-amber-800">Min. item charge</span>@endif
                        </div>
                    </div>
                    @if (! empty($line->photos))
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($line->photos as $photo)
                                <a href="{{ Media::url($photo) }}" target="_blank"><img src="{{ Media::url($photo) }}" class="h-16 w-16 rounded-lg border object-cover" alt=""></a>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
            <dl class="ml-auto max-w-xs space-y-1">
                <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ P::money($quote->subtotal) }}</dd></div>
                @if ($quote->discount_amount > 0)
                    <div class="flex justify-between"><dt>Discount{{ $quote->discount_type === 'percent' ? ' ('.P::num($quote->discount_value).'%)' : '' }}</dt><dd>- {{ P::money($quote->discount_amount) }}</dd></div>
                @endif
                @if ($quote->visit_charge > 0)
                    <div class="flex justify-between"><dt>Site visit charge</dt><dd>{{ P::money($quote->visit_charge) }}</dd></div>
                @endif
                @if ($quote->min_job_adjustment > 0)
                    <div class="flex justify-between text-amber-800"><dt>Minimum job charge adj.</dt><dd>{{ P::money($quote->min_job_adjustment) }}</dd></div>
                @endif
                <div class="flex justify-between font-medium"><dt>Taxable value</dt><dd>{{ P::money($quote->taxable) }}</dd></div>
                @if ($quote->intra_state)
                    <div class="flex justify-between"><dt>CGST {{ $half }}%</dt><dd>{{ P::money($quote->cgst) }}</dd></div>
                    <div class="flex justify-between"><dt>SGST {{ $half }}%</dt><dd>{{ P::money($quote->sgst) }}</dd></div>
                @else
                    <div class="flex justify-between"><dt>IGST {{ P::num($quote->gst_pct) }}%</dt><dd>{{ P::money($quote->igst) }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-slate-300 pt-1 text-base font-semibold"><dt>Total</dt><dd>{{ P::money($quote->total) }}</dd></div>
                @if ($quote->advance_pct > 0 && $quote->amount_paid <= 0)
                    <div class="flex justify-between text-xs text-slate-500"><dt>Advance ({{ P::num($quote->advance_pct) }}%)</dt><dd>{{ P::money($quote->advanceAmount()) }}</dd></div>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection
