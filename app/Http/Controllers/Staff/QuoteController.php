<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\QuoteBuilder;
use App\Services\QuoteWorkflow;
use App\Services\RateCard;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin and sales see every quote. Technicians create and send quotes for
 * the visits assigned to them, and see only those.
 */
class QuoteController extends Controller
{
    public function index(Request $request)
    {
        Quote::expireOverdue();
        $user = $request->user();
        $status = $request->query('status');
        $term = trim((string) $request->query('q'));

        $quotes = Quote::with('customer')
            ->when($user->role === User::TECHNICIAN, fn ($q) => $q->where(fn ($w) => $w->where('created_by', $user->id)
                ->orWhereHas('serviceRequest', fn ($r) => $r->where('technician_id', $user->id))))
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->current())
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%$term%")
                ->orWhereHas('customer', fn ($c) => $c->search($term))))
            ->latest('id')->paginate(25)->withQueryString();

        return view('staff.quotes.index', compact('quotes', 'status', 'term'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $sr = $request->query('request') ? ServiceRequest::with('items', 'customer.sites')->find($request->query('request')) : null;
        if ($user->role === User::TECHNICIAN) {
            abort_unless($sr && (int) $sr->technician_id === $user->id, 403, 'Technicians quote from their assigned visits.');
        }
        if ($sr && $sr->latestQuote && $sr->latestQuote->status !== Quote::REJECTED) {
            return redirect()->route('quotes.show', $sr->latestQuote)->with('error', 'This visit already has a quotation. Revise it instead.');
        }

        $customer = $sr?->customer
            ?? ($request->query('customer_id') ? Customer::with('sites')->find($request->query('customer_id')) : null);

        return $this->builder(null, $customer, $sr);
    }

    public function store(Request $request)
    {
        $data = $this->payload($request);
        $user = $request->user();
        if ($user->role === User::TECHNICIAN) {
            $sr = ServiceRequest::find($data['service_request_id'] ?? null);
            abort_unless($sr && (int) $sr->technician_id === $user->id, 403);
            $data['customer_id'] = $sr->customer_id;
            unset($data['new_customer']);
        }

        $quote = (new QuoteBuilder)->save($data, $user);

        return redirect()->route('quotes.show', $quote)->with('ok', "Quote {$quote->display_number} saved.");
    }

    public function show(Request $request, Quote $quote, PaymentService $payments)
    {
        $this->authorizeQuote($request->user(), $quote);
        $quote->load('lines', 'customer', 'site', 'creator', 'approver', 'serviceJob.technician', 'invoice', 'revisions',
            'serviceRequest', 'payments.recorder');

        return view('staff.quotes.show', [
            'quote' => $quote,
            'company' => Company::current(),
            'technicians' => User::where('role', User::TECHNICIAN)->where('active', true)->orderBy('name')->get(),
            'onlineOptions' => $payments->options($quote),
        ]);
    }

    public function edit(Request $request, Quote $quote)
    {
        $this->authorizeQuote($request->user(), $quote);
        if (! $quote->isEditable()) {
            return redirect()->route('quotes.show', $quote)
                ->with('error', 'Only draft quotes can be edited. Make a revision instead.');
        }

        return $this->builder($quote, $quote->customer()->with('sites')->first(), $quote->serviceRequest);
    }

    public function update(Request $request, Quote $quote)
    {
        $this->authorizeQuote($request->user(), $quote);
        abort_unless($quote->isEditable(), 403, 'Only draft quotes can be edited.');
        $data = $this->payload($request);
        if ($request->user()->role === User::TECHNICIAN) {
            $data['customer_id'] = $quote->customer_id;
            unset($data['new_customer']);
        }

        $quote = (new QuoteBuilder)->save($data, $request->user(), $quote);

        return redirect()->route('quotes.show', $quote)->with('ok', "Quote {$quote->display_number} updated.");
    }

    public function status(Request $request, Quote $quote, QuoteWorkflow $workflow)
    {
        $this->authorizeQuote($request->user(), $quote);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Quote::STATUSES))],
            'reason' => ['nullable', 'string', 'max:250'],
        ]);
        $to = $data['status'];

        if (! $quote->canMoveTo($to)) {
            $why = $quote->awaitingApproval() ? ' The discount is waiting for admin approval.' : '';

            return back()->with('error', "Cannot move a {$quote->status_label} quote to ".Quote::STATUSES[$to].'.'.$why);
        }
        if ($to !== Quote::SENT && $request->user()->role === User::TECHNICIAN) {
            abort(403);
        }

        match ($to) {
            Quote::SENT => $workflow->send($quote),
            Quote::ACCEPTED => $workflow->accept($quote, 'staff'),
            Quote::REJECTED => $workflow->reject($quote, 'staff', $data['reason'] ?? null),
            Quote::EXPIRED => $quote->update(['status' => Quote::EXPIRED, 'decided_at' => now()]),
        };

        return back()->with('ok', 'Quote marked '.Quote::STATUSES[$to].'.');
    }

    public function revise(Request $request, Quote $quote)
    {
        $this->authorizeQuote($request->user(), $quote);
        if (! $quote->canRevise()) {
            return back()->with('error', $quote->amount_paid > 0
                ? 'A payment has been received on this quote, so it cannot be revised.'
                : 'This quote cannot be revised.');
        }
        $new = (new QuoteBuilder)->revise($quote, $request->user());

        return redirect()->route('quotes.edit', $new)->with('ok', "Revision {$new->display_number} created from {$quote->display_number}.");
    }

    public function approve(Request $request, Quote $quote)
    {
        abort_unless($quote->awaitingApproval(), 400);
        $quote->update(['approved_by' => $request->user()->id]);

        return back()->with('ok', 'Discount approved.');
    }

    public function convert(Request $request, Quote $quote, QuoteWorkflow $workflow)
    {
        if ($quote->status !== Quote::ACCEPTED) {
            return back()->with('error', 'Only accepted quotes can be converted to a job.');
        }
        $data = $request->validate([
            'technician_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::TECHNICIAN)],
            'scheduled_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->convertToJob($quote, $data['technician_id'] ?? null, $data['scheduled_on'] ?? null, $data['notes'] ?? null);

        return back()->with('ok', 'Converted to a job.');
    }

    public static function canAccess(User $user, Quote $quote): bool
    {
        if ($user->hasRole(User::ADMIN, User::SALES)) {
            return true;
        }

        return (int) $quote->created_by === $user->id
            || ($quote->serviceRequest && (int) $quote->serviceRequest->technician_id === $user->id);
    }

    private function authorizeQuote(User $user, Quote $quote): void
    {
        abort_unless(self::canAccess($user, $quote), 403);
    }

    private function payload(Request $request): array
    {
        $request->validate(['payload' => ['required', 'json']]);

        return json_decode($request->input('payload'), true);
    }

    private function builder(?Quote $quote, ?Customer $customer, ?ServiceRequest $sr)
    {
        $card = new RateCard;

        $lines = $quote
            ? $quote->lines->map(fn (QuoteLine $l) => $this->lineState($l))->values()->all()
            : ($sr ? QuoteBuilder::linesFromRequest($sr) : []);
        foreach ($lines as &$line) {
            $line['photo_urls'] = array_map(fn ($p) => Media::url($p), $line['photos'] ?? []);
        }

        $state = [
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'sites' => $customer->sites->map(fn ($s) => [
                    'id' => $s->id, 'label' => $s->label, 'address' => $s->fullAddress(), 'state_code' => $s->state_code,
                ]),
            ] : null,
            'site_id' => $quote?->site_id ?? $sr?->site_id ?? $customer?->sites->first()?->id,
            'service_request_id' => $quote?->service_request_id ?? $sr?->id,
            'discount_type' => $quote?->discount_type,
            'discount_value' => $quote?->discount_value ?? 0,
            'visit_charge' => $quote?->visit_charge ?? $card->settings->visit_charge,
            'notes' => $quote?->notes,
            'lines' => $lines,
            // An existing quote keeps the rules it was created with.
            'rules' => $quote ? ['gst_pct' => $quote->gst_pct, 'min_item' => $quote->min_item, 'min_job' => $quote->min_job] : null,
        ];

        return view('staff.quotes.builder', [
            'quote' => $quote,
            'sr' => $sr,
            'card' => $card->forBuilder(),
            'state' => $state,
            'companyState' => Company::current()->state_code,
            'discountLimit' => auth()->user()->discountLimit(),
            'lockCustomer' => (bool) $sr || auth()->user()->role === User::TECHNICIAN,
        ]);
    }

    /** Builder state for a saved line, carrying its snapshot so the preview reuses it. */
    private function lineState(QuoteLine $l): array
    {
        $snap = fn ($id, $name, $value) => $id ? ['id' => (int) $id, 'name' => $name, 'value' => (float) $value] : null;

        return [
            'id' => $l->id,
            'product_id' => $l->product_id,
            'name' => $l->name,
            'qty' => $l->qty,
            'inputs' => $l->inputs,
            'photos' => $l->photos ?? [],
            'coating_id' => $l->coating_id,
            'addon_id' => $l->addon_id,
            'material_id' => $l->material_id,
            'design_id' => $l->design_id,
            'snap' => [
                'product' => $l->product_snapshot + ['id' => $l->product_id, 'name' => $l->product_name],
                'coating' => $snap($l->coating_id, $l->coating_name, $l->coating_rate),
                'addon' => $snap($l->addon_id, $l->addon_name, $l->addon_rate),
                'material' => $snap($l->material_id, $l->material_name, $l->material_factor),
                'design' => $snap($l->design_id, $l->design_name, $l->design_factor),
            ],
        ];
    }
}
