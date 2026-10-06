@extends('layouts.staff')
@section('title', 'Job '.$job->quote->display_number)
@php
    use App\Services\Pricing\PricingService as P;
    use App\Support\Media;
    $c = $job->quote->customer_snapshot;
    $isTech = auth()->user()->role === 'technician';
    $photos = collect($job->photos ?? [])->map(fn ($p) => ['path' => $p, 'url' => Media::url($p)])->values();
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-2">
    <h1 class="text-xl font-semibold">Job · {{ $c['name'] }}</h1>
    @include('partials.status', ['status' => $job->status])
    <span class="badge {{ $job->quote->isFullyPaid() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
        {{ $job->quote->paymentStatusLabel() }}{{ $job->quote->balance() > 0 ? ' · '.P::money($job->quote->balance()).' due' : '' }}
    </span>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4">
        <div class="card card-body text-sm">
            <div class="label">Customer</div>
            <div class="font-medium">{{ $c['name'] }}</div>
            <a href="tel:{{ $c['phone'] }}" class="link">{{ $c['phone'] }}</a>
            <div class="label mt-3">Site</div>
            <div>{{ $c['address'] }}</div>
            <a class="link text-xs" target="_blank" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($c['address']) }}">Open in Maps</a>
            <div class="label mt-3">Quote</div>
            <a class="link" href="{{ route('quotes.show', $job->quote) }}">{{ $job->quote->display_number }}</a>
            @if ($job->invoice)
                <div class="label mt-3">Invoice</div>
                <div>{{ $job->invoice->number }}</div>
            @endif
        </div>

        <form method="post" action="{{ route('jobs.update', $job) }}" class="card card-body space-y-3"
              x-data="{ photos: @js($photos), busy: false,
                async add(e) {
                    this.busy = true;
                    try {
                        for (const file of e.target.files) {
                            const body = new FormData(); body.append('photo', file);
                            const r = await fetch('{{ route('photos.store') }}', { method: 'POST', body, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' } });
                            const d = await r.json(); if (!r.ok) throw new Error(d.message || 'Upload failed');
                            this.photos.push(d);
                        }
                    } catch (err) { alert(err.message) } finally { this.busy = false; e.target.value = ''; }
                } }">
            @csrf @method('PUT')
            <div>
                <label class="label">Status</label>
                <select class="input" name="status">
                    @foreach (\App\Models\ServiceJob::STATUSES as $k => $v)
                        <option value="{{ $k }}" @selected($job->status === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                @if ($job->status !== 'done')
                    <p class="mt-1 text-xs text-slate-500">Marking it Done closes the request{{ \App\Models\Setting::current()->auto_invoice_on_done ? ' and emails the tax invoice to the customer' : '' }}.</p>
                @endif
            </div>
            @unless ($isTech)
                <div>
                    <label class="label">Technician</label>
                    <select class="input" name="technician_id">
                        <option value="">Unassigned</option>
                        @foreach ($technicians as $t)
                            <option value="{{ $t->id }}" @selected($job->technician_id === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Scheduled on</label><input class="input" type="date" name="scheduled_on" value="{{ $job->scheduled_on?->toDateString() }}"></div>
            @endunless
            <div><label class="label">Notes</label><textarea class="input" name="notes" rows="3">{{ $job->notes }}</textarea></div>
            <div>
                <label class="label">Before / after photos</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="(p, k) in photos" :key="p.path">
                        <div class="relative">
                            <input type="hidden" name="photos[]" :value="p.path">
                            <img :src="p.url" class="h-16 w-16 rounded-lg border object-cover" alt="">
                            <button type="button" class="absolute -top-1.5 -right-1.5 grid h-5 w-5 place-items-center rounded-full bg-red-600 text-xs text-white" @click="photos.splice(k, 1)">×</button>
                        </div>
                    </template>
                    <label class="btn btn-sm cursor-pointer"><span x-text="busy ? 'Uploading…' : '+ Photo'"></span>
                        <input type="file" accept="image/*" capture="environment" multiple class="hidden" @change="add($event)"></label>
                </div>
            </div>
            <button class="btn btn-primary w-full" :disabled="busy">Update job</button>
        </form>
    </div>

    <div class="card lg:col-span-2">
        <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Work to do</h2>
        <ul class="divide-y divide-slate-100">
            @foreach ($job->quote->lines as $i => $line)
                <li class="px-4 py-3 text-sm">
                    <div class="font-medium">{{ $i + 1 }}. {{ $line->name }} <span class="text-slate-500">× {{ P::num($line->qty) }}</span></div>
                    <div class="text-slate-600">{{ $line->specification() }}</div>
                    @if ($s = $line->inputSummary())<div class="text-xs text-slate-500">{{ $s }}</div>@endif
                    <div class="text-xs text-slate-500">{{ $line->area_text }}</div>
                    @if (! empty($line->photos))
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($line->photos as $photo)
                                <a href="{{ Media::url($photo) }}" target="_blank"><img src="{{ Media::url($photo) }}" class="h-16 w-16 rounded-lg border object-cover" alt=""></a>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
