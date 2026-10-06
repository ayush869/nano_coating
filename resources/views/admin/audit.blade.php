@extends('layouts.staff')
@section('title', 'Audit log')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold">Audit log</h1>
    <form>
        <select class="input w-auto" name="table" onchange="this.form.submit()">
            <option value="">All tables</option>
            @foreach ($tables as $t)<option value="{{ $t }}" @selected($table === $t)>{{ $t }}</option>@endforeach
        </select>
    </form>
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>When</th><th>Who</th><th>Table</th><th>Record</th><th>Change</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap">{{ $log->at->format('d M Y H:i') }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                    <td>{{ $log->table_name }}</td>
                    <td>#{{ $log->record_id }} <span class="text-xs text-slate-500">{{ $log->new_json['name'] ?? '' }}</span></td>
                    <td class="text-xs">
                        @if ($log->action === 'created')
                            <span class="badge bg-emerald-100 text-emerald-800">created</span>
                        @else
                            @foreach ($log->new_json as $field => $new)
                                <div><span class="font-medium">{{ $field }}</span>: <span class="text-red-700 line-through">{{ is_scalar($log->old_json[$field] ?? null) ? $log->old_json[$field] : json_encode($log->old_json[$field] ?? null) }}</span> → <span class="text-emerald-700">{{ is_scalar($new) ? $new : json_encode($new) }}</span></div>
                            @endforeach
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-slate-500">No changes recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
