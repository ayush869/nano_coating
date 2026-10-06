@extends('layouts.site')

@section('content')
<section class="bg-gradient-to-b from-brand-50 to-slate-50">
    <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 md:grid-cols-2 md:py-20">
        <div>
            <span class="badge bg-brand-100 text-brand-800">Nano coating at your home or office</span>
            <h1 class="mt-3 text-4xl leading-tight font-bold tracking-tight text-slate-900 md:text-5xl">{{ $company->tagline ?: 'Invisible protection for everything you love' }}</h1>
            <p class="mt-4 text-lg text-slate-600">Spill-proof sofas, stain-free marble, fingerprint-free glass and washable walls. Get an instant estimate, upload photos, and we'll visit, measure and quote.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('portal.requests.create') }}" class="btn btn-primary px-5 py-3 text-base">Get a free estimate</a>
                <a href="#services" class="btn px-5 py-3 text-base">See services</a>
            </div>
            <p class="mt-3 text-sm text-slate-500">Site visit {{ $settings->visit_charge > 0 ? '₹'.(int) $settings->visit_charge : 'free' }} · Pay {{ (int) $settings->advance_pct }}% advance online, balance after the work</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
            @foreach ($services->take(4) as $s)
                <a href="{{ route('site.service', $s) }}" class="card card-body transition hover:-translate-y-0.5 hover:border-brand-600">
                    <div class="text-3xl">{{ $s->icon }}</div>
                    <div class="mt-2 font-semibold">{{ $s->name }}</div>
                    <div class="text-xs text-slate-500">{{ $s->products_count }} options</div>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section id="services" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-12">
    <h2 class="text-2xl font-bold">What we protect</h2>
    <p class="mt-1 text-slate-600">Pick a service to see typical prices, or start your estimate straight away.</p>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($services as $s)
            <a href="{{ route('site.service', $s) }}" class="card group flex gap-4 p-5 hover:border-brand-600">
                <div class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-brand-50 text-3xl">{{ $s->icon }}</div>
                <div>
                    <div class="font-semibold group-hover:text-brand-800">{{ $s->name }}</div>
                    <div class="mt-1 text-sm text-slate-600">{{ $s->summary }}</div>
                    <div class="mt-2 text-xs font-medium text-brand-700">{{ $s->products_count }} options →</div>
                </div>
            </a>
        @endforeach
    </div>
</section>

<section id="how" class="scroll-mt-20 border-y border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-12">
        <h2 class="text-2xl font-bold">How it works</h2>
        <ol class="mt-6 grid gap-6 md:grid-cols-4">
            @foreach ([
                ['Tell us what to protect', 'Choose your items, enter rough sizes and upload photos. You see an estimate instantly.'],
                ['We visit and measure', 'A technician visits at your chosen time, checks the surfaces and measures properly.'],
                ['Approve & pay online', 'Your quotation arrives on WhatsApp and email. Accept it and pay the advance by UPI, card or net banking.'],
                ['We coat, you relax', 'Our team applies the coating. Pay the balance online when the job is done and get your GST invoice.'],
            ] as $i => [$title, $text])
                <li>
                    <div class="grid h-10 w-10 place-items-center rounded-full bg-brand-700 font-semibold text-white">{{ $i + 1 }}</div>
                    <div class="mt-3 font-semibold">{{ $title }}</div>
                    <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-12">
    <div class="rounded-2xl bg-brand-700 px-6 py-10 text-center text-white md:px-12">
        <h2 class="text-2xl font-bold">Get your estimate in two minutes</h2>
        <p class="mt-2 text-white/80">Sign in with your mobile number, no password needed.</p>
        <a href="{{ route('portal.requests.create') }}" class="mt-5 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-brand-800">Start my estimate</a>
    </div>
</section>
@endsection
