@extends('layouts.staff')
@section('title', $customer->exists ? 'Edit customer' : 'New customer')

@section('content')
<h1 class="mb-4 text-xl font-semibold">{{ $customer->exists ? 'Edit '.$customer->name : 'New customer' }}</h1>

<form method="post" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="card card-body max-w-2xl space-y-4">
    @csrf
    @if ($customer->exists) @method('PUT') @endif
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="label">Name *</label><input class="input" name="name" value="{{ old('name', $customer->name) }}" required></div>
        <div><label class="label">Phone *</label><input class="input" name="phone" type="tel" inputmode="tel" value="{{ old('phone', $customer->phone) }}" required></div>
        <div><label class="label">Email</label><input class="input" name="email" type="email" value="{{ old('email', $customer->email) }}"></div>
        <div><label class="label">GSTIN</label><input class="input" name="gstin" maxlength="15" value="{{ old('gstin', $customer->gstin) }}"></div>
        <div class="sm:col-span-2"><label class="label">Notes</label><textarea class="input" name="notes" rows="2">{{ old('notes', $customer->notes) }}</textarea></div>
    </div>

    @unless ($customer->exists)
        <div class="border-t border-slate-100 pt-4">
            <div class="mb-2 text-sm font-medium">First site address</div>
            <div class="grid gap-3 sm:grid-cols-2">@include('staff.customers._site_fields')</div>
        </div>
    @endunless

    <div class="flex gap-2">
        <button class="btn btn-primary">Save</button>
        <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn">Cancel</a>
    </div>
</form>
@endsection
