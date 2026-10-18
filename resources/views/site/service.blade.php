@extends('layouts.site')
@section('title', $service->name)
@php
    use App\Services\Pricing\PricingService as P;
    use App\Support\Media;
    $banner = Media::url($service->image_path);
@endphp

@section('content')
<section class="relative isolate overflow-hidden {{ $banner ? 'bg-slate-900' : 'bg-gradient-to-b from-brand-50 to-slate-50' }}">
    @if ($banner)
        <img src="{{ $banner }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-50">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/85 to-slate-900/30"></div>
    @endif
    <div class="mx-auto max-w-6xl px-4 py-14 {{ $banner ? 'text-white' : '' }}">
        <a href="{{ route('home') }}#services" class="text-sm {{ $banner ? 'text-white/80 hover:text-white' : 'text-brand-700' }}">← All services</a>
        <h1 class="mt-3 text-4xl font-bold tracking-tight">{{ $service->name }}</h1>
        <p class="mt-3 max-w-2xl text-lg {{ $banner ? 'text-slate-200' : 'text-slate-600' }}">{{ $service->description ?: $service->summary }}</p>
        <a href="{{ route('portal.requests.create', ['service' => $service->id]) }}" class="btn btn-primary mt-6 px-5 py-2.5">Get an estimate</a>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-10">
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($products as $row)
            @php $p = $row['product']; @endphp
            <div class="card group flex flex-col overflow-hidden">
                <div class="aspect-[3/2] overflow-hidden bg-brand-50">
                    @if ($row['image'])
                        <img src="{{ $row['image'] }}" alt="{{ $p->name }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    @else
                        <div class="grid h-full place-items-center text-5xl">{{ $service->icon }}</div>
                    @endif
                </div>
                <div class="flex flex-1 flex-col p-5">
                    <h2 class="text-lg font-semibold">{{ $p->name }}</h2>
                    <p class="mt-1 flex-1 text-sm text-slate-600">{{ $p->description }}</p>
                    @if ($row['typical'])
                        <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm">
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

    @if ($services->where('id', '!=', $service->id)->isNotEmpty())
        <div class="mt-14">
            <h2 class="text-lg font-semibold">Other services</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($services->where('id', '!=', $service->id) as $s)
                    <a href="{{ route('site.service', $s) }}" class="card group overflow-hidden hover:shadow-md">
                        @if ($img = Media::url($s->image_path))
                            <img src="{{ $img }}" alt="" loading="lazy" class="aspect-[3/2] w-full object-cover">
                        @else
                            <div class="grid aspect-[3/2] place-items-center bg-brand-50 text-3xl">{{ $s->icon }}</div>
                        @endif
                        <div class="px-3 py-2 text-sm font-medium group-hover:text-brand-800">{{ $s->name }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</section>
@endsection
