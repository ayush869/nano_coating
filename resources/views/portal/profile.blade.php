@extends('layouts.site')
@section('title', $welcome ? 'Welcome' : 'My profile')

@section('content')
<div class="mx-auto max-w-lg px-4 py-10">
    <h1 class="text-2xl font-bold">{{ $welcome ? 'Welcome! Tell us your name' : 'My profile' }}</h1>
    @if ($welcome)<p class="mt-1 text-sm text-slate-600">So our team knows who to ask for when we visit.</p>@endif

    <form method="post" action="{{ route('portal.profile.update') }}" class="card card-body mt-6 space-y-4">
        @csrf @method('PUT')
        <div><label class="label">Full name *</label><input class="input" name="name" value="{{ old('name', $customer->name) }}" required autofocus></div>
        <div><label class="label">Email (for quotations and invoices)</label><input class="input" type="email" name="email" value="{{ old('email', $customer->email) }}"></div>
        <div><label class="label">GSTIN (only for business billing)</label><input class="input" name="gstin" maxlength="15" value="{{ old('gstin', $customer->gstin) }}"></div>
        <div class="text-sm text-slate-500">Mobile: +91 {{ $customer->phone }}</div>
        <button class="btn btn-primary w-full">{{ $welcome ? 'Continue' : 'Save' }}</button>
    </form>

    @if (! $welcome && $customer->sites->isNotEmpty())
        <div class="card card-body mt-6">
            <h2 class="mb-2 font-semibold">Saved addresses</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($customer->sites as $s)
                    <li><span class="font-medium">{{ $s->label ?: 'Address' }}:</span> {{ $s->fullAddress() }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
