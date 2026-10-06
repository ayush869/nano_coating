@extends('layouts.staff')
@section('title', 'Invoices')
@php use App\Services\Pricing\PricingService as P; @endphp

@section('content')
<h1 class="mb-4 text-xl font-semibold">Invoices</h1>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Quote</th><th class="text-right">Total</th><th class="text-right">Balance</th><th></th></tr></thead>
        <tbody>
            @forelse ($invoices as $inv)
                <tr>
                    <td class="font-mono">{{ $inv->number }}</td>
                    <td>{{ $inv->date->format('d M Y') }}</td>
                    <td>{{ $inv->quote->customer_snapshot['name'] }}</td>
                    <td><a class="link" href="{{ route('quotes.show', $inv->quote) }}">{{ $inv->quote->display_number }}</a></td>
                    <td class="text-right font-medium">{{ P::money($inv->quote->total) }}</td>
                    <td class="text-right {{ $inv->quote->balance() > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $inv->quote->balance() > 0 ? P::money($inv->quote->balance()) : 'Paid' }}</td>
                    <td class="text-right"><a class="btn btn-sm" target="_blank" href="{{ route('invoices.pdf', $inv) }}">PDF</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-slate-500">No invoices yet. Convert an accepted quote to a job, then create its invoice.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $invoices->links() }}</div>
@endsection
