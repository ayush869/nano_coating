@extends('layouts.site')
@section('title', 'Log in')

@section('content')
<div class="mx-auto max-w-sm px-4 py-12">
    <h1 class="text-2xl font-bold">Log in or sign up</h1>
    <p class="mt-1 text-sm text-slate-600">We'll send a 6-digit code to your mobile. No password needed.</p>
    <form method="post" action="{{ route('customer.login.send') }}" class="card card-body mt-6 space-y-4">
        @csrf
        <div>
            <label class="label" for="phone">Mobile number</label>
            <div class="flex">
                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-600">+91</span>
                <input class="input rounded-l-none text-lg tracking-wide" id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel-national"
                       maxlength="14" value="{{ old('phone') }}" placeholder="98XXXXXXXX" required autofocus>
            </div>
        </div>
        <button class="btn btn-primary w-full py-2.5">Send code</button>
    </form>
    <p class="mt-4 text-center text-xs text-slate-500">By continuing you agree to be contacted about your request on this number.</p>
</div>
@endsection
