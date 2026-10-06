@extends('layouts.staff')
@section('title', 'Home')
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ strtok(auth()->user()->name, ' ') }}</h1>
    <a href="{{ route('quotes.create') }}" class="btn btn-primary">+ New quote</a>
</div>

<div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
    <a href="{{ route('requests.index', ['status' => 'new']) }}" class="card card-body hover:border-brand-600 {{ $newRequests->count() ? 'border-amber-300 bg-amber-50' : '' }}">
        <div class="text-xs text-slate-500">New requests</div>
        <div class="mt-1 text-2xl font-semibold">{{ $newRequests->count() }}</div>
        <div class="text-xs text-slate-500">need a technician</div>
    </a>
    @foreach (['draft' => 'Draft quotes', 'sent' => 'Awaiting customer', 'converted' => 'Jobs in hand'] as $s => $label)
        <a href="{{ route('quotes.index', ['status' => $s]) }}" class="card card-body hover:border-brand-600">
            <div class="text-xs text-slate-500">{{ $label }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $counts[$s]->n ?? 0 }}</div>
            <div class="text-xs text-slate-500">{{ P::money($counts[$s]->value ?? 0) }}</div>
        </a>
    @endforeach
    <a href="{{ route('payments.index') }}" class="card card-body hover:border-brand-600">
        <div class="text-xs text-slate-500">Collected this month</div>
        <div class="mt-1 text-xl font-semibold text-emerald-700">{{ P::money($collected) }}</div>
    </a>
    <div class="card card-body">
        <div class="text-xs text-slate-500">Outstanding on jobs</div>
        <div class="mt-1 text-xl font-semibold text-amber-700">{{ P::money($outstanding) }}</div>
    </div>
</div>

@if ($newRequests->isNotEmpty())
    <div class="card mt-5 border-amber-200">
        <div class="flex items-center justify-between border-b border-amber-100 px-4 py-3">
            <h2 class="font-semibold text-amber-900">New requests from the website</h2>
            <a href="{{ route('requests.index') }}" class="link text-sm">All requests</a>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach ($newRequests as $r)
                <li><a href="{{ route('requests.show', $r) }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-slate-50">
                    <span><span class="font-medium">{{ $r->customer->name }}</span> <span class="text-slate-500">· {{ $r->site->city }} · {{ $r->created_at->diffForHumans() }}</span></span>
                    <span class="flex items-center gap-2"><span>{{ P::money($r->estimate_total) }}</span><span class="btn btn-sm">Assign</span></span>
                </a></li>
            @endforeach
        </ul>
    </div>
@endif

@if ($awaitingApproval->isNotEmpty())
    <div class="card mt-5 border-amber-200">
        <div class="card-body">
            <h2 class="mb-2 font-semibold text-amber-800">Discounts waiting for approval</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($awaitingApproval as $q)
                    <li class="flex items-center justify-between py-2">
                        <a class="link" href="{{ route('quotes.show', $q) }}">{{ $q->display_number }} · {{ $q->customer_snapshot['name'] }}</a>
                        <span>{{ P::num($q->discountPct()) }}% off · {{ P::money($q->total) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="mt-5 grid gap-5 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h2 class="font-semibold">Recent quotes</h2>
            <a href="{{ route('quotes.index') }}" class="link text-sm">All quotes</a>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse ($recent as $q)
                <li>
                    <a href="{{ route('quotes.show', $q) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ $q->customer_snapshot['name'] }}</div>
                            <div class="text-xs text-slate-500">{{ $q->display_number }} · {{ $q->date->format('d M') }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold">{{ P::money($q->total) }}</div>
                            @include('partials.status', ['status' => $q->status])
                        </div>
                    </a>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-slate-500">No quotes yet.</li>
            @endforelse
        </ul>
    </div>
    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h2 class="font-semibold">Upcoming jobs</h2>
            <a href="{{ route('jobs.index') }}" class="link text-sm">All jobs</a>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse ($upcomingJobs as $job)
                <li class="px-4 py-3 text-sm">
                    <a href="{{ route('jobs.show', $job) }}" class="link">{{ $job->quote->customer_snapshot['name'] }}</a>
                    <div class="text-xs text-slate-500">{{ $job->scheduled_on?->format('D d M') ?? 'Not scheduled' }} · {{ $job->technician?->name ?? 'Unassigned' }}</div>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-slate-500">No open jobs.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
