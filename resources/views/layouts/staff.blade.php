<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <title>@yield('title', 'Staff') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
@php
    $user = auth()->user();
    $newRequests = $user->hasRole('admin', 'sales') ? \App\Models\ServiceRequest::where('status', 'new')->count() : 0;
    if ($user->hasRole('admin', 'sales')) {
        $nav = [
            ['dashboard', 'Home', 'dashboard'],
            ['requests.index', 'Requests', 'requests.*', $newRequests],
            ['quotes.index', 'Quotes', 'quotes.*'],
            ['jobs.index', 'Jobs', 'jobs.*'],
            ['payments.index', 'Payments', 'payments.*'],
            ['invoices.index', 'Invoices', 'invoices.*'],
            ['customers.index', 'Customers', 'customers.*'],
            ['reports', 'Reports', 'reports'],
        ];
    } else {
        $nav = [
            ['dashboard', 'Today', 'dashboard'],
            ['requests.index', 'My visits', 'requests.*'],
            ['quotes.index', 'My quotes', 'quotes.*'],
            ['jobs.index', 'My jobs', 'jobs.*'],
        ];
    }
    $admin = [
        ['admin.products.index', 'Products & formulas', []],
        ['admin.services.index', 'Service categories', []],
        ['admin.rates.index', 'Rate card', ['table' => 'coatings']],
        ['admin.settings.edit', 'Rules, GST & payments', []],
        ['admin.company.edit', 'Company profile', []],
        ['admin.users.index', 'Users', []],
        ['admin.audit', 'Audit log', []],
    ];
@endphp

<header class="sticky top-0 z-30 border-b border-brand-800 bg-brand-700 text-white" x-data="{ open: false }">
    <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/15 text-sm">NC</span>
            <span class="hidden xl:inline">{{ config('app.name') }}</span>
        </a>
        <nav class="hidden flex-1 items-center gap-0.5 lg:flex">
            @foreach ($nav as $item)
                <a href="{{ route($item[0]) }}" class="relative rounded-md px-2.5 py-1.5 text-sm {{ request()->routeIs($item[2]) ? 'bg-white/20' : 'hover:bg-white/10' }}">
                    {{ $item[1] }}
                    @if (!empty($item[3]))<span class="ml-1 rounded-full bg-amber-400 px-1.5 text-xs font-semibold text-amber-950">{{ $item[3] }}</span>@endif
                </a>
            @endforeach
            @if ($user->isAdmin())
                <div class="relative" x-data="{ a: false }" @click.outside="a = false">
                    <button @click="a = !a" class="rounded-md px-2.5 py-1.5 text-sm {{ request()->routeIs('admin.*') ? 'bg-white/20' : 'hover:bg-white/10' }}">Admin ▾</button>
                    <div x-cloak x-show="a" class="absolute left-0 mt-1 w-56 rounded-lg bg-white py-1 text-sm text-slate-700 shadow-lg ring-1 ring-slate-200">
                        @foreach ($admin as [$route, $label, $params])
                            <a href="{{ route($route, $params) }}" class="block px-3 py-2 hover:bg-slate-50">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </nav>
        <div class="ml-auto flex items-center gap-2">
            @if ($user->hasRole('admin', 'sales'))
                <a href="{{ route('quotes.create') }}" class="hidden rounded-md bg-white px-3 py-1.5 text-sm font-medium text-brand-800 sm:inline-block">+ New quote</a>
            @endif
            <a href="{{ route('home') }}" target="_blank" class="hidden text-xs text-white/70 hover:text-white xl:inline">Website ↗</a>
            <span class="hidden text-xs text-white/80 xl:inline">{{ $user->name }} · {{ \App\Models\User::ROLES[$user->role] }}</span>
            <form method="post" action="{{ route('logout') }}" class="hidden lg:block">@csrf
                <button class="rounded-md px-2 py-1.5 text-sm hover:bg-white/10">Log out</button>
            </form>
            <button class="rounded-md p-2 hover:bg-white/10 lg:hidden" @click="open = !open" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <div x-cloak x-show="open" class="border-t border-white/10 px-4 pb-3 lg:hidden">
        @foreach ($nav as $item)
            <a href="{{ route($item[0]) }}" class="block rounded-md px-3 py-2 text-sm {{ request()->routeIs($item[2]) ? 'bg-white/20' : '' }}">
                {{ $item[1] }} @if (!empty($item[3]))<span class="ml-1 rounded-full bg-amber-400 px-1.5 text-xs text-amber-950">{{ $item[3] }}</span>@endif
            </a>
        @endforeach
        @if ($user->isAdmin())
            <div class="mt-2 px-3 text-xs tracking-wide text-white/60 uppercase">Admin</div>
            @foreach ($admin as [$route, $label, $params])
                <a href="{{ route($route, $params) }}" class="block rounded-md px-3 py-2 text-sm">{{ $label }}</a>
            @endforeach
        @endif
        <form method="post" action="{{ route('logout') }}" class="mt-2 border-t border-white/10 pt-2">@csrf
            <button class="px-3 py-2 text-sm">Log out ({{ $user->name }})</button>
        </form>
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 py-5 @yield('main-class')">
    @if (session('ok'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('ok') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc space-y-0.5 pl-4">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
