<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestAssigned;
use App\Services\Sms\SmsSender;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Customer visit requests: assign a technician, then quote after the visit. */
class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $term = trim((string) $request->query('q'));

        $requests = ServiceRequest::with('customer', 'site', 'technician')
            ->when($user->role === User::TECHNICIAN, fn ($q) => $q->where('technician_id', $user->id))
            ->when($status, fn ($q) => $q->where('status', $status),
                fn ($q) => $q->whereIn('status', [ServiceRequest::NEW, ServiceRequest::ASSIGNED]))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%$term%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->orderByRaw("case status when 'new' then 0 when 'assigned' then 1 else 2 end")
            ->orderByRaw('visit_at is null')->orderBy('visit_at')->latest('id')
            ->paginate(25)->withQueryString();

        return view('staff.requests.index', compact('requests', 'status', 'term'));
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeView($request->user(), $serviceRequest);
        $serviceRequest->load('items', 'customer', 'site', 'technician', 'quotes');

        return view('staff.requests.show', [
            'sr' => $serviceRequest,
            'technicians' => User::where('role', User::TECHNICIAN)->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function assign(Request $request, ServiceRequest $serviceRequest, SmsSender $sms)
    {
        $data = $request->validate([
            'technician_id' => ['required', Rule::exists('users', 'id')->where('role', User::TECHNICIAN)->where('active', true)],
            'visit_at' => ['required', 'date'],
        ]);
        if (in_array($serviceRequest->status, [ServiceRequest::CLOSED, ServiceRequest::CANCELLED], true)) {
            return back()->with('error', 'This request is closed.');
        }

        $serviceRequest->update([
            'technician_id' => $data['technician_id'],
            'visit_at' => Carbon::parse($data['visit_at']),
            'status' => $serviceRequest->status === ServiceRequest::NEW ? ServiceRequest::ASSIGNED : $serviceRequest->status,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);
        $serviceRequest->load('technician', 'customer', 'site');

        $serviceRequest->technician->notify(new RequestAssigned($serviceRequest));
        $serviceRequest->customer->notify(new RequestAssigned($serviceRequest));
        $sms->send($serviceRequest->customer->phone, 'Your site visit for '.$serviceRequest->number.' is on '
            .$serviceRequest->visit_at->format('d M, h:i A').'. Technician: '.$serviceRequest->technician->name
            .($serviceRequest->technician->phone ? ' ('.$serviceRequest->technician->phone.')' : '').'.');

        return back()->with('ok', 'Visit scheduled with '.$serviceRequest->technician->name.'.');
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);
        $serviceRequest->update(['status' => ServiceRequest::CANCELLED, 'cancel_reason' => $data['reason']]);

        return back()->with('ok', 'Request cancelled.');
    }

    public function notes(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeView($request->user(), $serviceRequest);
        $serviceRequest->update($request->validate(['staff_notes' => ['nullable', 'string', 'max:2000']]));

        return back()->with('ok', 'Notes saved.');
    }

    private function authorizeView(User $user, ServiceRequest $sr): void
    {
        abort_if($user->role === User::TECHNICIAN && (int) $sr->technician_id !== $user->id, 403);
    }
}
