@extends('layouts.site')
@section('title', 'Get an estimate')
@section('main-class', 'pb-32')
@section('wa-class', 'bottom-24')

@php
    $config = [
        'card' => $card,
        'sites' => $sites,
        'companyState' => $companyState,
        'states' => config('gst.states'),
        'preselect' => $preselect,
        'old' => old('payload') ? json_decode(old('payload'), true) : null,
        'urls' => ['photo' => route('photos.store'), 'csrf' => csrf_token()],
    ];
    $steps = [1 => 'Items', 2 => 'Photos', 3 => 'Address & time', 4 => 'Review'];
@endphp

@section('content')
<form method="post" action="{{ route('portal.requests.store') }}" x-data="requestForm(@js($config))" @submit.prevent="submit($event)" x-cloak
      class="mx-auto max-w-4xl px-4 py-6">
    @csrf
    <input type="hidden" name="payload">

    <h1 class="text-2xl font-bold">Get your estimate</h1>

    {{-- Steps --}}
    <ol class="mt-4 grid grid-cols-4 gap-1 text-center text-xs">
        @foreach ($steps as $n => $label)
            <li>
                <button type="button" class="w-full" @click="{{ $n }} < step && go({{ $n }})">
                    <div class="mx-auto mb-1 h-1.5 rounded-full" :class="step >= {{ $n }} ? 'bg-brand-600' : 'bg-slate-200'"></div>
                    <span :class="step === {{ $n }} ? 'font-semibold text-brand-800' : 'text-slate-500'">{{ $n }}. {{ $label }}</span>
                </button>
            </li>
        @endforeach
    </ol>

    {{-- Step 1: items --}}
    <section x-show="step === 1" class="mt-6 space-y-4">
        <div class="card card-body">
            <h2 class="font-semibold">What would you like to protect?</h2>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                <template x-for="s in servicesWithProducts" :key="s.id">
                    <button type="button" class="btn shrink-0" :class="service === s.id && 'border-brand-600 bg-brand-50 text-brand-800'" @click="service = s.id" x-text="(s.icon ? s.icon + ' ' : '') + s.name"></button>
                </template>
            </div>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <template x-for="p in productsFor(service)" :key="p.id">
                    <button type="button" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-left hover:border-brand-600 hover:bg-brand-50" @click="add(p.id)">
                        <template x-if="p.image_url"><img :src="p.image_url" class="h-12 w-12 rounded object-cover" alt=""></template>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium" x-text="p.name"></div>
                            <div class="text-xs text-slate-500" x-text="p.description"></div>
                        </div>
                        <span class="shrink-0 text-sm font-medium text-brand-700" x-text="countOf(p.id) ? '+ Add another' : '+ Add'"></span>
                    </button>
                </template>
            </div>
        </div>

        <template x-for="(line, i) in lines" :key="line.uid">
            <div class="card scroll-mt-24" :id="'item-' + line.uid">
                <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
                    <span class="grid h-6 w-6 place-items-center rounded-full bg-brand-700 text-xs font-semibold text-white" x-text="i + 1"></span>
                    <span class="font-medium" x-text="product(line)?.name"></span>
                    <button type="button" class="ml-auto text-sm text-red-700" @click="remove(i)">Remove</button>
                </div>
                <div class="card-body space-y-3">
                    <div>
                        <label class="label">Where is it? (optional)</label>
                        <input class="input" x-model="line.name" placeholder="e.g. Living room, Kids' bedroom">
                    </div>
                    @include('partials.line-fields', ['customer' => true])
                </div>
                <div class="flex items-center justify-between rounded-b-xl border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-sm">
                    <span class="text-slate-600" x-text="estimate.lines[i]?.error ? 'Please check the sizes' : estimate.lines[i]?.area_text"></span>
                    <span class="font-semibold" x-text="money(estimate.lines[i]?.amount ?? 0)"></span>
                </div>
            </div>
        </template>

        <p class="text-center text-sm text-slate-500" x-show="!lines.length">Add at least one item above.</p>
    </section>

    {{-- Step 2: photos --}}
    <section x-show="step === 2" class="mt-6 space-y-4">
        <div class="card card-body">
            <h2 class="font-semibold">Add photos (optional, but helpful)</h2>
            <p class="text-sm text-slate-500">Photos help our technician bring the right products. Take them from your phone camera.</p>
        </div>
        <template x-for="(line, i) in lines" :key="line.uid">
            <div class="card card-body">
                <div class="mb-2 font-medium" x-text="(line.name || product(line)?.name)"></div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="(url, k) in line.photo_urls" :key="url">
                        <div class="relative">
                            <img :src="url" class="h-20 w-20 rounded-lg border object-cover" alt="">
                            <button type="button" class="absolute -top-1.5 -right-1.5 grid h-5 w-5 place-items-center rounded-full bg-red-600 text-xs text-white" @click="removePhoto(line, k)">×</button>
                        </div>
                    </template>
                    <label class="grid h-20 w-20 cursor-pointer place-items-center rounded-lg border-2 border-dashed border-slate-300 text-center text-xs text-slate-500 hover:border-brand-600">
                        <span x-text="uploading[line.uid] ? 'Uploading…' : '+ Photo'"></span>
                        <input type="file" accept="image/*" multiple class="hidden" @change="addPhotos(line, $event)">
                    </label>
                </div>
            </div>
        </template>
        <div class="card card-body">
            <div class="mb-2 font-medium">Other photos of the site</div>
            <div class="flex flex-wrap gap-2">
                <template x-for="(url, k) in photo_urls" :key="url">
                    <div class="relative">
                        <img :src="url" class="h-20 w-20 rounded-lg border object-cover" alt="">
                        <button type="button" class="absolute -top-1.5 -right-1.5 grid h-5 w-5 place-items-center rounded-full bg-red-600 text-xs text-white" @click="removePhoto(null, k)">×</button>
                    </div>
                </template>
                <label class="grid h-20 w-20 cursor-pointer place-items-center rounded-lg border-2 border-dashed border-slate-300 text-xs text-slate-500 hover:border-brand-600">
                    <span x-text="uploading.site ? 'Uploading…' : '+ Photo'"></span>
                    <input type="file" accept="image/*" multiple class="hidden" @change="addPhotos(null, $event)">
                </label>
            </div>
            <label class="label mt-4">Anything we should know?</label>
            <textarea class="input" rows="3" x-model="notes" placeholder="Stains to treat, parking, best time to call…"></textarea>
        </div>
    </section>

    {{-- Step 3: address & time --}}
    <section x-show="step === 3" class="mt-6 space-y-4">
        <div class="card card-body">
            <h2 class="mb-3 font-semibold">Where should we come?</h2>
            <div class="space-y-2" x-show="!newSiteMode">
                <template x-for="s in sites" :key="s.id">
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm" :class="Number(site_id) === s.id ? 'border-brand-600 bg-brand-50' : 'border-slate-200'">
                        <input type="radio" class="mt-1" :value="s.id" x-model.number="site_id">
                        <span><span class="font-medium" x-text="s.label || 'Address'"></span> — <span x-text="s.address"></span></span>
                    </label>
                </template>
                <button type="button" class="link text-sm" @click="newSiteMode = true">+ Use a different address</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2" x-show="newSiteMode">
                <div><label class="label">Label</label><input class="input" x-model="newSite.label" placeholder="Home / Office"></div>
                <div><label class="label">City</label><input class="input" x-model="newSite.city"></div>
                <div class="sm:col-span-2"><label class="label">Full address *</label><textarea class="input" rows="2" x-model="newSite.address" placeholder="Flat / house no., building, street, area"></textarea></div>
                <div><label class="label">Pincode</label><input class="input" inputmode="numeric" maxlength="6" x-model="newSite.pincode"></div>
                <div>
                    <label class="label">State *</label>
                    <select class="input" x-model="newSite.state_code">
                        <template x-for="(name, code) in states" :key="code"><option :value="code" x-text="name" :selected="code === newSite.state_code"></option></template>
                    </select>
                </div>
                <button type="button" class="link text-left text-sm" x-show="sites.length" @click="newSiteMode = false">Use a saved address</button>
            </div>
        </div>
        <div class="card card-body">
            <h2 class="mb-3 font-semibold">When suits you for the site visit?</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">Preferred date</label><input class="input" type="date" min="{{ now()->toDateString() }}" x-model="preferred_date"></div>
                <div>
                    <label class="label">Preferred time</label>
                    <select class="input" x-model="preferred_slot">
                        @foreach (\App\Models\ServiceRequest::SLOTS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-500">We'll call to confirm the exact time.</p>
        </div>
    </section>

    {{-- Step 4: review --}}
    <section x-show="step === 4" class="mt-6 space-y-4">
        <div class="card">
            <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Your estimate</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                <template x-for="(line, i) in lines" :key="line.uid">
                    <li class="flex justify-between gap-3 px-4 py-2.5">
                        <div>
                            <div class="font-medium" x-text="(line.name ? line.name + ' · ' : '') + product(line)?.name + (line.qty > 1 ? ' × ' + line.qty : '')"></div>
                            <div class="text-xs text-slate-500" x-text="estimate.lines[i]?.area_text"></div>
                        </div>
                        <div x-text="money(estimate.lines[i]?.amount ?? 0)"></div>
                    </li>
                </template>
            </ul>
            <dl class="space-y-1 border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                <div class="flex justify-between"><dt>Items</dt><dd x-text="money(estimate.subtotal)"></dd></div>
                <div class="flex justify-between" x-show="estimate.visit_charge > 0"><dt>Site visit</dt><dd x-text="money(estimate.visit_charge)"></dd></div>
                <div class="flex justify-between" x-show="estimate.min_job_applied"><dt>Minimum job charge adjustment</dt><dd x-text="money(estimate.min_job_adjustment)"></dd></div>
                <div class="flex justify-between"><dt>GST</dt><dd x-text="money(estimate.gst_total)"></dd></div>
                <div class="flex justify-between border-t border-slate-300 pt-1 text-base font-semibold"><dt>Estimated total</dt><dd x-text="money(estimate.total)"></dd></div>
            </dl>
        </div>
        @if ($settings->estimate_note)
            <p class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">{{ $settings->estimate_note }}</p>
        @endif
        <p class="text-sm text-slate-600">No payment now. After the visit you'll get the final quotation to approve and pay online.</p>
    </section>

    {{-- Bottom bar --}}
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-4xl items-center gap-3 px-4 py-3">
            <button type="button" class="btn" x-show="step > 1" @click="go(step - 1)">Back</button>
            <div>
                <div class="text-xs text-slate-500">Estimate incl. GST</div>
                <div class="text-lg font-semibold text-brand-800" x-text="money(estimate.total)"></div>
            </div>
            <button type="button" class="btn btn-primary ml-auto px-6" x-show="step < 4" :disabled="!canNext()" @click="go(step + 1)">Next</button>
            <button class="btn btn-primary ml-auto px-6" x-show="step === 4" :disabled="saving" x-text="saving ? 'Sending…' : 'Request site visit'"></button>
        </div>
    </div>
</form>
@endsection
