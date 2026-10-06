@extends('layouts.staff')
@section('title', 'Rate card · '.$meta['label'])

@section('content')
<h1 class="mb-3 text-xl font-semibold">Rate card</h1>

<div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200 pb-px">
    @foreach ($tables as $key => $t)
        <a href="{{ route('admin.rates.index', $key) }}"
           class="shrink-0 rounded-t-lg px-3 py-2 text-sm {{ $key === $table ? 'border border-b-white border-slate-200 bg-white font-medium text-brand-800' : 'text-slate-600 hover:text-slate-900' }}">{{ $t['label'] }}</a>
    @endforeach
</div>

<p class="mb-3 text-sm text-slate-500">{{ $meta['hint'] }} Changes apply to new quotes only; saved quotes keep their rates. Rows are deactivated, never deleted.</p>

<div class="card overflow-x-auto">
    <table class="table min-w-[640px]">
        <thead>
            <tr><th class="w-16">Sort</th><th>Name</th><th class="w-36">{{ $meta['value'] }}</th><th>Description</th><th class="w-20">Active</th><th class="w-20"></th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="{{ $row->active ? '' : 'bg-slate-50 text-slate-400' }}">
                    <td colspan="6" class="p-0">
                        <form method="post" action="{{ route('admin.rates.update', [$table, $row->id]) }}" class="grid grid-cols-[4rem_1fr_9rem_1fr_5rem_5rem] items-center gap-2 px-3 py-2">
                            @csrf @method('PUT')
                            <input class="input px-2" type="number" name="sort" value="{{ $row->sort }}" min="0">
                            <input class="input" name="name" value="{{ $row->name }}" required>
                            <input class="input" type="number" name="{{ $column }}" value="{{ $row->{$column} }}" step="{{ $meta['step'] }}" min="0" required>
                            <input class="input" name="description" value="{{ $row->description }}">
                            <label class="flex justify-center"><input type="checkbox" name="active" value="1" @checked($row->active)></label>
                            <button class="btn btn-sm">Save</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            <tr class="bg-brand-50/50">
                <td colspan="6" class="p-0">
                    <form method="post" action="{{ route('admin.rates.store', $table) }}" class="grid grid-cols-[4rem_1fr_9rem_1fr_5rem_5rem] items-center gap-2 px-3 py-2">
                        @csrf
                        <input class="input px-2" type="number" name="sort" value="{{ ($rows->max('sort') ?? 0) + 10 }}" min="0">
                        <input class="input" name="name" placeholder="New {{ strtolower(\Illuminate\Support\Str::singular($meta['label'])) }}" required>
                        <input class="input" type="number" name="{{ $column }}" step="{{ $meta['step'] }}" min="0" placeholder="0" required>
                        <input class="input" name="description" placeholder="Optional">
                        <span></span>
                        <button class="btn btn-primary btn-sm">Add</button>
                    </form>
                </td>
            </tr>
        </tbody>
    </table>
</div>

@if ($coatings)
    <form method="post" action="{{ route('admin.rates.combinations') }}" class="card mt-6 overflow-x-auto">
        @csrf @method('PUT')
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="font-semibold">Allowed coating × material combinations</h2>
            <p class="text-xs text-slate-500">Tick the materials each coating may be used on. A coating with no ticks can be used on any material.</p>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Coating</th>
                    @foreach ($materials as $m)<th class="text-center text-[10px] normal-case">{{ $m->name }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($coatings as $c)
                    @php $allowed = $c->materials->pluck('id'); @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $c->name }}</td>
                        @foreach ($materials as $m)
                            <td class="text-center"><input type="checkbox" name="allow[{{ $c->id }}][]" value="{{ $m->id }}" @checked($allowed->contains($m->id))></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3"><button class="btn btn-primary">Save combinations</button></div>
    </form>
@endif
@endsection
