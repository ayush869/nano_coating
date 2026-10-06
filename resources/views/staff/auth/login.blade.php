<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-slate-50 px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-xl bg-brand-700 font-semibold text-white">NC</div>
            <h1 class="text-lg font-semibold">{{ config('app.name') }}</h1>
            <p class="text-sm text-slate-500">Staff sign-in</p>
        </div>
        <form method="post" action="{{ url('staff/login') }}" class="card card-body space-y-4">
            @csrf
            <div>
                <label class="label" for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <input class="input" id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            @error('email')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Keep me signed in
            </label>
            <button class="btn btn-primary w-full">Log in</button>
        </form>
    </div>
</body>
</html>
