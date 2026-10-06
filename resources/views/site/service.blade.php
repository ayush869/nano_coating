@extends('layouts.site')
@section('title', $service->name)
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<section class="bg-gradient-to-b from-brand-50 to-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-10">
        <a href="{{ route('home') }}#services" class="text-sm text-brand-700">← All services</a>
        <div class="mt-2 flex items-center gap-3">
            <span class="text-4xl">{{ $service->icon }}</span>
            <h1 class="text-3xl font-bold">{{ $service->name }}</h1>
        </div>
        <p class="mt-2 max-w-2xl text-slate-600">{{ $service->description ?: $service->summary }}</p>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-8">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($products as $row)
            @php $p = $row['product']; @endphp
            <div class="card flex flex-col overflow-hidden">
                @if ($row['image'])
                    <img src="{{ $row['image'] }}" alt="" class="h-40 w-full object-cover">
                @endif
                <div class="flex flex-1 flex-col p-5">
                    <h2 class="font-semibold">{{ $p->name }}</h2>
                    <p class="mt-1 flex-1 text-sm text-slate-600">{{ $p->description }}</p>
                    @if ($row['typical'])
                        <div class="mt-3 text-sm">
                            <span class="text-slate-500">Typically</span>
                            <span class="text-lg font-semibold text-brand-800">{{ P::money($row['typical']['amount'], false) }}</span>
                            <div class="text-xs text-slate-500">for {{ $row['typical']['area'] }}, before GST</div>
                        </div>
                    @endif
                    <a href="{{ route('portal.requests.create', ['product' => $p->id, 'service' => $service->id]) }}" class="btn btn-primary mt-4">Get my exact estimate</a>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-10">
        <h2 class="text-lg font-semibold">Other services</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($services->where('id', '!=', $service->id) as $s)
                <a href="{{ route('site.service', $s) }}" class="btn">{{ $s->icon }} {{ $s->name }}</a>
            @endforeach
        </div>
    </div>
</section>
@endsection
