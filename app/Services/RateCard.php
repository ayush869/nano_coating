<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Coating;
use App\Models\Design;
use App\Models\Material;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Support\Media;
use Illuminate\Support\Collection;

/**
 * The current rate card and product catalog, loaded once per request.
 * Lookups include inactive rows (so old drafts still resolve); pickers only
 * offer active ones.
 */
class RateCard
{
    public const MASTERS = [
        'coatings' => Coating::class,
        'addons' => Addon::class,
        'materials' => Material::class,
        'designs' => Design::class,
    ];

    /** @var array<string, Collection> keyed by id */
    private array $items = [];

    private Collection $products;

    public Setting $settings;

    public function __construct()
    {
        foreach (self::MASTERS as $key => $class) {
            $query = $class::query()->ordered();
            if ($class === Coating::class) {
                $query->with('materials:id');
            }
            $this->items[$key] = $query->get()->keyBy('id');
        }
        $this->products = Product::with('coatings:id')->ordered()->get()->keyBy('id');
        $this->settings = Setting::current();
    }

    public function find(string $table, $id)
    {
        return $id ? $this->items[$table]->get((int) $id) : null;
    }

    public function product($id): ?Product
    {
        return $id ? $this->products->get((int) $id) : null;
    }

    public function active(string $table): Collection
    {
        return $this->items[$table]->filter->active->values();
    }

    /**
     * Payload for the browser builders (staff quote builder and customer
     * request form).
     */
    public function forBuilder(bool $customer = false): array
    {
        $map = fn (string $table) => $this->active($table)->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'value' => $m->value(),
            'description' => $m->description,
        ])->values();

        $products = $this->products->filter(fn (Product $p) => $p->active && (! $customer || $p->customer_visible));
        $serviceIds = $products->pluck('service_id')->unique();

        return [
            'coatings' => $this->active('coatings')->map(fn (Coating $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'value' => $c->value(),
                'description' => $c->description,
                'materials' => $c->materials->pluck('id')->values(),
            ])->values(),
            'addons' => $map('addons'),
            'materials' => $map('materials'),
            'designs' => $map('designs'),
            'services' => Service::active()->ordered()->whereIn('id', $serviceIds)->get(['id', 'name', 'icon', 'slug'])->values(),
            'products' => $products->map(fn (Product $p) => $this->productPayload($p))->values(),
            'rules' => $this->settings->rules(),
            'visit_charge' => $this->settings->visit_charge,
        ];
    }

    public function productPayload(Product $p): array
    {
        return $p->definition() + [
            'service_id' => $p->service_id,
            'description' => $p->description,
            'image_url' => Media::url($p->image_path),
            'coatings' => $p->coatings->pluck('id')->values(),
            'default_coating_id' => $p->default_coating_id,
            'default_material_id' => $p->default_material_id,
            'default_design_id' => $p->default_design_id,
        ];
    }
}
