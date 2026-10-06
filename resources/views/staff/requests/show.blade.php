@extends('layouts.staff')
@section('title', $sr->number)
@php
    use App\Models\QuoteLine;
    use App\Models\ServiceRequest;
    use App\Services\Pricing\PricingService as P;
    use App\Support\Media;
    $user = auth()->user();
    $staff = $user->hasRole('admin', 'sales');
    $openQuote = $sr->quotes->first(fn ($q) => $q->status !== 'superseded');
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-xl font-semibold">{{ $sr->number }}</h1>
            @include('partials.status', ['status' => $sr->status])
        </div>
        <div class="text-sm text-slate-500">Requested {{ $sr->created_at->format('d M Y, h:i A') }} via {{ $sr->source }}</div>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($openQuote)
            <a href="{{ route('quotes.show', $openQuote) }}" class="btn btn-primary">Open quote {{ $openQuote->display_number }}</a>
        @elseif ($sr->isOpen() && ($staff || (int) $sr->technician_id === $user->id))
            <a href="{{ route('quotes.create', ['request' => $sr->id]) }}" class="btn btn-primary">Create quotation from this visit</a>
        @endif
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4">
        <div class="card card-body text-sm">
            <div class="label">Customer</div>
            <div class="font-medium">{{ $sr->customer->name }}</div>
            <a href="tel:{{ $sr->customer->phone }}" class="link">{{ $sr->customer->phone }}</a>
            <a href="https://wa.me/91{{ $sr->customer->phone }}" target="_blank" class="ml-2 text-xs text-emerald-700">WhatsApp</a>
            @if ($sr->customer->email)<div>{{ $sr->customer->email }}</div>@endif
            <div class="label mt-3">Site{{ $sr->site->label ? ' · '.$sr->site->label : '' }}</div>
            <div>{{ $sr->site->fullAddress() }}</div>
            <a class="link text-xs" target="_blank" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($sr->site->fullAddress()) }}">Open in Maps</a>
            <div class="label mt-3">Preferred time</div>
            <div>{{ $sr->preferred_date?->format('D d M Y') ?? 'Any day' }} · {{ ServiceRequest::SLOTS[$sr->preferred_slot] ?? 'Any time' }}</div>
            @if ($sr->notes)
                <div class="label mt-3">Customer's notes</div>
                <div class="whitespace-pre-line">{{ $sr->notes }}</div>
            @endif
            @if ($sr->cancel_reason)
                <div class="label mt-3">Cancelled</div>
                <div class="text-red-700">{{ $sr->cancel_reason }}</div>
            @endif
        </div>

        {{-- Assignment --}}
        <div class="card card-body">
            <h2 class="mb-2 font-semibold">Site visit</h2>
            @if ($sr->technician)
                <p class="text-sm"><span class="font-medium">{{ $sr->technician->name }}</span> on
                    <span class="font-medium">{{ $sr->visit_at?->format('D d M, h:i A') }}</span></p>
            @else
                <p class="text-sm text-amber-700">No technician assigned yet.</p>
            @endif
            @if ($staff && ! in_array($sr->status, [ServiceRequest::CLOSED, ServiceRequest::CANCELLED]))
                <form method="post" action="{{ route('requests.assign', $sr) }}" class="mt-3 space-y-2">
                    @csrf
                    <div>
                        <label class="label">Technician</label>
                        <select class="input" name="technician_id" required>
                            <option value="">Choose…</option>
                            @foreach ($technicians as $t)
                                <option value="{{ $t->id }}" @selected($sr->technician_id === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Visit date &amp; time</label>
                        <input class="input" type="datetime-local" name="visit_at" required
                               value="{{ ($sr->visit_at ?? ($sr->preferred_date ?? now()->addDay())->copy()->setTime(['morning' => 10, 'afternoon' => 13, 'evening' => 17][$sr->preferred_slot] ?? 10, 0))->format('Y-m-d\TH:i') }}">
                    </div>
                    <button class="btn btn-primary w-full">{{ $sr->technician ? 'Reschedule' : 'Assign & notify customer' }}</button>
                </form>
                @if ($sr->isOpen())
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs text-red-700">Cancel request</summary>
                        <form method="post" action="{{ route('requests.cancel', $sr) }}" class="mt-2 flex gap-2">
                            @csrf
                            <input class="input" name="reason" placeholder="Reason" required>
                            <button class="btn btn-danger">Cancel</button>
                        </form>
                    </details>
                @endif
            @endif
        </div>

        <form method="post" action="{{ route('requests.notes', $sr) }}" class="card card-body space-y-2">
            @csrf @method('PUT')
            <label class="label">Internal notes (not shown to customer)</label>
            <textarea class="input" name="staff_notes" rows="3">{{ $sr->staff_notes }}</textarea>
            <button class="btn btn-sm">Save notes</button>
        </form>
    </div>

    <div class="space-y-4 lg:col-span-2">
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <h2 class="font-semibold">What the customer asked for</h2>
                <div class="text-right">
                    <div class="font-semibold">{{ P::money($sr->estimate_total) }}</div>
                    <div class="text-[11px] text-slate-500">customer's estimate incl. GST</div>
                </div>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($sr->items as $i => $item)
                    <li class="px-4 py-3">
                        <div class="flex justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ $i + 1 }}. {{ $item->name ?: $item->product_name }} @if ($item->qty != 1)<span class="text-slate-500">× {{ P::num($item->qty) }}</span>@endif</div>
                                <div class="text-xs text-slate-500">{{ $item->product_name }}</div>
                                <div class="text-xs text-slate-500">{{ QuoteLine::summariseInputs($item->product_snapshot['fields'] ?? [], $item->inputs ?? []) }}</div>
                                <div class="text-xs text-slate-600">{{ $item->area_text }}</div>
                            </div>
                            <div class="text-sm font-medium">{{ P::money($item->estimate_amount) }}</div>
                        </div>
                        @if (! empty($item->photos))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($item->photos as $photo)
                                    <a href="{{ Media::url($photo) }}" target="_blank"><img src="{{ Media::url($photo) }}" class="h-20 w-20 rounded-lg border object-cover" alt=""></a>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if (! empty($sr->photos))
            <div class="card card-body">
                <h2 class="mb-2 font-semibold">Site photos</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($sr->photos as $photo)
                        <a href="{{ Media::url($photo) }}" target="_blank"><img src="{{ Media::url($photo) }}" class="h-28 w-28 rounded-lg border object-cover" alt=""></a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($sr->quotes->isNotEmpty())
            <div class="card">
                <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Quotations</h2>
                <ul class="divide-y divide-slate-100">
                    @foreach ($sr->quotes as $q)
                        <li><a href="{{ route('quotes.show', $q) }}" class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50">
                            <span class="font-mono">{{ $q->display_number }}</span>
                            <span class="flex items-center gap-2">{{ P::money($q->total) }} @include('partials.status', ['status' => $q->status])</span>
                        </a></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
