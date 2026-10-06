<?php

namespace App\Services;

use App\Models\Product;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\Storage;

/**
 * Turns one item from a browser payload into a snapshot row plus the
 * pricing-engine input. Used by the staff quote builder and the customer
 * request form. Never trusts prices from the browser.
 *
 * Snapshot rule: when $existing (a previously saved line) used the same
 * product / coating / add-on / material / design, its stored definition and
 * rates are kept; anything newly picked uses today's rate card.
 */
class LineResolver
{
    /** @var array<string, string> */
    public array $errors = [];

    public function __construct(private RateCard $card, private bool $customer = false) {}

    /**
     * @param  array|null  $existing  stored row (product_id, product_snapshot, coating_id, coating_rate, …)
     * @return array{row: array, pricing: array}|null
     */
    public function resolve(array $in, ?array $existing, int $n): ?array
    {
        $errorsBefore = count($this->errors);

        // ---- product
        $productId = (int) ($in['product_id'] ?? 0);
        $sameProduct = $existing && (int) ($existing['product_id'] ?? 0) === $productId && ! empty($existing['product_snapshot']);
        if ($sameProduct) {
            $def = $existing['product_snapshot'];
            $productName = $existing['product_name'];
            $product = $this->card->product($productId);
        } else {
            $product = $this->card->product($productId);
            if (! $product || ! $product->active || ($this->customer && ! $product->customer_visible)) {
                $this->errors["lines.$n.product_id"] = "Item $n: choose a product.";

                return null;
            }
            $def = $product->definition();
            $productName = $product->name;
            $existing = null; // a different product: no snapshot reuse at all
        }

        $qty = (float) ($in['qty'] ?? 0);
        if ($qty <= 0 || $qty > 10000) {
            $this->errors["lines.$n.qty"] = "Item $n: quantity must be between 1 and 10,000.";
        }

        $inputs = $this->inputs($def, (array) ($in['inputs'] ?? []), $n);

        $row = [
            'product_id' => $productId,
            'product_name' => $productName,
            'product_snapshot' => $def,
            'measure_unit' => $def['measure_unit'] ?? 'sq ft',
            'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 200) ?: $productName,
            'qty' => $qty,
            'inputs' => $inputs,
            'photos' => $this->photos($in['photos'] ?? [], $n),
        ];

        // ---- coating, add-on, material, design
        $sel = ['coating_rate' => 0, 'addon_rate' => 0, 'material_factor' => 1, 'design_factor' => 1];
        foreach (['coating', 'addon', 'material', 'design'] as $kind) {
            $row[$kind.'_id'] = $row[$kind.'_name'] = null;
            $row[$kind === 'coating' || $kind === 'addon' ? $kind.'_rate' : $kind.'_factor'] = null;
        }

        if ($def['uses_coating'] ?? true) {
            $coating = $this->pick('coatings', $in['coating_id'] ?? null, $existing['coating_id'] ?? null, $n, 'coating', true);
            $material = $this->pick('materials', $in['material_id'] ?? null, $existing['material_id'] ?? null, $n, 'material', false);
            $design = $this->pick('designs', $in['design_id'] ?? null, $existing['design_id'] ?? null, $n, 'design', false);
            $addon = $this->pick('addons', $in['addon_id'] ?? null, $existing['addon_id'] ?? null, $n, 'add-on', false);

            $coatingChanged = ! $existing || (int) ($existing['coating_id'] ?? 0) !== $coating?->id;
            if ($coating && $coatingChanged && $product && ! $product->allowsCoating($coating->id)) {
                $this->errors["lines.$n.coating_id"] = "Item $n: {$coating->name} is not offered for {$productName}.";
            }
            $materialChanged = ! $existing || (int) ($existing['material_id'] ?? 0) !== $material?->id;
            if ($coating && $material && ($coatingChanged || $materialChanged) && ! $coating->allowsMaterial($material->id)) {
                $this->errors["lines.$n.material_id"] = "Item $n: {$coating->name} cannot be used on {$material->name}.";
            }

            foreach (['coating' => $coating, 'addon' => $addon, 'material' => $material, 'design' => $design] as $kind => $m) {
                if (! $m) {
                    continue;
                }
                $col = $kind === 'coating' || $kind === 'addon' ? $kind.'_rate' : $kind.'_factor';
                $same = $existing && (int) ($existing[$kind.'_id'] ?? 0) === $m->id;
                $row[$kind.'_id'] = $m->id;
                $row[$kind.'_name'] = $same ? $existing[$kind.'_name'] : $m->name;
                $row[$col] = $same ? (float) $existing[$col] : $m->value();
            }
            $sel = [
                'coating_rate' => (float) ($row['coating_rate'] ?? 0),
                'addon_rate' => (float) ($row['addon_rate'] ?? 0),
                'material_factor' => (float) ($row['material_factor'] ?? 1),
                'design_factor' => (float) ($row['design_factor'] ?? 1),
            ];
        }

        $pricing = $sel + ['product' => $def, 'inputs' => $inputs, 'qty' => $qty];

        if (count($this->errors) === $errorsBefore) {
            $r = PricingService::line($pricing, ['min_item' => 0]);
            if ($r['error']) {
                $this->errors["lines.$n"] = "Item $n: ".$r['error'];
            } elseif ($r['area'] <= 0) {
                $this->errors["lines.$n"] = "Item $n: the measured area is 0 {$row['measure_unit']}. Check the sizes.";
            }
        }

        return ['row' => $row, 'pricing' => $pricing];
    }

