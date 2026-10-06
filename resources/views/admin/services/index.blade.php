@extends('layouts.staff')
@section('title', 'Service categories')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold">Service categories</h1>
        <p class="text-sm text-slate-500">Groups shown on the website. Each holds products with their own formulas.</p>
    </div>
    <a href="{{ route('admin.services.create') }}" class="btn btn-primary">+ Add service</a>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th class="w-16">Sort</th><th>Service</th><th>Website address</th><th class="text-right">Products</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @foreach ($services as $s)
                <tr class="{{ $s->active ? '' : 'text-slate-400' }}">
                    <td>{{ $s->sort }}</td>
                    <td><span class="mr-1">{{ $s->icon }}</span><span class="font-medium">{{ $s->name }}</span><div class="text-xs text-slate-500">{{ $s->summary }}</div></td>
                    <td><a class="link text-xs" target="_blank" href="{{ route('site.service', $s) }}">/services/{{ $s->slug }}</a></td>
                    <td class="text-right"><a class="link" href="{{ route('admin.products.index', ['service' => $s->id]) }}">{{ $s->products_count }}</a></td>
                    <td>{{ $s->active ? 'Active' : 'Hidden' }}</td>
                    <td class="text-right whitespace-nowrap">
                        <a class="btn btn-sm" href="{{ route('admin.products.create', ['service' => $s->id]) }}">+ Product</a>
                        <a class="btn btn-sm" href="{{ route('admin.services.edit', $s) }}">Edit</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
