@extends('layouts.staff')
@section('title', $quote ? 'Edit '.$quote->display_number : 'New quote')
@section('main-class', 'pb-44')

@php
    $config = [
        'card' => $card,
        'state' => $state,
        'old' => old('payload') ? json_decode(old('payload'), true) : null,
        'isEdit' => (bool) $quote,
        'companyState' => $companyState,
        'discountLimit' => $discountLimit,
        'lockCustomer' => $lockCustomer,
        'states' => config('gst.states'),
        'urls' => ['search' => route('customers.search'), 'photo' => route('photos.store'), 'csrf' => csrf_token()],
    ];
@endphp

@section('content')
<form method="post" action="{{ $quote ? route('quotes.update', $quote) : route('quotes.store') }}"
      x-data="quoteBuilder(@js($config))" @submit.prevent="submit($event)" x-cloak>
    @csrf
    @if ($quote) @method('PUT') @endif
    <input type="hidden" name="payload">

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-xl font-semibold">{{ $quote ? 'Edit '.$quote->display_number : 'New quotation' }}</h1>
            @if ($sr)
                <p class="text-sm text-slate-500">From visit request <a class="link" href="{{ route('requests.show', $sr) }}">{{ $sr->number }}</a>
                    @unless ($quote) · items prefilled from the customer's estimate. Update the sizes you measured. @endunless</p>
            @endif
        </div>
        @if ($quote)
            <button type="button" class="btn btn-sm" @click="refreshRates()" x-show="!refresh_rates">Re-price at current rates</button>
            <span class="badge bg-amber-100 text-amber-800" x-show="refresh_rates">Will re-price at today's rate card</span>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">

    {{-- Customer & site --}}
    <section class="card card-body">
        <h2 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Customer &amp; site</h2>

        <template x-if="customer && !newCustomerMode">
            <div>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold" x-text="customer.name"></div>
                        <div class="text-sm text-slate-500" x-text="customer.phone"></div>
                    </div>
                    <button type="button" class="btn btn-sm" x-show="!lockCustomer" @click="customer = null; site_id = null; newSiteMode = false">Change</button>
                </div>
                <div class="mt-3 space-y-2" x-show="!newSiteMode">
                    <template x-for="s in customer.sites" :key="s.id">
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm"
                               :class="Number(site_id) === s.id ? 'border-brand-600 bg-brand-50' : 'border-slate-200'">
                            <input type="radio" class="mt-1" :value="s.id" x-model.number="site_id">
                            <span><span class="font-medium" x-text="s.label || 'Site'"></span> — <span x-text="s.address"></span></span>
                        </label>
                    </template>
                    <button type="button" class="link text-sm" @click="newSiteMode = true">+ Add a new site address</button>
                </div>
            </div>
        </template>

        <template x-if="!customer && !newCustomerMode">
            <div class="relative">
                <label class="label">Find customer by phone or name</label>
                <div class="flex gap-2">
                    <input class="input" type="search" inputmode="search" placeholder="e.g. 98220…" x-model="customerQuery"
                           @input.debounce.300ms="searchCustomers()" @keydown.enter.prevent>
                    <button type="button" class="btn whitespace-nowrap" @click="startNewCustomer()">+ New</button>
                </div>
                <div class="absolute z-20 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg" x-show="customerResults.length">
                    <template x-for="c in customerResults" :key="c.id">
                        <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50" @click="selectCustomer(c)">
                            <span class="font-medium" x-text="c.name"></span> <span class="text-slate-500" x-text="' · ' + c.phone"></span>
                        </button>
                    </template>
                </div>
                <p class="mt-1 text-xs text-slate-500" x-show="customerQuery.length >= 2 && !searching && !customerResults.length">
                    No match. <button type="button" class="link" @click="startNewCustomer()">Add as new customer</button>
                </p>
            </div>
        </template>

        <template x-if="newCustomerMode">
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">Name *</label><input class="input" x-model="newCustomer.name"></div>
                <div><label class="label">Mobile *</label><input class="input" type="tel" inputmode="tel" x-model="newCustomer.phone"></div>
                <div><label class="label">Email</label><input class="input" type="email" x-model="newCustomer.email"></div>
                <div><label class="label">GSTIN (business customers)</label><input class="input" maxlength="15" x-model="newCustomer.gstin"></div>
                <div class="sm:col-span-2"><button type="button" class="link text-sm" @click="newCustomerMode = false">← Search existing customers instead</button></div>
            </div>
        </template>

        <template x-if="newCustomerMode || newSiteMode">
            <div class="mt-3 grid gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2">
                <div class="flex items-center justify-between sm:col-span-2">
                    <span class="text-sm font-medium">Site address</span>
                    <button type="button" class="link text-sm" x-show="newSiteMode && !newCustomerMode && customer?.sites?.length" @click="newSiteMode = false">Use a saved site</button>
                </div>
                <div><label class="label">Label</label><input class="input" placeholder="Home / Office" x-model="newSite.label"></div>
                <div><label class="label">City</label><input class="input" x-model="newSite.city"></div>
                <div class="sm:col-span-2"><label class="label">Address *</label><textarea class="input" rows="2" x-model="newSite.address"></textarea></div>
                <div><label class="label">Pincode</label><input class="input" inputmode="numeric" x-model="newSite.pincode"></div>
                <div>
                    <label class="label">State (place of supply) *</label>
                    <select class="input" x-model="newSite.state_code">
                        <template x-for="(name, code) in states" :key="code">
                            <option :value="code" x-text="name" :selected="code === newSite.state_code"></option>
                        </template>
                    </select>
                </div>
            </div>
        </template>

        <p class="mt-3 text-xs" :class="intraState ? 'text-slate-500' : 'text-amber-700'"
           x-text="intraState ? 'Same state as company: CGST + SGST' : 'Different state: IGST applies'"></p>
    </section>

    {{-- Items --}}
    <section class="space-y-3">
        <template x-for="(line, i) in lines" :key="line.uid">
            <div class="card scroll-mt-20" :id="'line-' + line.uid">
                <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-700 text-xs font-semibold text-white" x-text="i + 1"></span>
                    <span class="truncate text-sm font-medium" x-text="product(line)?.name ?? 'Product no longer available'"></span>
                    <div class="ml-auto flex shrink-0 items-center gap-1">
                        <button type="button" class="btn btn-sm" title="Move up" @click="move(i, -1)" :disabled="i === 0">↑</button>
                        <button type="button" class="btn btn-sm" title="Move down" @click="move(i, 1)" :disabled="i === lines.length - 1">↓</button>
                        <button type="button" class="btn btn-sm" title="Duplicate" @click="duplicate(i)">Copy</button>
                        <button type="button" class="btn btn-sm btn-danger" title="Remove" @click="remove(i)">✕</button>
                    </div>
                </div>

                <div class="card-body space-y-3">
                    <div>
                        <label class="label">Item name / location (printed on the quote)</label>
                        <input class="input" x-model="line.name" :placeholder="product(line)?.name">
                    </div>

                    @include('partials.line-fields', ['customer' => false])

                    {{-- Photos --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-for="(url, k) in line.photo_urls" :key="url">
                            <div class="relative">
                                <img :src="url" class="h-16 w-16 rounded-lg border border-slate-200 object-cover" alt="">
                                <button type="button" class="absolute -top-1.5 -right-1.5 grid h-5 w-5 place-items-center rounded-full bg-red-600 text-xs text-white" @click="removePhoto(line, k)">×</button>
                            </div>
                        </template>
                        <label class="btn btn-sm cursor-pointer" x-show="line.photos.length < 8">
                            <span x-text="uploading[line.uid] ? 'Uploading…' : '+ Photo'"></span>
                            <input type="file" accept="image/*" capture="environment" multiple class="hidden" @change="addPhotos(line, $event)">
                        </label>
                    </div>
                </div>

                <div class="rounded-b-xl border-t border-slate-100 bg-slate-50 px-4 py-2.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 text-xs text-slate-600">
                            <template x-if="priced.lines[i]?.error">
                                <div class="font-medium text-red-700" x-text="'Formula error: ' + priced.lines[i].error"></div>
                            </template>
                            <div x-text="priced.lines[i]?.area_text"></div>
                            <div class="font-mono text-[11px] break-words text-slate-500" x-text="priced.lines[i]?.formula"></div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-semibold" x-text="money(priced.lines[i]?.amount ?? 0)"></div>
                            <span class="badge bg-amber-100 text-amber-800" x-show="priced.lines[i]?.min_applied">Min. item charge</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Product picker --}}
        <div class="card">
            <button type="button" class="flex w-full items-center justify-between px-4 py-3 text-left" @click="picker = !picker">
                <span class="font-medium">+ Add item</span>
                <span class="text-sm text-slate-500" x-text="picker ? 'Close' : card.products.length + ' products'"></span>
            </button>
            <div x-show="picker" class="border-t border-slate-100 p-4">
                <div class="mb-3 flex flex-wrap gap-2">
                    <input class="input max-w-xs" type="search" placeholder="Search products" x-model="pickerQuery">
                    <button type="button" class="btn btn-sm" :class="!pickerService && 'border-brand-600 bg-brand-50'" @click="pickerService = null">All</button>
                    <template x-for="s in card.services" :key="s.id">
                        <button type="button" class="btn btn-sm" :class="pickerService === s.id && 'border-brand-600 bg-brand-50'" @click="pickerService = s.id" x-text="(s.icon ? s.icon + ' ' : '') + s.name"></button>
                    </template>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="p in pickerProducts" :key="p.id">
                        <button type="button" class="rounded-lg border border-slate-200 p-3 text-left hover:border-brand-600 hover:bg-brand-50" @click="addProduct(p.id)">
                            <div class="text-sm font-medium" x-text="p.name"></div>
                            <div class="text-xs text-slate-500" x-text="p.description || p.measure_unit"></div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </section>
    </div>

    {{-- Charges & totals --}}
    <aside class="space-y-4">
        <section class="card card-body space-y-3">
            <div>
                <label class="label">Discount</label>
                <div class="flex gap-2">
                    <select class="input w-28" x-model="discount_type">
                        <option value="">None</option>
                        <option value="percent">%</option>
                        <option value="flat">₹ flat</option>
                    </select>
                    <input class="input" type="number" inputmode="decimal" step="any" min="0" x-model.number="discount_value" :disabled="!discount_type">
                </div>
                <p class="mt-1 text-xs text-amber-700" x-show="overLimit">
                    Over your limit of <span x-text="num(discountLimit)"></span>%. An admin must approve it before the quote can be sent.
                </p>
            </div>
            <div>
                <label class="label">Site visit charge (₹)</label>
                <input class="input" type="number" inputmode="decimal" step="any" min="0" x-model.number="visit_charge">
            </div>
            <div>
                <label class="label">Notes (printed on the quote)</label>
                <textarea class="input" rows="3" x-model="notes"></textarea>
            </div>
        </section>

        <section class="card card-body hidden text-sm lg:block">
            <dl class="space-y-1">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd x-text="money(priced.subtotal)"></dd></div>
                <div class="flex justify-between" x-show="priced.discount_amount > 0"><dt class="text-slate-500">Discount</dt><dd x-text="'- ' + money(priced.discount_amount)"></dd></div>
                <div class="flex justify-between" x-show="priced.visit_charge > 0"><dt class="text-slate-500">Visit charge</dt><dd x-text="money(priced.visit_charge)"></dd></div>
                <div class="flex justify-between text-amber-700" x-show="priced.min_job_applied"><dt>Min. job adjustment</dt><dd x-text="money(priced.min_job_adjustment)"></dd></div>
                <div class="flex justify-between font-medium"><dt>Taxable</dt><dd x-text="money(priced.taxable)"></dd></div>
                <template x-if="intraState">
                    <div>
                        <div class="flex justify-between"><dt class="text-slate-500" x-text="'CGST ' + gstHalf() + '%'"></dt><dd x-text="money(priced.cgst)"></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500" x-text="'SGST ' + gstHalf() + '%'"></dt><dd x-text="money(priced.sgst)"></dd></div>
                    </div>
                </template>
                <template x-if="!intraState">
                    <div class="flex justify-between"><dt class="text-slate-500" x-text="'IGST ' + num(rules.gst_pct) + '%'"></dt><dd x-text="money(priced.igst)"></dd></div>
                </template>
                <div class="flex justify-between border-t border-slate-200 pt-1 text-base font-semibold"><dt>Total</dt><dd x-text="money(priced.total)"></dd></div>
            </dl>
        </section>
    </aside>
    </div>

    {{-- Sticky save bar --}}
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 shadow-[0_-4px_12px_rgba(0,0,0,0.05)] backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
            <div>
                <div class="text-xs text-slate-500"><span x-text="lines.length"></span> items · incl. GST</div>
                <div class="text-xl font-semibold text-brand-800" x-text="money(priced.total)"></div>
            </div>
            <span class="badge bg-amber-100 text-amber-800" x-show="priced.min_job_applied">Min. job charge</span>
            <button class="btn btn-primary ml-auto px-6 py-2.5" :disabled="saving || !lines.length" x-text="saving ? 'Saving…' : 'Save quote'"></button>
        </div>
    </div>
</form>
@endsection
