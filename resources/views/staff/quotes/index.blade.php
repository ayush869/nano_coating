@extends('layouts.staff')
@section('title', 'Quotes')
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold">Quotes</h1>
    <a href="{{ route('quotes.create') }}" class="btn btn-primary">+ New quote</a>
</div>

<form class="mb-4 flex flex-wrap gap-2">
    <input class="input max-w-xs flex-1" type="search" name="q" value="{{ $term }}" placeholder="Number, customer name or phone">
    <select class="input w-auto" name="status" onchange="this.form.submit()">
        <option value="">All current</option>
        @foreach (\App\Models\Quote::STATUSES as $k => $v)
            <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
        @endforeach
    </select>
    <button class="btn">Search</button>
</form>

<div class="card overflow-hidden">
    <ul class="divide-y divide-slate-100">
        @forelse ($quotes as $q)
            <li>
                <a href="{{ route('quotes.show', $q) }}" class="grid grid-cols-[1fr_auto] gap-x-3 px-4 py-3 hover:bg-slate-50 sm:grid-cols-[10rem_1fr_6rem_8rem_7rem] sm:items-center">
                    <span class="font-mono text-sm">{{ $q->display_number }}</span>
                    <span class="order-first col-span-2 truncate font-medium sm:order-none sm:col-span-1">{{ $q->customer_snapshot['name'] }} <span class="text-sm font-normal text-slate-500">· {{ $q->customer_snapshot['phone'] }}</span></span>
                    <span class="hidden text-sm text-slate-500 sm:block">{{ $q->date->format('d M Y') }}</span>
                    <span class="text-right text-sm font-semibold">{{ P::money($q->total) }}</span>
                    <span class="sm:text-right">@include('partials.status', ['status' => $q->status])</span>
                </a>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-slate-500">No quotes found.</li>
        @endforelse
    </ul>
</div>
<div class="mt-4">{{ $quotes->links() }}</div>
@endsection
