@extends('layouts.site')
@php use App\Support\Media; @endphp

@section('content')
{{-- Hero --}}
<section class="relative isolate overflow-hidden bg-slate-900">
    <img src="{{ asset('images/hero.jpg') }}" alt="A bright living room with a coated sofa and rug" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60" fetchpriority="high">
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/90 via-slate-900/70 to-slate-900/10"></div>
    <div class="mx-auto max-w-6xl px-4 py-20 md:py-28">
        <div class="max-w-2xl">
            <span class="badge bg-white/15 px-3 py-1 text-white ring-1 ring-white/25 backdrop-blur">Nano coating at your home or office</span>
            <h1 class="mt-4 text-4xl leading-tight font-bold tracking-tight text-white md:text-6xl">{{ $company->tagline ?: 'Invisible protection for everything you love' }}</h1>
            <p class="mt-5 text-lg text-slate-200">Spill-proof sofas, stain-free marble, fingerprint-free glass and washable walls. Get an instant estimate, upload photos, and we'll visit, measure and quote.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('portal.requests.create') }}" class="btn btn-primary px-6 py-3 text-base shadow-lg shadow-brand-900/30">Get a free estimate</a>
                <a href="#services" class="btn border-white/30 bg-white/10 px-6 py-3 text-base text-white backdrop-blur hover:bg-white/20">Explore services</a>
            </div>
            <p class="mt-4 text-sm text-slate-300">Site visit {{ $settings->visit_charge > 0 ? '₹'.(int) $settings->visit_charge : 'free' }} · Pay {{ (int) $settings->advance_pct }}% advance online, balance after the work</p>
        </div>
    </div>
</section>

{{-- Promise bar --}}
<section class="border-b border-slate-200 bg-white">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Instant estimate', 'See a price before anyone visits.', 'M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z'],
            ['Measured on site', 'A technician confirms every size.', 'M4 7h16M4 7v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7M4 7l2-3h12l2 3M9 11h6'],
            ['Pay securely online', 'UPI, cards and net banking.', 'M12 3 4 6v6c0 5 3.4 8.3 8 9 4.6-.7 8-4 8-9V6l-8-3Zm-3 9 2 2 4-4'],
            ['GST invoice', 'Proper receipts for every payment.', 'M7 3h7l5 5v13H7V3Zm7 0v5h5M10 13h6m-6 4h6'],
        ] as [$title, $text, $icon])
            <div class="flex gap-3">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
                </div>
                <div>
                    <div class="font-semibold">{{ $title }}</div>
                    <div class="text-sm text-slate-600">{{ $text }}</div>
                </div>
            </div>
        @endforeach
    </div>
</section>

{{-- Services --}}
<section id="services" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-16">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-wide text-brand-700 uppercase">Services</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">What we protect</h2>
            <p class="mt-2 max-w-xl text-slate-600">Pick a service to see typical prices, or start your estimate straight away.</p>
        </div>
        <a href="{{ route('portal.requests.create') }}" class="link text-sm">Start an estimate →</a>
    </div>
    @if ($services->isEmpty())
        <div class="card card-body mt-8 text-center text-slate-500">Our services will appear here soon.</div>
    @endif
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($services as $s)
            <a href="{{ route('site.service', $s) }}" class="card group flex flex-col overflow-hidden transition hover:-translate-y-1 hover:shadow-lg">
                <div class="relative aspect-[3/2] overflow-hidden bg-brand-50">
                    @if ($img = Media::url($s->image_path))
                        <img src="{{ $img }}" alt="{{ $s->name }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    @else
                        <div class="grid h-full place-items-center text-6xl">{{ $s->icon }}</div>
                    @endif
                    <span class="badge absolute top-3 left-3 bg-white/90 text-slate-700 shadow-sm">{{ $s->products_count }} {{ Str::plural('option', $s->products_count) }}</span>
                </div>
                <div class="flex flex-1 flex-col p-5">
                    <div class="text-lg font-semibold group-hover:text-brand-800">{{ $s->name }}</div>
                    <div class="mt-1 flex-1 text-sm text-slate-600">{{ $s->summary }}</div>
                    <div class="mt-4 text-sm font-medium text-brand-700">See prices →</div>
                </div>
            </a>
        @endforeach
    </div>
</section>

{{-- Why --}}
<section class="bg-white">
    <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 md:grid-cols-2">
        <img src="{{ asset('images/about.jpg') }}" alt="A living room with protected upholstery" loading="lazy" class="aspect-[10/11] w-full rounded-2xl object-cover shadow-xl">
        <div>
            <p class="text-sm font-semibold tracking-wide text-brand-700 uppercase">Why nano coating</p>
            <h2 class="mt-1 text-3xl font-bold tracking-tight">Spills bead up. Stains wipe off. Surfaces stay new.</h2>
            <p class="mt-4 text-slate-600">A nano coating bonds to the fibres or pores of a surface and forms an invisible, breathable shield. It keeps the look and feel of the material while repelling water, oil and dirt.</p>
            <ul class="mt-6 space-y-3">
                @foreach ([
                    'Invisible: no change to colour, texture or breathability',
                    'Water, oil and stain repellent on fabric, leather, wood, stone and glass',
                    'Optional anti-microbial and UV layers',
                    'Safe for homes with children and pets once cured',
                ] as $point)
                    <li class="flex gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                        <span class="text-slate-700">{{ $point }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

{{-- How it works --}}
<section id="how" class="scroll-mt-20">
    <div class="mx-auto max-w-6xl px-4 py-16">
        <p class="text-sm font-semibold tracking-wide text-brand-700 uppercase">How it works</p>
        <h2 class="mt-1 text-3xl font-bold tracking-tight">From estimate to finished job in four steps</h2>
        <ol class="mt-10 grid gap-6 md:grid-cols-4">
            @foreach ([
                ['Tell us what to protect', 'Choose your items, enter rough sizes and upload photos. You see an estimate instantly.'],
                ['We visit and measure', 'A technician visits at your chosen time, checks the surfaces and measures properly.'],
                ['Approve & pay online', 'Your quotation arrives on WhatsApp and email. Accept it and pay the advance by UPI, card or net banking.'],
                ['We coat, you relax', 'Our team applies the coating. Pay the balance online when the job is done and get your GST invoice.'],
            ] as $i => [$title, $text])
                <li class="card card-body relative">
                    <div class="grid h-10 w-10 place-items-center rounded-full bg-brand-700 font-semibold text-white">{{ $i + 1 }}</div>
                    <div class="mt-4 font-semibold">{{ $title }}</div>
                    <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- CTA --}}
<section class="mx-auto max-w-6xl px-4 pb-16">
    <div class="relative isolate overflow-hidden rounded-3xl bg-brand-800 px-6 py-14 text-center text-white md:px-12">
        <img src="{{ asset('images/cta.jpg') }}" alt="" loading="lazy" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-25">
        <h2 class="text-3xl font-bold tracking-tight">Get your estimate in two minutes</h2>
        <p class="mt-2 text-white/80">Sign in with your mobile number. No password needed.</p>
        <a href="{{ route('portal.requests.create') }}" class="mt-6 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-brand-800 shadow hover:bg-brand-50">Start my estimate</a>
    </div>
</section>
@endsection
