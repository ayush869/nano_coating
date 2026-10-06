@extends('layouts.site')
@section('title', $sr->number)
@php
    use App\Models\QuoteLine;
    use App\Models\ServiceRequest;
    use App\Services\Pricing\PricingService as P;
    $stages = [
        ['Request received', true, $sr->created_at->format('d M, h:i A')],
        ['Site visit scheduled', (bool) $sr->visit_at, $sr->visit_at?->format('D d M, h:i A').($sr->technician ? ' · '.$sr->technician->name : '')],
        ['Quotation ready', in_array($sr->status, [ServiceRequest::QUOTED, ServiceRequest::CLOSED]) && $sr->latestQuote, $sr->latestQuote?->display_number],
    ];
@endphp

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <a href="{{ route('portal.dashboard') }}" class="text-sm text-brand-700">← My account</a>
    <div class="mt-2 flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-bold">{{ $sr->number }}</h1>
        @include('partials.status', ['status' => $sr->status])
    </div>

    @if ($sr->status !== ServiceRequest::CANCELLED)
        <ol class="card card-body mt-5 space-y-3">
            @foreach ($stages as [$label, $done, $detail])
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-bold {{ $done ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500' }}">{{ $done ? '✓' : $loop->iteration }}</span>
                    <div>
                        <div class="font-medium {{ $done ? '' : 'text-slate-500' }}">{{ $label }}</div>
                        @if ($done && $detail)<div class="text-sm text-slate-500">{{ $detail }}</div>@endif
                    </div>
                </li>
            @endforeach
        </ol>
    @else
        <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Cancelled: {{ $sr->cancel_reason }}</p>
    @endif

    @if ($sr->latestQuote && in_array($sr->latestQuote->status, \App\Models\Quote::CUSTOMER_VISIBLE))
        <a href="{{ route('portal.quotes.show', $sr->latestQuote) }}" class="mt-4 flex items-center justify-between rounded-xl border border-sky-200 bg-sky-50 p-4">
            <span class="font-semibold text-sky-900">View quotation {{ $sr->latestQuote->display_number }}</span>
            <span class="font-semibold">{{ P::money($sr->latestQuote->total) }} →</span>
        </a>
    @endif

    <div class="card mt-5">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h2 class="font-semibold">Your estimate</h2>
            <span class="font-semibold">{{ P::money($sr->estimate_total) }}</span>
        </div>
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach ($sr->items as $item)
                <li class="px-4 py-3">
                    <div class="flex justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $item->name ? $item->name.' · ' : '' }}{{ $item->product_name }} @if ($item->qty != 1)× {{ P::num($item->qty) }}@endif</div>
                            <div class="text-xs text-slate-500">{{ QuoteLine::summariseInputs($item->product_snapshot['fields'] ?? [], $item->inputs ?? []) }}</div>
                        </div>
                        <span>{{ P::money($item->estimate_amount) }}</span>
                    </div>
                    @if (! empty($item->photos))
                        <div class="mt-2 flex gap-2">
                            @foreach ($item->photos as $photo)<img src="{{ $photoUrl($photo) }}" class="h-14 w-14 rounded border object-cover" alt="">@endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="border-t border-slate-100 px-4 py-3 text-sm text-slate-600">
            <div>Address: {{ $sr->site->fullAddress() }}</div>
            <div>Preferred: {{ $sr->preferred_date?->format('D d M') ?? 'any day' }}, {{ ServiceRequest::SLOTS[$sr->preferred_slot] ?? 'any time' }}</div>
        </div>
    </div>

    @if ($sr->isOpen())
        <details class="mt-4">
            <summary class="cursor-pointer text-sm text-red-700">Cancel this request</summary>
            <form method="post" action="{{ route('portal.requests.cancel', $sr) }}" class="mt-2 flex gap-2" onsubmit="return confirm('Cancel this request?')">
                @csrf
                <input class="input" name="reason" placeholder="Reason (optional)">
                <button class="btn btn-danger">Cancel request</button>
            </form>
        </details>
    @endif
</div>
@endsection
