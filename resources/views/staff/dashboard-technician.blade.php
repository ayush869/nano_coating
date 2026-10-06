@extends('layouts.staff')
@section('title', 'Today')
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<h1 class="mb-4 text-xl font-semibold">Hello, {{ strtok(auth()->user()->name, ' ') }}</h1>

<div class="space-y-5">
    <section class="card">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Site visits to measure &amp; quote</h2>
        <ul class="divide-y divide-slate-100">
            @forelse ($visits as $r)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <div class="w-20 shrink-0 text-center">
                        <div class="text-xs text-slate-500 uppercase">{{ $r->visit_at?->format('D') }}</div>
                        <div class="font-semibold">{{ $r->visit_at?->format('d M') }}</div>
                        <div class="text-xs text-slate-500">{{ $r->visit_at?->format('h:i A') }}</div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ $r->customer->name }} · <a class="link" href="tel:{{ $r->customer->phone }}">{{ $r->customer->phone }}</a></div>
                        <div class="text-sm text-slate-500">{{ $r->site->fullAddress() }}</div>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('requests.show', $r) }}" class="btn btn-sm">Details</a>
                        <a href="{{ route('quotes.create', ['request' => $r->id]) }}" class="btn btn-primary btn-sm">Create quote</a>
                    </div>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-slate-500">No visits assigned right now.</li>
            @endforelse
        </ul>
    </section>

    @if ($drafts->isNotEmpty())
        <section class="card">
            <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Draft quotes to send</h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($drafts as $q)
                    <li><a href="{{ route('quotes.show', $q) }}" class="flex justify-between px-4 py-3 text-sm hover:bg-slate-50">
                        <span>{{ $q->display_number }} · {{ $q->customer_snapshot['name'] }}</span><span class="font-medium">{{ P::money($q->total) }}</span>
                    </a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Coating jobs</h2>
        <ul class="divide-y divide-slate-100">
            @forelse ($jobs as $job)
                <li><a href="{{ route('jobs.show', $job) }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-slate-50">
                    <span><span class="font-medium">{{ $job->quote->customer_snapshot['name'] }}</span>
                        <span class="text-slate-500">· {{ $job->scheduled_on?->format('D d M') ?? 'date to be fixed' }}</span></span>
                    @include('partials.status', ['status' => $job->status])
                </a></li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-slate-500">No open jobs.</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
