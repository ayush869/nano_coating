@php $site = $site ?? null; @endphp
<div><label class="label">Label</label><input class="input" name="label" placeholder="Home / Office" value="{{ old('label', $site?->label) }}"></div>
<div><label class="label">City</label><input class="input" name="city" value="{{ old('city', $site?->city) }}"></div>
<div class="sm:col-span-2"><label class="label">Address *</label><textarea class="input" name="address" rows="2" required>{{ old('address', $site?->address) }}</textarea></div>
<div><label class="label">Pincode</label><input class="input" name="pincode" inputmode="numeric" value="{{ old('pincode', $site?->pincode) }}"></div>
<div>
    <label class="label">State (place of supply) *</label>
    <select class="input" name="state_code" required>
        @foreach (config('gst.states') as $code => $name)
            <option value="{{ $code }}" @selected(old('state_code', $site?->state_code ?? \App\Models\Company::current()->state_code) == $code)>{{ $name }}</option>
        @endforeach
    </select>
</div>
