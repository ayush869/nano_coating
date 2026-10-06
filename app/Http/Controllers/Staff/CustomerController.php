<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;

use App\Models\Customer;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::search($request->query('q'))
            ->withCount('sites', 'quotes')->orderBy('name')->paginate(25)->withQueryString();

        return view('staff.customers.index', ['customers' => $customers, 'q' => $request->query('q')]);
    }

    /** JSON for the quote builder's customer picker. */
    public function search(Request $request)
    {
        return Customer::search($request->query('q'))->with('sites')->orderBy('name')->limit(10)->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'email' => $c->email,
                'sites' => $c->sites->map(fn (Site $s) => [
                    'id' => $s->id,
                    'label' => $s->label,
                    'address' => $s->fullAddress(),
                    'state_code' => $s->state_code,
                ]),
            ]);
    }

    public function create()
    {
        return view('staff.customers.form', ['customer' => new Customer]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $site = $request->validate($this->siteRules());

        $customer = Customer::create($data);
        $customer->sites()->create($site);

        return redirect()->route('customers.show', $customer)->with('ok', 'Customer added.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['sites', 'quotes' => fn ($q) => $q->latest('id')]);

        return view('staff.customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('staff.customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('ok', 'Customer updated.');
    }

    public function storeSite(Request $request, Customer $customer)
    {
        $customer->sites()->create($request->validate($this->siteRules()));

        return back()->with('ok', 'Site added.');
    }

    public function updateSite(Request $request, Site $site)
    {
        $site->update($request->validate($this->siteRules()));

        return back()->with('ok', 'Site updated.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'size:15'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['phone'] = Customer::normalisePhone($data['phone']);
        if (strlen($data['phone']) < 10) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid phone number.']);
        }
        if (Customer::where('phone', $data['phone'])->when($customer, fn ($q) => $q->whereKeyNot($customer->id))->exists()) {
            throw ValidationException::withMessages(['phone' => 'Another customer already has this phone number.']);
        }

        return $data;
    }

    private function siteRules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'state_code' => ['required', Rule::in(array_keys(config('gst.states')))],
        ];
    }
}
