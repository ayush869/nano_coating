<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Services\RateCard;
use App\Services\RequestService;
use App\Support\Media;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function create(Request $request)
    {
        $customer = $request->user('customer');
        if ($customer->name === '') {
            return redirect()->route('portal.welcome');
        }
        abort_unless(Setting::current()->portal_enabled, 503, 'Online requests are paused. Please call us.');

        return view('portal.requests.create', [
            'card' => (new RateCard)->forBuilder(customer: true),
            'sites' => $customer->sites->map(fn ($s) => [
                'id' => $s->id, 'label' => $s->label, 'address' => $s->fullAddress(), 'state_code' => $s->state_code,
            ]),
            'companyState' => Company::current()->state_code,
            'settings' => Setting::current(),
            'preselect' => [
                'product' => (int) $request->query('product') ?: null,
                'service' => (int) $request->query('service') ?: null,
            ],
        ]);
    }

    public function store(Request $request, RequestService $service)
    {
        $request->validate(['payload' => ['required', 'json']]);
        $sr = $service->create($request->user('customer'), json_decode($request->input('payload'), true));

        return redirect()->route('portal.requests.show', $sr)
            ->with('ok', "Request {$sr->number} received. We will call you to confirm the visit.");
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeOwner($request, $serviceRequest);
        $serviceRequest->load('items', 'site', 'technician', 'latestQuote');

        return view('portal.requests.show', [
            'sr' => $serviceRequest,
            'photoUrl' => fn ($p) => Media::url($p),
        ]);
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeOwner($request, $serviceRequest);
        if (! $serviceRequest->isOpen()) {
            return back()->with('error', 'This request can no longer be cancelled online. Please call us.');
        }
        $serviceRequest->update([
            'status' => ServiceRequest::CANCELLED,
            'cancel_reason' => mb_substr((string) $request->input('reason', 'Cancelled by customer'), 0, 250),
        ]);

        return back()->with('ok', 'Request cancelled.');
    }

    private function authorizeOwner(Request $request, ServiceRequest $sr): void
    {
        abort_unless($sr->customer_id === $request->user('customer')->id, 404);
    }
}
