<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Services\Pricing\PricingService;
use App\Services\RateCard;
use App\Support\Media;

/** The public website. */
class SiteController extends Controller
{
    public function home()
    {
        return view('site.home', [
            'company' => Company::current(),
            'settings' => Setting::current(),
            'services' => $this->services()->get(),
        ]);
    }

    public function service(Service $service)
    {
        abort_unless($service->active, 404);
        $service->load(['products' => fn ($q) => $q->active()->where('customer_visible', true)->with('coatings:id')]);
        $card = new RateCard;

        return view('site.service', [
            'company' => Company::current(),
            'service' => $service,
            'services' => $this->services()->get(),
            'products' => $service->products->map(fn (Product $p) => [
                'product' => $p,
                'image' => Media::url($p->image_path) ?? Media::url($service->image_path),
                'typical' => $this->typical($p, $card),
            ]),
        ]);
    }

    /** Price with the product's default inputs and default coating — "typically ₹X". */
    private function typical(Product $p, RateCard $card): ?array
    {
        $line = ['product' => $p->definition(), 'inputs' => [], 'qty' => 1];
        if ($p->uses_coating) {
            $coating = $card->find('coatings', $p->default_coating_id) ?? $card->active('coatings')->first();
            $line += [
                'coating_rate' => $coating?->value() ?? 0,
                'material_factor' => $card->find('materials', $p->default_material_id)?->value() ?? 1,
                'design_factor' => $card->find('designs', $p->default_design_id)?->value() ?? 1,
            ];
        }
        $r = PricingService::line($line, $card->settings->rules());

        return $r['error'] || $r['amount'] <= 0 ? null : ['amount' => $r['amount'], 'area' => $r['area_text']];
    }

    private function services()
    {
        return Service::active()->ordered()
            ->whereHas('products', fn ($q) => $q->active()->where('customer_visible', true))
            ->withCount(['products' => fn ($q) => $q->active()->where('customer_visible', true)]);
    }
}
