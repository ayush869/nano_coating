@extends('layouts.site')
@section('title', 'Test checkout')
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-t-xl bg-slate-800 px-5 py-4 text-white">
        <div class="text-xs tracking-wide text-amber-300 uppercase">Test gateway · no real money</div>
        <div class="mt-1 text-lg font-semibold">{{ $company->name }}</div>
        <div class="text-sm text-white/70">{{ $payment->purpose_label }} · {{ $payment->quote->display_number }}</div>
        <div class="mt-2 text-3xl font-bold">{{ P::money($payment->amount) }}</div>
    </div>
    <form method="post" class="card rounded-t-none p-5" x-data="{ method: 'upi' }">
        @csrf
        <p class="text-sm text-slate-600">This page stands in for Razorpay Checkout so you can test the whole flow. It goes through the same signature check as a real payment.</p>
        <div class="mt-4 grid grid-cols-2 gap-2">
            @foreach (['upi' => 'UPI', 'card' => 'Card', 'netbanking' => 'Net banking', 'wallet' => 'Wallet'] as $k => $v)
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm" :class="method === '{{ $k }}' ? 'border-brand-600 bg-brand-50' : 'border-slate-200'">
                    <input type="radio" name="method" value="{{ $k }}" x-model="method"> {{ $v }}
                </label>
            @endforeach
        </div>
        <div class="mt-5 grid gap-2">
            <button name="outcome" value="success" class="btn btn-primary py-3">Simulate successful payment</button>
            <button name="outcome" value="failure" class="btn btn-danger">Simulate failed payment</button>
            <a href="{{ route('portal.quotes.show', $payment->quote_id) }}" class="btn">Cancel</a>
        </div>
    </form>
</div>
@endsection
