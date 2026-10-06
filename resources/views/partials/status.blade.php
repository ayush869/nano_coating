@php
    $colors = [
        // quotes
        'draft' => 'bg-slate-100 text-slate-700',
        'sent' => 'bg-sky-100 text-sky-800',
        'accepted' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-red-100 text-red-800',
        'expired' => 'bg-amber-100 text-amber-800',
        'converted' => 'bg-violet-100 text-violet-800',
        'invoiced' => 'bg-brand-100 text-brand-800',
        'superseded' => 'bg-slate-100 text-slate-500 line-through',
        // jobs
        'scheduled' => 'bg-sky-100 text-sky-800',
        'in_progress' => 'bg-amber-100 text-amber-800',
        'done' => 'bg-emerald-100 text-emerald-800',
        // requests
        'new' => 'bg-amber-100 text-amber-900',
        'assigned' => 'bg-sky-100 text-sky-800',
        'quoted' => 'bg-violet-100 text-violet-800',
        'closed' => 'bg-slate-100 text-slate-600',
        'cancelled' => 'bg-red-50 text-red-700',
        // payments
        'created' => 'bg-slate-100 text-slate-600',
        'paid' => 'bg-emerald-100 text-emerald-800',
        'failed' => 'bg-red-100 text-red-800',
    ];
    $labels = \App\Models\Quote::STATUSES + \App\Models\ServiceJob::STATUSES + \App\Models\ServiceRequest::STATUSES
        + ['created' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed'];
    if (($context ?? null) === 'customer') {
        $labels = ['sent' => 'Awaiting your approval', 'converted' => 'Booked', 'invoiced' => 'Completed'] + $labels;
    }
@endphp
<span class="badge {{ $colors[$status] ?? 'bg-slate-100' }}">{{ $labels[$status] ?? $status }}</span>
