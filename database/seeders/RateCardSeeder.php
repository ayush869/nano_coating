<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Coating;
use App\Models\Company;
use App\Models\Design;
use App\Models\Material;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Company profile, rules and the default rate card. Every value is editable
 * in the admin panel; these are illustrative starting values.
 */
class RateCardSeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'name' => 'Shield Nano Coatings',
            'tagline' => 'Invisible protection for everything you love',
            'gstin' => '27ABCDE1234F1Z5',
            'address' => "Unit 12, Example Business Park\nBaner Road, Pune 411045",
            'state_code' => '27',
            'phone' => '+91 98765 43210',
            'whatsapp' => '919876543210',
            'email' => 'hello@example.com',
            'bank_details' => "Bank: Example Bank\nA/c name: Shield Nano Coatings\nA/c no: 000000000000\nIFSC: EXMP0000001",
            'terms' => implode("\n", [
                'Advance as shown confirms your booking; balance is due on completion.',
                'Prices are based on the items and sizes listed; extra items are charged at the same rates.',
                'Surfaces must be dry and free of stains before coating. Stain removal is not included unless listed.',
                'Allow 24 hours curing before use. Avoid water contact for 48 hours.',
                'Warranty as per product card. Damage from abrasion, chemicals or mishandling is not covered.',
            ]),
            'signatory' => 'Authorised Signatory',
        ]);

        Setting::create([
            'gst_pct' => 18,
            'min_item' => 500,
            'min_job' => 2500,
            'visit_charge' => 300,
            'valid_days' => 15,
            'discount_limit_sales' => 10,
            'discount_limit_admin' => 100,
            'allow_advance' => true,
            'advance_pct' => 50,
            'allow_full_payment' => true,
            'auto_job_on_payment' => true,
            'auto_invoice_on_done' => true,
            'portal_enabled' => true,
            'estimate_note' => 'This is an estimate from the sizes you entered. Our technician measures on site and the final quotation may differ.',
        ]);

        $seed = function (string $class, array $rows) {
            $out = [];
            foreach ($rows as $i => [$name, $value, $desc]) {
                $out[$name] = $class::create([
                    'name' => $name, $class::VALUE_COLUMN => $value, 'description' => $desc, 'sort' => ($i + 1) * 10,
                ]);
            }

            return $out;
        };

        $coatings = $seed(Coating::class, [
            ['Fabric protection', 18, 'Water and stain repellent for upholstery, rugs and curtains'],
            ['Leather protection', 25, 'Breathable nano barrier for natural and faux leather'],
            ['Glass protection', 20, 'Hydrophobic, anti-fingerprint coating for glass'],
            ['Wood protection', 22, 'Water and stain shield for polished and natural wood'],
            ['Stone protection', 28, 'Penetrating sealer for marble, granite and tiles'],
            ['Wall protection', 12, 'Washable, anti-stain coating for painted walls'],
        ]);

        $seed(Addon::class, [
            ['Anti-microbial layer', 6, 'Silver-ion layer that inhibits bacteria and odour'],
            ['UV protection layer', 5, 'Reduces fading on sun-facing furniture'],
            ['Extra stain guard', 4, 'Second repellent coat for high-use areas'],
        ]);

        $materials = $seed(Material::class, [
            ['Cotton / linen', 1.00, null],
            ['Polyester / blend', 1.00, null],
            ['Velvet', 1.20, 'High pile absorbs more product'],
            ['Suede / nubuck', 1.20, 'High absorption'],
            ['Leather', 1.10, null],
            ['Faux leather', 1.05, null],
            ['Glass', 1.00, null],
            ['Wood', 1.00, null],
            ['Marble', 1.10, 'Porous stone'],
            ['Granite / quartz', 1.05, null],
            ['Painted wall', 1.00, null],
            ['Textured wall', 1.25, 'Rough texture takes more coating'],
            ['Wallpaper', 1.15, null],
        ]);

        $seed(Design::class, [
            ['Plain', 1.00, null],
            ['Curved', 1.15, null],
            ['Recliner', 1.20, 'Mechanism and folds'],
            ['Tufted / buttoned', 1.25, 'Deep folds take longer'],
            ['Carved', 1.35, 'Detailed woodwork'],
        ]);

        $allow = [
            'Fabric protection' => ['Cotton / linen', 'Polyester / blend', 'Velvet', 'Suede / nubuck'],
            'Leather protection' => ['Leather', 'Faux leather', 'Suede / nubuck'],
            'Glass protection' => ['Glass'],
            'Wood protection' => ['Wood'],
            'Stone protection' => ['Marble', 'Granite / quartz'],
            'Wall protection' => ['Painted wall', 'Textured wall', 'Wallpaper'],
        ];
        foreach ($allow as $coating => $names) {
            $coatings[$coating]->materials()->sync(collect($names)->map(fn ($n) => $materials[$n]->id));
        }
    }
}
