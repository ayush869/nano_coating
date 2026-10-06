@extends('layouts.staff')
@section('title', 'Products & formulas')
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold">Products &amp; formulas</h1>
        <p class="text-sm text-slate-500">Each product has its own inputs and formulas. Changes apply to new quotes only.</p>
    </div>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">+ New product</a>
</div>

<form class="mb-4 flex flex-wrap gap-2">
    <input class="input max-w-xs" type="search" name="q" value="{{ $q }}" placeholder="Search products">
    <select class="input w-auto" name="service" onchange="this.form.submit()">
        <option value="">All services</option>
        @foreach ($allServices as $s)<option value="{{ $s->id }}" @selected($serviceId == $s->id)>{{ $s->name }}</option>@endforeach
    </select>
</form>

<div class="space-y-5">
    @foreach ($services as $service)
        @continue($service->products->isEmpty() && $q)
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-2.5">
                <h2 class="font-semibold">{{ $service->icon }} {{ $service->name }} @unless ($service->active)<span class="badge bg-slate-200 text-slate-600">hidden</span>@endunless</h2>
                <a class="link text-sm" href="{{ route('admin.products.create', ['service' => $service->id]) }}">+ Add product</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($service->products as $p)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $p->active ? '' : 'opacity-60' }}">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">
                                {{ $p->name }}
                                @unless ($p->active)<span class="badge bg-slate-200 text-slate-600">inactive</span>@endunless
                                @unless ($p->customer_visible)<span class="badge bg-sky-100 text-sky-800">staff only</span>@endunless
                            </div>
                            <div class="mt-0.5 font-mono text-[11px] text-slate-500">
                                {{ $p->measure_unit }} = {{ $p->area_formula }}
                                · price = {{ $p->price_formula }}
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ count($p->fields ?? []) }} input(s), {{ count($p->constants ?? []) }} constant(s) ·
                                {{ $p->uses_coating ? ($p->coatings->pluck('name')->implode(', ') ?: 'any coating') : 'fixed rate '.P::money($p->base_rate, false).'/'.$p->measure_unit }}
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <form method="post" action="{{ route('admin.products.duplicate', $p) }}">@csrf<button class="btn btn-sm" title="Make a variant">Duplicate</button></form>
                            <a class="btn btn-sm" href="{{ route('admin.products.edit', $p) }}">Edit</a>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-4 text-sm text-slate-500">No products yet.</li>
                @endforelse
            </ul>
        </section>
    @endforeach
</div>
@endsection
