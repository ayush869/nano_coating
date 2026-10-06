@extends('layouts.staff')
@section('title', 'Jobs')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold">{{ auth()->user()->role === 'technician' ? 'My jobs' : 'Jobs' }}</h1>
    <form>
        <select class="input w-auto" name="status" onchange="this.form.submit()">
            <option value="">Open jobs</option>
            @foreach (\App\Models\ServiceJob::STATUSES as $k => $v)
                <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card overflow-hidden">
    <ul class="divide-y divide-slate-100">
        @forelse ($jobs as $job)
            @php $c = $job->quote->customer_snapshot; @endphp
            <li>
                <a href="{{ route('jobs.show', $job) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                    <div class="w-16 shrink-0 text-center">
                        @if ($job->scheduled_on)
                            <div class="text-xs text-slate-500 uppercase">{{ $job->scheduled_on->format('D') }}</div>
                            <div class="text-lg leading-tight font-semibold">{{ $job->scheduled_on->format('d') }}</div>
                            <div class="text-xs text-slate-500">{{ $job->scheduled_on->format('M') }}</div>
                        @else
                            <div class="text-xs text-slate-400">No date</div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $c['name'] }}</div>
                        <div class="truncate text-sm text-slate-500">{{ $c['address'] }}</div>
                        <div class="text-xs text-slate-500">{{ $job->quote->display_number }} · {{ $job->technician?->name ?? 'Unassigned' }}</div>
                    </div>
                    @include('partials.status', ['status' => $job->status])
                </a>
            </li>
        @empty
            <li class="px-4 py-8 text-center text-sm text-slate-500">No jobs.</li>
        @endforelse
    </ul>
</div>
<div class="mt-4">{{ $jobs->links() }}</div>
@endsection
