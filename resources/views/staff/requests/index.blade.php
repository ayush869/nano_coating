@extends('layouts.staff')
@section('title', auth()->user()->role === 'technician' ? 'My visits' : 'Service requests')
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold">{{ auth()->user()->role === 'technician' ? 'My visits' : 'Service requests' }}</h1>
    <form class="flex flex-wrap gap-2">
        <input class="input w-56" type="search" name="q" value="{{ $term }}" placeholder="Number, name or phone">
        <select class="input w-auto" name="status" onchange="this.form.submit()">
            <option value="">Open (new + scheduled)</option>
            @foreach (\App\Models\ServiceRequest::STATUSES as $k => $v)
                <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card overflow-hidden">
    <ul class="divide-y divide-slate-100">
        @forelse ($requests as $r)
            <li>
                <a href="{{ route('requests.show', $r) }}" class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-slate-50">
                    <div class="w-20 shrink-0 text-center">
                        @if ($r->visit_at)
                            <div class="text-xs text-slate-500 uppercase">{{ $r->visit_at->format('D') }}</div>
                            <div class="text-lg leading-tight font-semibold">{{ $r->visit_at->format('d M') }}</div>
                            <div class="text-xs text-slate-500">{{ $r->visit_at->format('h:i A') }}</div>
                        @else
                            <div class="text-xs text-slate-400">{{ $r->preferred_date ? 'Wants '.$r->preferred_date->format('d M') : 'No date' }}</div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $r->customer->name }} <span class="text-sm font-normal text-slate-500">· {{ $r->customer->phone }}</span></div>
                        <div class="truncate text-sm text-slate-500">{{ $r->site->fullAddress() }}</div>
                        <div class="text-xs text-slate-500">{{ $r->number }} · {{ $r->created_at->diffForHumans() }} · {{ $r->technician?->name ?? 'No technician yet' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium">{{ P::money($r->estimate_total) }}</div>
                        <div class="text-[11px] text-slate-500">customer estimate</div>
                        @include('partials.status', ['status' => $r->status])
                    </div>
                </a>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-slate-500">No requests here.</li>
        @endforelse
    </ul>
</div>
<div class="mt-4">{{ $requests->links() }}</div>
@endsection
