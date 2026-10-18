<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8">
                <div class="mb-6 grid h-12 w-12 place-items-center rounded-xl bg-brand-700 font-semibold text-white shadow-lg shadow-brand-900/20">NC</div>
                <h1 class="text-2xl font-bold tracking-tight">Welcome back</h1>
                <p class="mt-1 text-sm text-slate-500">Sign in to the {{ config('app.name') }} staff panel.</p>
            </div>
            <form method="post" action="{{ url('staff/login') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="label" for="email">Email</label>
                    <input class="input py-2.5" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.com">
                </div>
                <div>
                    <label class="label" for="password">Password</label>
                    <input class="input py-2.5" id="password" type="password" name="password" required autocomplete="current-password">
                </div>
                @error('email')<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>@enderror
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Keep me signed in
                </label>
                <button class="btn btn-primary w-full py-2.5">Log in</button>
            </form>
            <a href="{{ route('home') }}" class="mt-8 inline-block text-sm text-slate-500 hover:text-slate-700">← Back to the website</a>
        </div>
    </div>
    <div class="relative hidden overflow-hidden bg-brand-800 lg:block">
        <img src="{{ asset('images/staff-login.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60">
        <div class="absolute inset-0 bg-gradient-to-t from-brand-900/90 via-brand-900/30 to-transparent"></div>
        <div class="absolute right-10 bottom-10 left-10 text-white">
            <p class="text-2xl font-semibold">Requests, quotes, jobs and payments in one place.</p>
            <p class="mt-2 text-white/80">Design products with your own formulas and every estimate prices itself.</p>
        </div>
    </div>
</div>
</body>
</html>
