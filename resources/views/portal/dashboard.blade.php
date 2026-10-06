@extends('layouts.site')
@section('title', 'My account')
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Hello, {{ strtok($customer->name, ' ') }}</h1>
            <p class="text-sm text-slate-500">+91 {{ $customer->phone }} · <a class="link" href="{{ route('portal.profile') }}">Profile</a></p>
        </div>
        <a href="{{ route('portal.requests.create') }}" class="btn btn-primary">+ New request</a>
    </div>

    {{-- Things to do --}}
    @php
        $toApprove = $quotes->where('status', 'sent');
        $toPay = $quotes->filter(fn ($q) => in_array($q->status, ['accepted', 'converted', 'invoiced']) && $q->balance() > 0);
    @endphp
    @if ($toApprove->isNotEmpty() || $toPay->isNotEmpty())
        <div class="mt-6 space-y-3">
            @foreach ($toApprove as $q)
                <a href="{{ route('portal.quotes.show', $q) }}" class="flex items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 hover:border-sky-400">
                    <div><div class="font-semibold text-sky-900">Your quotation is ready</div><div class="text-sm text-sky-800">{{ $q->display_number }} · valid till {{ $q->valid_till->format('d M') }}</div></div>
                    <div class="text-right"><div class="font-semibold">{{ P::money($q->total) }}</div><span class="btn btn-primary btn-sm mt-1">Review &amp; pay</span></div>
                </a>
            @endforeach
            @foreach ($toPay as $q)
                <a href="{{ route('portal.quotes.show', $q) }}" class="flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 hover:border-amber-400">
                    <div><div class="font-semibold text-amber-900">{{ $q->amount_paid > 0 ? 'Balance due' : 'Payment due' }}</div><div class="text-sm text-amber-800">{{ $q->display_number }}</div></div>
                    <div class="text-right"><div class="font-semibold">{{ P::money($q->balance()) }}</div><span class="btn btn-primary btn-sm mt-1">Pay now</span></div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card">
            <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">My requests</h2>
            <ul class="divide-y divide-slate-100">
                @forelse ($requests as $r)
                    <li><a href="{{ route('portal.requests.show', $r) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                        <div>
                            <div class="font-medium">{{ $r->number }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $r->created_at->format('d M Y') }} ·
                                @if ($r->visit_at) visit {{ $r->visit_at->format('d M, h:i A') }} @else estimate {{ P::money($r->estimate_total) }} @endif
                            </div>
                        </div>
                        @include('partials.status', ['status' => $r->status])
                    </a></li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-slate-500">
                        No requests yet.<br><a href="{{ route('portal.requests.create') }}" class="link">Get your first estimate</a>
                    </li>
                @endforelse
            </ul>
        </section>

        <section class="card">
            <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Quotations &amp; invoices</h2>
            <ul class="divide-y divide-slate-100">
                @forelse ($quotes as $q)
                    <li><a href="{{ route('portal.quotes.show', $q) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                        <div>
                            <div class="font-medium">{{ $q->display_number }}</div>
                            <div class="text-xs text-slate-500">{{ $q->date->format('d M Y') }}{{ $q->invoice ? ' · invoice '.$q->invoice->number : '' }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold">{{ P::money($q->total) }}</div>
                            @include('partials.status', ['status' => $q->status, 'context' => 'customer'])
                        </div>
                    </a></li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-slate-500">Quotations appear here after our site visit.</li>
                @endforelse
            </ul>
        </section>
    </div>

    @if ($payments->isNotEmpty())
        <section class="card mt-6">
            <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Payments</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($payments as $p)
                    <li class="flex items-center justify-between px-4 py-3">
                        <span>{{ $p->paid_at->format('d M Y') }} · {{ $p->purpose_label }} · {{ $p->quote->display_number }}</span>
                        <span class="flex items-center gap-3"><span class="font-medium">{{ P::money($p->amount) }}</span>
                            <a class="link" target="_blank" href="{{ route('portal.payments.receipt', $p) }}">Receipt</a></span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
