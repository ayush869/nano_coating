@extends('layouts.staff')
@section('title', $customer->name)
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold">{{ $customer->name }}</h1>
        <div class="text-sm text-slate-500">
            <a href="tel:{{ $customer->phone }}" class="link">{{ $customer->phone }}</a>
            {{ $customer->email ? ' · '.$customer->email : '' }}{{ $customer->gstin ? ' · GSTIN '.$customer->gstin : '' }}
        </div>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('customers.edit', $customer) }}" class="btn">Edit</a>
        <a href="{{ route('quotes.create', ['customer_id' => $customer->id]) }}" class="btn btn-primary">+ New quote</a>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-2">
    <div class="card card-body">
        <h2 class="mb-3 font-semibold">Sites</h2>
        <ul class="space-y-3">
            @foreach ($customer->sites as $site)
                <li x-data="{ edit: false }" class="rounded-lg border border-slate-200 p-3 text-sm">
                    <div class="flex justify-between gap-2" x-show="!edit">
                        <div>
                            <div class="font-medium">{{ $site->label ?: 'Site' }}</div>
                            <div class="text-slate-600">{{ $site->fullAddress() }}</div>
                        </div>
                        <button class="btn btn-sm" @click="edit = true">Edit</button>
                    </div>
                    <form x-cloak x-show="edit" method="post" action="{{ route('sites.update', $site) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf @method('PUT')
                        @include('staff.customers._site_fields', ['site' => $site])
                        <div class="flex gap-2 sm:col-span-2">
                            <button class="btn btn-primary btn-sm">Save site</button>
                            <button type="button" class="btn btn-sm" @click="edit = false">Cancel</button>
                        </div>
                    </form>
                </li>
            @endforeach
        </ul>
        <details class="mt-3">
            <summary class="link cursor-pointer text-sm">+ Add site</summary>
            <form method="post" action="{{ route('customers.sites.store', $customer) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                @include('staff.customers._site_fields', ['site' => null])
                <div class="sm:col-span-2"><button class="btn btn-primary btn-sm">Add site</button></div>
            </form>
        </details>
        @if ($customer->notes)
            <div class="label mt-4">Notes</div>
            <p class="text-sm whitespace-pre-line">{{ $customer->notes }}</p>
        @endif
    </div>

    <div class="card">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Quotes</h2>
        <ul class="divide-y divide-slate-100">
            @forelse ($customer->quotes as $q)
                <li>
                    <a href="{{ route('quotes.show', $q) }}" class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50">
                        <span><span class="font-mono">{{ $q->display_number }}</span> <span class="text-slate-500">· {{ $q->date->format('d M Y') }}</span></span>
                        <span class="flex items-center gap-2">{{ P::money($q->total) }} @include('partials.status', ['status' => $q->status])</span>
                    </a>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-slate-500">No quotes yet.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
