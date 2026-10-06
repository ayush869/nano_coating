@extends('layouts.staff')
@section('title', 'Reports')
@php
    use App\Models\Quote;
    use App\Services\Pricing\PricingService as P;
    $maxCoating = max(1, $byCoating->max('value'));
    $maxType = max(1, $byService->max('n'));
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold">Reports</h1>
        <p class="text-sm text-slate-500">Latest revision of each quote, dated {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</p>
    </div>
    <form class="flex flex-wrap items-end gap-2">
        <div><label class="label">From</label><input class="input" type="date" name="from" value="{{ $from->toDateString() }}"></div>
        <div><label class="label">To</label><input class="input" type="date" name="to" value="{{ $to->toDateString() }}"></div>
        <button class="btn">Apply</button>
    </form>
</div>

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="card card-body">
        <div class="text-xs text-slate-500">Quotes</div>
        <div class="text-2xl font-semibold">{{ $quoteCount }}</div>
    </div>
    <div class="card card-body">
        <div class="text-xs text-slate-500">Conversion rate</div>
        <div class="text-2xl font-semibold">{{ $conversion === null ? '—' : P::num($conversion).'%' }}</div>
        <div class="text-xs text-slate-500">{{ $wonCount }} won of {{ $decided }} sent</div>
    </div>
    <div class="card card-body">
        <div class="text-xs text-slate-500">Average quote value</div>
        <div class="text-2xl font-semibold">{{ P::money($average ?? 0) }}</div>
        <div class="text-xs text-slate-500">excl. drafts, incl. GST</div>
    </div>
    <div class="card card-body">
        <div class="text-xs text-slate-500">Won value</div>
        <div class="text-2xl font-semibold">{{ P::money($wonValue) }}</div>
        <div class="text-xs text-slate-500">accepted, converted, invoiced</div>
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="card">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Quotes by status</h2>
        <table class="table">
            <thead><tr><th>Status</th><th class="text-right">Count</th><th class="text-right">Value</th></tr></thead>
            <tbody>
                @foreach (Quote::STATUSES as $s => $label)
                    @continue($s === Quote::SUPERSEDED)
                    <tr>
                        <td>@include('partials.status', ['status' => $s])</td>
                        <td class="text-right">{{ $byStatus[$s]->n ?? 0 }}</td>
                        <td class="text-right">{{ P::money($byStatus[$s]->value ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Revenue by coating <span class="text-xs font-normal text-slate-500">(won quotes, line value before discount &amp; GST)</span></h2>
        <div class="space-y-3 p-4">
            @forelse ($byCoating as $row)
                <div>
                    <div class="flex justify-between text-sm"><span>{{ $row->label }}</span><span class="font-medium">{{ P::money($row->value) }}</span></div>
                    <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-brand-600" style="width: {{ $row->value / $maxCoating * 100 }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No won quotes in this period.</p>
            @endforelse
        </div>
    </div>

    <div class="card">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Items by service</h2>
        <div class="space-y-3 p-4">
            @forelse ($byService as $row)
                <div>
                    <div class="flex justify-between text-sm">
                        <span>{{ $row->label }}</span>
                        <span><span class="font-medium">{{ $row->n }}</span> <span class="text-slate-500">· {{ P::money($row->value) }}</span></span>
                    </div>
                    <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-sky-600" style="width: {{ $row->n / $maxType * 100 }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No items in this period.</p>
            @endforelse
        </div>
    </div>

    <div class="card">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Top items</h2>
        <table class="table">
            <thead><tr><th>Item</th><th class="text-right">Lines</th><th class="text-right">Value</th></tr></thead>
            <tbody>
                @forelse ($topItems as $row)
                    <tr><td>{{ $row->label }}</td><td class="text-right">{{ $row->n }}</td><td class="text-right">{{ P::money($row->value) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-slate-500">No items.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
