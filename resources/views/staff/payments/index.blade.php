@extends('layouts.staff')
@section('title', 'Payments')
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold">Payments</h1>
        <p class="text-sm text-slate-500">
            Online gateway: <span class="font-medium">{{ $gateway === 'razorpay' ? 'Razorpay' : 'Built-in test gateway (no real money)' }}</span>
            @if (auth()->user()->isAdmin()) · <a class="link" href="{{ route('admin.settings.edit') }}#payments">settings</a>@endif
        </p>
    </div>
    <form>
        <select class="input w-auto" name="status" onchange="this.form.submit()">
            @foreach (['paid' => 'Received', 'created' => 'Pending', 'failed' => 'Failed', 'all' => 'All'] as $k => $v)
                <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Receipt</th><th>Date</th><th>Customer</th><th>Quote</th><th>For</th><th>Mode</th><th class="text-right">Amount</th><th></th></tr></thead>
        <tbody>
            @forelse ($payments as $p)
                <tr>
                    <td class="font-mono text-xs">{{ $p->number ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ ($p->paid_at ?? $p->created_at)->format('d M Y, H:i') }}</td>
                    <td>{{ $p->customer->name }}</td>
                    <td><a class="link" href="{{ route('quotes.show', $p->quote) }}">{{ $p->quote->display_number }}</a></td>
                    <td>{{ $p->purpose_label }}</td>
                    <td>{{ $p->gateway_label }}{{ $p->method ? ' · '.strtoupper($p->method) : '' }}
                        @if ($p->reference)<div class="text-xs text-slate-500">Ref. {{ $p->reference }}</div>@endif
                        @if ($p->failure_reason)<div class="text-xs text-red-700">{{ $p->failure_reason }}</div>@endif
                    </td>
                    <td class="text-right font-medium">{{ P::money($p->amount) }}</td>
                    <td class="text-right">
                        @if ($p->status === 'paid')
                            <a class="btn btn-sm" target="_blank" href="{{ route('payments.receipt', $p) }}">Receipt</a>
                        @else
                            @include('partials.status', ['status' => $p->status])
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-slate-500">No payments.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
