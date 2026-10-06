@extends('layouts.site')
@section('title', 'Enter code')

@section('content')
<div class="mx-auto max-w-sm px-4 py-12">
    <h1 class="text-2xl font-bold">Enter the code</h1>
    <p class="mt-1 text-sm text-slate-600">Sent to +91 {{ $phone }}. <a class="link" href="{{ route('customer.login') }}">Change number</a></p>

    @if (session('otp_dev'))
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <b>Test mode:</b> no SMS provider is connected, so the code is shown here: <span class="font-mono text-lg font-bold tracking-widest">{{ session('otp_dev') }}</span>
        </div>
    @endif

    <form method="post" action="{{ url('login/verify') }}" class="card card-body mt-6 space-y-4">
        @csrf
        <div>
            <label class="label" for="code">6-digit code</label>
            <input class="input text-center font-mono text-2xl tracking-[0.5em]" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                   maxlength="6" pattern="\d{6}" required autofocus>
        </div>
        <button class="btn btn-primary w-full py-2.5">Verify &amp; continue</button>
    </form>
    <form method="post" action="{{ route('customer.login.send') }}" class="mt-3 text-center">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone }}">
        <button class="link text-sm">Send a new code</button>
    </form>
</div>
@endsection
