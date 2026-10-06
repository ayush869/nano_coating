<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\ServiceRequest;
use App\Models\Site;
use App\Models\User;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the staff builder payload into a saved quote. The server always
 * re-prices: browser totals are ignored. Everything used to price a line is
 * snapshotted onto it (see LineResolver for the snapshot rule).
 */
class QuoteBuilder
{
    private RateCard $card;

    public function __construct(?RateCard $card = null)
    {
        $this->card = $card ?? new RateCard;
    }

    public function save(array $data, User $user, ?Quote $quote = null): Quote
    {
        $refresh = ! empty($data['refresh_rates']);
        $existingLines = ($quote && ! $refresh)
            ? $quote->lines->keyBy('id')->map(fn (QuoteLine $l) => $l->toArray())
            : collect();

        [$customer, $site] = $this->resolveCustomer($data);

        $request = null;
        if (! $quote && ! empty($data['service_request_id'])) {
            $request = ServiceRequest::where('customer_id', $customer->id)->find($data['service_request_id']);
        }

        $resolver = new LineResolver($this->card);
        $lines = [];
        foreach (array_values($data['lines'] ?? []) as $i => $input) {
            $existing = isset($input['id']) ? $existingLines->get((int) $input['id']) : null;
            if ($r = $resolver->resolve((array) $input, $existing, $i + 1)) {
                $lines[] = $r;
            }
        }
        $errors = $resolver->errors;
        if (! $lines && ! $errors) {
            $errors['lines'] = 'Add at least one item.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $settings = $this->card->settings;
        $rules = ($quote && ! $refresh)
            ? ['gst_pct' => $quote->gst_pct, 'min_item' => $quote->min_item, 'min_job' => $quote->min_job]
            : $settings->rules();

        $intra = $site->state_code === Company::current()->state_code;
        $discountType = in_array($data['discount_type'] ?? null, ['percent', 'flat'], true) ? $data['discount_type'] : null;
        $options = [
            'discount_type' => $discountType,
            'discount_value' => $discountType ? max(0, (float) ($data['discount_value'] ?? 0)) : 0,
            'visit_charge' => max(0, (float) ($data['visit_charge'] ?? 0)),
            'intra_state' => $intra,
        ];

        $priced = PricingService::quote(array_column($lines, 'pricing'), $options, $rules);

        $needsApproval = $priced['discount_pct'] > $user->discountLimit() + 1e-9;
        $approvedBy = null;
        if ($needsApproval && $quote && $quote->approved_by
            && $quote->discount_type === $options['discount_type']
            && (float) $quote->discount_value === (float) $options['discount_value']) {
            $approvedBy = $quote->approved_by; // unchanged discount stays approved
        }

        $date = $quote?->date ?? Carbon::parse($data['date'] ?? now())->startOfDay();

        $attributes = [
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'customer_snapshot' => self::customerSnapshot($customer, $site),
            'place_of_supply' => $site->state_code,
            'intra_state' => $intra,
            'gst_pct' => $rules['gst_pct'],
            'min_item' => $rules['min_item'],
            'min_job' => $rules['min_job'],
            'discount_type' => $options['discount_type'],
            'discount_value' => $options['discount_value'],
            'discount_amount' => $priced['discount_amount'],
            'visit_charge' => $priced['visit_charge'],
            'subtotal' => $priced['subtotal'],
            'taxable_before_min' => $priced['taxable_before_min'],
            'min_job_adjustment' => $priced['min_job_adjustment'],
            'taxable' => $priced['taxable'],
            'cgst' => $priced['cgst'],
            'sgst' => $priced['sgst'],
            'igst' => $priced['igst'],
            'total' => $priced['total'],
            'needs_approval' => $needsApproval,
            'approved_by' => $approvedBy,
            'notes' => isset($data['notes']) ? mb_substr((string) $data['notes'], 0, 2000) : null,
        ];
        if (! $quote || $refresh) {
            $attributes['advance_pct'] = $settings->allow_advance ? $settings->advance_pct : 0;
        }

        return DB::transaction(function () use ($quote, $attributes, $lines, $priced, $user, $date, $settings, $request) {
            if (! $quote) {
                $quote = Quote::create($attributes + [
                    'number' => DocumentNumber::next('QT', $date),
                    'revision' => 0,
                    'service_request_id' => $request?->id,
                    'date' => $date,
                    'valid_till' => $date->copy()->addDays($settings->valid_days),
                    'status' => Quote::DRAFT,
                    'created_by' => $user->id,
                ]);
            } else {
                $quote->update($attributes);
                $quote->lines()->delete();
            }

            foreach ($lines as $i => $line) {
                $r = $priced['lines'][$i];
                $quote->lines()->create($line['row'] + [
                    'sort' => $i + 1,
                    'area' => $r['area'],
                    'rate' => $r['rate'],
                    'raw_amount' => $r['raw_amount'],
                    'min_applied' => $r['min_applied'],
                    'amount' => $r['amount'],
                    'area_text' => mb_substr($r['area_text'], 0, 500),
                    'formula' => mb_substr($r['formula'], 0, 1000),
                ]);
            }

            return $quote->fresh('lines');
        });
    }

    /** Copy a quote into a new draft revision (-R1, -R2 ...) and supersede the old one. */
    public function revise(Quote $quote, User $user): Quote
    {
        return DB::transaction(function () use ($quote, $user) {
            $nextRevision = Quote::where('number', $quote->number)->max('revision') + 1;
            $today = now()->startOfDay();

            $new = $quote->replicate(['status', 'sent_at', 'viewed_at', 'decided_at', 'decided_by', 'rejection_reason',
                'superseded_by_id', 'created_by', 'amount_paid']);
            $new->fill([
                'revision' => $nextRevision,
                'status' => Quote::DRAFT,
                'date' => $today,
                'valid_till' => $today->copy()->addDays($this->card->settings->valid_days),
                'created_by' => $user->id,
                'amount_paid' => 0,
            ])->save();

            foreach ($quote->lines as $line) {
                $copy = $line->replicate();
                $copy->quote_id = $new->id;
                $copy->save();
            }

            $quote->update(['status' => Quote::SUPERSEDED, 'superseded_by_id' => $new->id]);

            return $new;
        });
    }

    /** Builder lines prefilled from a customer's service request. */
    public static function linesFromRequest(ServiceRequest $request): array
    {
        return $request->items->map(fn ($item) => [
            'id' => null,
            'product_id' => $item->product_id,
            'name' => $item->name ?: $item->product_name,
            'qty' => $item->qty,
            'inputs' => $item->inputs,
            'coating_id' => $item->coating_id,
            'addon_id' => $item->addon_id,
            'material_id' => $item->material_id,
            'design_id' => $item->design_id,
            'photos' => $item->photos ?? [],
        ])->values()->all();
    }

    public static function customerSnapshot(Customer $customer, Site $site): array
    {
        return [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'gstin' => $customer->gstin,
            'site_label' => $site->label,
            'address' => $site->fullAddress(),
            'state_code' => $site->state_code,
            'state_name' => $site->stateName(),
        ];
    }

    private function resolveCustomer(array $data): array
    {
        $states = config('gst.states');

        if (! empty($data['new_customer']['name'])) {
            $c = $data['new_customer'];
            $s = $data['new_site'] ?? [];
            $phone = Customer::normalisePhone((string) ($c['phone'] ?? ''));
            $errors = array_filter([
                'new_customer.phone' => strlen($phone) < 10 ? 'Enter a valid customer phone number.' : null,
                'new_site.address' => empty($s['address']) ? 'Site address is required.' : null,
                'new_site.state_code' => empty($s['state_code']) || ! isset($states[$s['state_code']])
                    ? 'Choose the site state (place of supply).' : null,
            ]);
            if (! $errors && Customer::where('phone', $phone)->exists()) {
                $errors['new_customer.phone'] = 'A customer with this phone already exists. Search for them instead.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            $customer = Customer::create([
                'name' => mb_substr($c['name'], 0, 255),
                'phone' => $phone,
                'email' => filter_var($c['email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null,
                'gstin' => $c['gstin'] ?? null,
            ]);

            return [$customer, $this->createSite($customer, $s)];
        }

        $customer = Customer::find($data['customer_id'] ?? null);
        if (! $customer) {
            throw ValidationException::withMessages(['customer_id' => 'Choose a customer or add a new one.']);
        }

        if (! empty($data['new_site']['address'])) {
            $s = $data['new_site'];
            if (empty($s['state_code']) || ! isset($states[$s['state_code']])) {
                throw ValidationException::withMessages(['new_site.state_code' => 'Choose the site state (place of supply).']);
            }
            $site = $this->createSite($customer, $s);
        } else {
            $site = Site::where('customer_id', $customer->id)->find($data['site_id'] ?? null);
        }
        if (! $site) {
            throw ValidationException::withMessages(['site_id' => 'Choose a site address for this customer.']);
        }

        return [$customer, $site];
    }

    private function createSite(Customer $customer, array $s): Site
    {
        return $customer->sites()->create([
            'label' => isset($s['label']) ? mb_substr($s['label'], 0, 100) : null,
            'address' => mb_substr($s['address'], 0, 1000),
            'city' => isset($s['city']) ? mb_substr($s['city'], 0, 100) : null,
            'pincode' => isset($s['pincode']) ? mb_substr($s['pincode'], 0, 10) : null,
            'state_code' => $s['state_code'],
        ]);
    }
}
