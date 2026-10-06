@php
    $company = $company ?? \App\Models\Company::current();
    $customer = auth('customer')->user();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <meta name="description" content="{{ $company->tagline }}">
    <title>@hasSection('title')@yield('title') · @endif{{ $company->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-slate-50 text-slate-900 antialiased">
<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur" x-data="{ open: false }">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2">
            @if ($company->logo_path)
                <img src="{{ \App\Support\Media::url($company->logo_path) }}" alt="" class="h-9 w-auto">
            @else
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-700 text-sm font-bold text-white">{{ mb_substr($company->name, 0, 1) }}</span>
            @endif
            <span class="font-semibold">{{ $company->name }}</span>
        </a>
        <nav class="ml-auto hidden items-center gap-1 text-sm md:flex">
            <a href="{{ route('home') }}#services" class="rounded-md px-3 py-2 hover:bg-slate-100">Services</a>
            <a href="{{ route('home') }}#how" class="rounded-md px-3 py-2 hover:bg-slate-100">How it works</a>
            @if ($customer)
                <a href="{{ route('portal.dashboard') }}" class="rounded-md px-3 py-2 hover:bg-slate-100 {{ request()->routeIs('portal.*') ? 'font-semibold text-brand-800' : '' }}">My account</a>
                <form method="post" action="{{ route('portal.logout') }}">@csrf<button class="rounded-md px-3 py-2 hover:bg-slate-100">Log out</button></form>
            @else
                <a href="{{ route('customer.login') }}" class="rounded-md px-3 py-2 hover:bg-slate-100">Log in</a>
            @endif
            <a href="{{ route('portal.requests.create') }}" class="btn btn-primary ml-2">Get a free estimate</a>
        </nav>
        <button class="ml-auto rounded-md p-2 hover:bg-slate-100 md:hidden" @click="open = !open" aria-label="Menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <div x-cloak x-show="open" class="border-t border-slate-100 px-4 pb-4 md:hidden">
        <a href="{{ route('home') }}#services" class="block py-2">Services</a>
        <a href="{{ route('home') }}#how" class="block py-2">How it works</a>
        @if ($customer)
            <a href="{{ route('portal.dashboard') }}" class="block py-2">My account</a>
            <form method="post" action="{{ route('portal.logout') }}">@csrf<button class="py-2">Log out</button></form>
        @else
            <a href="{{ route('customer.login') }}" class="block py-2">Log in</a>
        @endif
        <a href="{{ route('portal.requests.create') }}" class="btn btn-primary mt-2 w-full">Get a free estimate</a>
    </div>
</header>

<main class="flex-1 @yield('main-class')">
    @if (session('ok') || session('error') || $errors->any())
        <div class="mx-auto max-w-6xl px-4 pt-4">
            @if (session('ok'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('ok') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-0.5 pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
        </div>
    @endif
    @yield('content')
</main>

<footer class="mt-12 border-t border-slate-200 bg-white">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 text-sm text-slate-600 sm:grid-cols-3">
        <div>
            <div class="font-semibold text-slate-900">{{ $company->name }}</div>
            <div class="mt-1 whitespace-pre-line">{{ $company->address }}</div>
        </div>
        <div>
            <div class="font-semibold text-slate-900">Contact</div>
            @if ($company->phone)<a href="tel:{{ preg_replace('/\s/', '', $company->phone) }}" class="mt-1 block">{{ $company->phone }}</a>@endif
            @if ($company->email)<a href="mailto:{{ $company->email }}" class="block">{{ $company->email }}</a>@endif
        </div>
        <div class="sm:text-right">
            <a href="{{ route('staff.login') }}" class="text-xs text-slate-400 hover:text-slate-600">Staff login</a>
        </div>
    </div>
</footer>

@if ($company->whatsapp)
    <a href="https://wa.me/{{ preg_replace('/\D/', '', $company->whatsapp) }}" target="_blank" rel="noopener"
       class="fixed right-4 bottom-4 z-20 grid h-14 w-14 place-items-center rounded-full bg-emerald-500 text-white shadow-lg hover:bg-emerald-600 @yield('wa-class')"
       aria-label="Chat on WhatsApp">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9 0-1.4.7-2 1-2.3.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.4.6-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.7-.1l2 1c.3.1.5.2.6.3.1.2.1.8-.1 1.4Z"/></svg>
    </a>
@endif
</body>
</html>
