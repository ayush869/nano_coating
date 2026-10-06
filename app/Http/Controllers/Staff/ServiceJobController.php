<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ServiceJob;
use App\Models\User;
use App\Services\QuoteWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ServiceJobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');

        $jobs = ServiceJob::with('quote.customer', 'technician')
            ->when($user->role === User::TECHNICIAN, fn ($q) => $q->where('technician_id', $user->id))
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'done'))
            ->orderByRaw('scheduled_on is null')->orderBy('scheduled_on')->latest('id')
            ->paginate(25)->withQueryString();

        return view('staff.jobs.index', compact('jobs', 'status'));
    }

    public function show(Request $request, ServiceJob $job)
    {
        $this->authorizeJob($request->user(), $job);
        $job->load('quote.lines', 'quote.customer', 'quote.serviceRequest', 'technician', 'invoice');

        return view('staff.jobs.show', [
            'job' => $job,
            'technicians' => User::where('role', User::TECHNICIAN)->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ServiceJob $job, QuoteWorkflow $workflow)
    {
        $user = $request->user();
        $this->authorizeJob($user, $job);

        $rules = [
            'status' => ['required', Rule::in(array_keys(ServiceJob::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:12'],
            'photos.*' => ['string', 'regex:#^photos/[A-Za-z0-9._-]+$#'],
        ];
        if ($user->role !== User::TECHNICIAN) {
            $rules['technician_id'] = ['nullable', Rule::exists('users', 'id')->where('role', User::TECHNICIAN)];
            $rules['scheduled_on'] = ['nullable', 'date'];
        }
        $data = $request->validate($rules);
        $data['photos'] = collect($data['photos'] ?? $job->photos ?? [])
            ->filter(fn ($p) => Storage::disk('public')->exists($p))->values()->all();

        $wasDone = $job->status === 'done';
        $data['completed_at'] = $data['status'] === 'done' ? ($job->completed_at ?? now()) : null;
        $job->update($data);

        if (! $wasDone && $job->status === 'done') {
            $workflow->jobCompleted($job);

            return back()->with('ok', 'Job completed.'.($job->quote->fresh()->invoice ? ' Invoice issued to the customer.' : ''));
        }

        return back()->with('ok', 'Job updated.');
    }

    private function authorizeJob(User $user, ServiceJob $job): void
    {
        abort_if($user->role === User::TECHNICIAN && (int) $job->technician_id !== $user->id, 403);
    }
}
