@extends('layouts.staff')
@section('title', 'Customers')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold">Customers</h1>
    <a href="{{ route('customers.create') }}" class="btn btn-primary">+ New customer</a>
</div>

<form class="mb-4 flex gap-2">
    <input class="input max-w-sm" type="search" name="q" value="{{ $q }}" placeholder="Search by phone or name" autofocus>
    <button class="btn">Search</button>
</form>

<div class="card overflow-hidden">
    <ul class="divide-y divide-slate-100">
        @forelse ($customers as $c)
            <li>
                <a href="{{ route('customers.show', $c) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $c->name }}</div>
                        <div class="text-sm text-slate-500">{{ $c->phone }}{{ $c->email ? ' · '.$c->email : '' }}</div>
                    </div>
                    <div class="text-right text-xs text-slate-500">{{ $c->sites_count }} site{{ $c->sites_count == 1 ? '' : 's' }} · {{ $c->quotes_count }} quote{{ $c->quotes_count == 1 ? '' : 's' }}</div>
                </a>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-slate-500">No customers found.</li>
        @endforelse
    </ul>
</div>
<div class="mt-4">{{ $customers->links() }}</div>
@endsection
