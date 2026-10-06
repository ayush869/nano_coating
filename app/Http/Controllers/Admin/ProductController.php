<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coating;
use App\Models\Design;
use App\Models\Material;
use App\Models\Product;
use App\Models\Service;
use App\Services\Catalog\ProductDefinition;
use App\Services\Catalog\ProductTemplates;
use App\Services\Pricing\PricingService;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The product designer: the admin defines any product with its own inputs,
 * constants and formulas. Products are deactivated, never deleted.
 */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $serviceId = $request->query('service');

        return view('admin.products.index', [
            'services' => Service::ordered()->with(['products' => fn ($q) => $q->with('coatings:id,name')
                ->when($request->query('q'), fn ($w, $t) => $w->where('name', 'like', "%$t%"))])
                ->when($serviceId, fn ($q) => $q->whereKey($serviceId))->get(),
            'allServices' => Service::ordered()->get(),
            'serviceId' => $serviceId,
            'q' => $request->query('q'),
        ]);
    }

    public function create(Request $request)
    {
        $templates = ProductTemplates::all();
        $key = $request->query('template');
        $t = $templates[$key] ?? $templates['parts'];

        $product = new Product([
            'service_id' => $request->query('service') ?? Service::ordered()->value('id'),
            'measure_unit' => $t['measure_unit'],
            'fields' => $t['fields'],
            'constants' => $t['constants'],
            'area_formula' => $t['area_formula'],
            'price_formula' => $t['price_formula'],
            'uses_coating' => $t['uses_coating'],
            'base_rate' => $t['base_rate'] ?? 0,
            'apply_min' => $t['apply_min'] ?? true,
            'customer_visible' => true,
            'active' => true,
            'sort' => 100,
        ]);

        return $this->form($product, $key ? null : $templates);
    }

    public function store(Request $request)
    {
        $product = DB::transaction(function () use ($request) {
            $product = new Product;
            $this->fill($request, $product);

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('ok', "Product “{$product->name}” created. Try it in the preview.");
    }

    public function edit(Product $product)
    {
        return $this->form($product->load('coatings:id'), null);
    }

    public function update(Request $request, Product $product)
    {
        DB::transaction(fn () => $this->fill($request, $product));

        return back()->with('ok', "“{$product->name}” saved. New quotes use it now; saved quotes keep their copy.");
    }

    public function duplicate(Product $product)
    {
        $copy = $product->replicate(['slug']);
        $copy->name = $product->name.' (copy)';
        $copy->slug = $this->uniqueSlug($copy->name, null);
        $copy->active = false;
        $copy->save();
        $copy->coatings()->sync($product->coatings->pluck('id'));

        return redirect()->route('admin.products.edit', $copy)->with('ok', 'Copy created (inactive). Change what differs, then tick Active.');
    }

    private function fill(Request $request, Product $product): void
    {
        $data = $request->validate([
            'service_id' => ['required', 'exists:services,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'definition' => ['required', 'json'],
            'default_coating_id' => ['nullable', 'exists:coatings,id'],
            'default_material_id' => ['nullable', 'exists:materials,id'],
            'default_design_id' => ['nullable', 'exists:designs,id'],
            'coating_ids' => ['nullable', 'array'],
            'coating_ids.*' => ['integer', 'exists:coatings,id'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $definer = new ProductDefinition;
        $def = $definer->normalise(json_decode($data['definition'], true) ?? []);
        if ($definer->fails()) {
            throw ValidationException::withMessages($definer->errors);
        }

        $product->fill($def + [
            'service_id' => $data['service_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'default_coating_id' => $def['uses_coating'] ? ($data['default_coating_id'] ?? null) : null,
            'default_material_id' => $def['uses_coating'] ? ($data['default_material_id'] ?? null) : null,
            'default_design_id' => $def['uses_coating'] ? ($data['default_design_id'] ?? null) : null,
            'customer_visible' => $request->boolean('customer_visible'),
            'active' => $request->boolean('active'),
            'sort' => $data['sort'] ?? 0,
        ]);
        if (! $product->slug) {
            $product->slug = $this->uniqueSlug($data['name'], $product->exists ? $product : null);
        }
        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $product->image_path = $request->file('image')->store('products', 'public');
        }
        $product->save();
        $product->coatings()->sync($def['uses_coating'] ? ($data['coating_ids'] ?? []) : []);
    }

    private function form(Product $product, ?array $templates)
    {
        return view('admin.products.form', [
            'product' => $product,
            'templates' => $templates,
            'services' => Service::ordered()->get(),
            'coatings' => Coating::active()->ordered()->get(),
            'materials' => Material::active()->ordered()->get(),
            'designs' => Design::active()->ordered()->get(),
            'imageUrl' => Media::url($product->image_path),
            'definition' => [
                'measure_unit' => $product->measure_unit,
                'fields' => $product->fields ?? [],
                'constants' => $product->constants ?? [],
                'area_formula' => $product->area_formula,
                'price_formula' => $product->price_formula ?: PricingService::DEFAULT_PRICE_FORMULA,
                'uses_coating' => $product->uses_coating,
                'base_rate' => $product->base_rate,
                'apply_min' => $product->apply_min,
                'min_charge' => $product->min_charge,
            ],
            'reserved' => PricingService::RESERVED,
            'coatingIds' => $product->exists ? $product->coatings->pluck('id')->all() : [],
            'minItem' => \App\Models\Setting::current()->min_item,
        ]);
    }

    private function uniqueSlug(string $name, ?Product $product): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        for ($i = 2; Product::where('slug', $slug)->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
