@extends('layouts.staff')
@section('title', 'Company profile')

@section('content')
<h1 class="mb-4 text-xl font-semibold">Company profile</h1>

<form method="post" action="{{ route('admin.company.update') }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-4">
    @csrf @method('PUT')
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">Company name *</label><input class="input" name="name" value="{{ old('name', $company->name) }}" required></div>
        <div><label class="label">Tagline (website)</label><input class="input" name="tagline" value="{{ old('tagline', $company->tagline) }}"></div>
        <div><label class="label">WhatsApp number (with country code)</label><input class="input" name="whatsapp" placeholder="919876543210" value="{{ old('whatsapp', $company->whatsapp) }}"></div>
        <div><label class="label">GSTIN</label><input class="input" name="gstin" maxlength="15" value="{{ old('gstin', $company->gstin) }}"></div>
        <div class="sm:col-span-2"><label class="label">Address</label><textarea class="input" name="address" rows="2">{{ old('address', $company->address) }}</textarea></div>
        <div>
            <label class="label">State (decides CGST/SGST vs IGST) *</label>
            <select class="input" name="state_code" required>
                @foreach (config('gst.states') as $code => $name)
                    <option value="{{ $code }}" @selected(old('state_code', $company->state_code) == $code)>{{ $name }} ({{ $code }})</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Phone</label><input class="input" name="phone" value="{{ old('phone', $company->phone) }}"></div>
        <div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ old('email', $company->email) }}"></div>
        <div><label class="label">Signatory line</label><input class="input" name="signatory" value="{{ old('signatory', $company->signatory) }}"></div>
        <div class="sm:col-span-2">
            <label class="label">Logo (PNG/JPG, max 2 MB)</label>
            <div class="flex items-center gap-3">
                @if ($company->logo_path)
                    <img src="{{ \App\Support\Media::url($company->logo_path) }}" class="h-12 rounded border" alt="Logo">
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg" class="text-sm">
            </div>
        </div>
        <div class="sm:col-span-2"><label class="label">Bank details (printed on PDF)</label><textarea class="input" name="bank_details" rows="4">{{ old('bank_details', $company->bank_details) }}</textarea></div>
        <div class="sm:col-span-2"><label class="label">Terms &amp; conditions (one per line)</label><textarea class="input" name="terms" rows="6">{{ old('terms', $company->terms) }}</textarea></div>
    </div>
    <button class="btn btn-primary">Save profile</button>
</form>
@endsection