    /** Keep only known inputs, as numbers; dimension units must be known. */
    private function inputs(array $def, array $in, int $n): array
    {
        $out = [];
        foreach ($def['fields'] ?? [] as $f) {
            $key = $f['key'];
            $v = $in[$key] ?? null;
            if ($v === null || $v === '') {
                $v = $f['default'] ?? ($f['type'] === 'select' ? ($f['options'][0]['value'] ?? 0) : 0);
            }
            if (! is_numeric($v)) {
                $this->errors["lines.$n.inputs.$key"] = "Item $n: {$f['label']} must be a number.";
                $v = 0;
            }
            $v = (float) $v;

            if ($f['type'] === 'select' && ! collect($f['options'] ?? [])->contains(fn ($o) => (float) $o['value'] == $v)) {
                $this->errors["lines.$n.inputs.$key"] = "Item $n: choose a valid {$f['label']}.";
            }
            if (in_array($f['type'], ['counter', 'dimension', 'number'], true) && $v < ($f['min'] ?? 0)) {
                $this->errors["lines.$n.inputs.$key"] = "Item $n: {$f['label']} cannot be below ".($f['min'] ?? 0).'.';
            }
            if (isset($f['max']) && $v > $f['max']) {
                $this->errors["lines.$n.inputs.$key"] = "Item $n: {$f['label']} cannot be above {$f['max']}.";
            }
            if ($f['type'] === 'counter' && floor($v) != $v) {
                $this->errors["lines.$n.inputs.$key"] = "Item $n: {$f['label']} must be a whole number.";
            }
            $out[$key] = $v;

            if ($f['type'] === 'dimension') {
                $unit = $in[$key.'__unit'] ?? ($f['unit'] ?? 'ft');
                $out[$key.'__unit'] = isset(PricingService::UNIT_TO_FT[$unit]) ? $unit : 'ft';
            }
        }

        return $out;
    }

    /**
     * A master row by id. Must be active, unless the line already used it
     * (an old draft may keep a since-deactivated rate).
     */
    private function pick(string $table, $id, $existingId, int $n, string $label, bool $required)
    {
        if (empty($id)) {
            if ($required) {
                $this->errors["lines.$n.$table"] = "Item $n: choose a $label.";
            }

            return null;
        }
        $item = $this->card->find($table, $id);
        if (! $item || (! $item->active && $item->id !== (int) $existingId)) {
            $this->errors["lines.$n.$table"] = "Item $n: choose a $label.";

            return null;
        }

        return $item;
    }

    private function photos($paths, int $n): array
    {
        $out = [];
        foreach (array_slice((array) $paths, 0, 8) as $path) {
            if (! is_string($path) || ! preg_match('#^photos/[A-Za-z0-9._-]+$#', $path) || ! Storage::disk('public')->exists($path)) {
                $this->errors["lines.$n.photos"] = "Item $n: a photo was not found, please upload it again.";

                continue;
            }
            $out[] = $path;
        }

        return $out;
    }
}
