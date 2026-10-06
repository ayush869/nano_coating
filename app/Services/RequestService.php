<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Site;
use App\Models\User;
use App\Notifications\NewServiceRequest;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Creates service requests from the customer portal, with a server-side
 * estimate that uses exactly the same engine as quotations.
 */
class RequestService
{
    public function __construct(private ?RateCard $card = null)
    {
        $this->card ??= new RateCard;
    }

    public function create(Customer $customer, array $data, string $source = 'portal'): ServiceRequest
    {
        $site = $this->site($customer, $data);

        $resolver = new LineResolver($this->card, customer: $source === 'portal');
        $lines = [];
        foreach (array_values($data['lines'] ?? []) as $i => $in) {
            if ($r = $resolver->resolve((array) $in, null, $i + 1)) {
                $lines[] = $r;
            }
        }
        $errors = $resolver->errors;
        if (! $lines && ! $errors) {
            $errors['lines'] = 'Add at least one item you want coated.';
        }
        if (count($lines) > 50) {
            $errors['lines'] = 'Please add at most 50 items in one request.';
        }
        $date = $data['preferred_date'] ?? null;
        if ($date && (! strtotime($date) || strtotime($date) < strtotime('today'))) {
            $errors['preferred_date'] = 'Choose today or a later date for the visit.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $settings = $this->card->settings;
        $estimate = PricingService::quote(array_column($lines, 'pricing'), [
            'visit_charge' => $settings->visit_charge,
            'intra_state' => $site->state_code === Company::current()->state_code,
        ], $settings->rules());

        $request = DB::transaction(function () use ($customer, $site, $data, $lines, $estimate, $source) {
            $request = ServiceRequest::create([
                'number' => DocumentNumber::next('SR', now()),
                'customer_id' => $customer->id,
                'site_id' => $site->id,
                'status' => ServiceRequest::NEW,
                'source' => $source,
                'preferred_date' => $data['preferred_date'] ?? null,
                'preferred_slot' => array_key_exists($data['preferred_slot'] ?? '', ServiceRequest::SLOTS) ? $data['preferred_slot'] : null,
                'notes' => isset($data['notes']) ? mb_substr((string) $data['notes'], 0, 2000) : null,
                'photos' => $this->photos($data['photos'] ?? []),
                'estimate_subtotal' => $estimate['subtotal'],
                'estimate_total' => $estimate['total'],
            ]);

            foreach ($lines as $i => $line) {
                $r = $estimate['lines'][$i];
                $row = $line['row'];
                $request->items()->create([
                    'sort' => $i + 1,
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'product_snapshot' => $row['product_snapshot'],
                    'name' => $row['name'],
                    'qty' => $row['qty'],
                    'inputs' => $row['inputs'],
                    'coating_id' => $row['coating_id'],
                    'addon_id' => $row['addon_id'],
                    'material_id' => $row['material_id'],
                    'design_id' => $row['design_id'],
                    'estimate_area' => $r['area'],
                    'estimate_amount' => $r['amount'],
                    'area_text' => mb_substr($r['area_text'], 0, 255),
                    'photos' => $row['photos'],
                ]);
            }

            return $request;
        });

        Notification::send(User::where('role', User::ADMIN)->where('active', true)->get(), new NewServiceRequest($request));

        return $request;
    }

    private function site(Customer $customer, array $data): Site
    {
        $s = $data['new_site'] ?? null;
        if ($s && ! empty($s['address'])) {
            if (empty($s['state_code']) || ! isset(config('gst.states')[$s['state_code']])) {
                throw ValidationException::withMessages(['new_site.state_code' => 'Choose your state.']);
            }

            return $customer->sites()->create([
                'label' => isset($s['label']) ? mb_substr($s['label'], 0, 100) : null,
                'address' => mb_substr($s['address'], 0, 1000),
                'city' => isset($s['city']) ? mb_substr($s['city'], 0, 100) : null,
                'pincode' => isset($s['pincode']) ? mb_substr($s['pincode'], 0, 10) : null,
                'state_code' => $s['state_code'],
            ]);
        }

        $site = $customer->sites()->find($data['site_id'] ?? null);
        if (! $site) {
            throw ValidationException::withMessages(['site_id' => 'Add the address where the work is needed.']);
        }

        return $site;
    }

    private function photos($paths): array
    {
        return collect((array) $paths)->take(10)
            ->filter(fn ($p) => is_string($p) && preg_match('#^photos/[A-Za-z0-9._-]+$#', $p) && Storage::disk('public')->exists($p))
            ->values()->all();
    }
}
