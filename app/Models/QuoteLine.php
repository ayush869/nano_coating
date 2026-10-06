<?php

namespace App\Models;

use App\Services\Pricing\Formula;
use App\Services\Pricing\PricingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLine extends Model
{
    protected $fillable = [
        'quote_id', 'sort', 'product_id', 'product_name', 'product_snapshot', 'measure_unit', 'name', 'qty', 'inputs',
        'coating_id', 'coating_name', 'coating_rate', 'addon_id', 'addon_name', 'addon_rate',
        'material_id', 'material_name', 'material_factor', 'design_id', 'design_name', 'design_factor',
        'area', 'rate', 'raw_amount', 'min_applied', 'amount', 'area_text', 'formula', 'photos',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'inputs' => 'array',
        'photos' => 'array',
        'qty' => 'float',
        'coating_rate' => 'float',
        'addon_rate' => 'float',
        'material_factor' => 'float',
        'design_factor' => 'float',
        'area' => 'float',
        'rate' => 'float',
        'raw_amount' => 'float',
        'min_applied' => 'boolean',
        'amount' => 'float',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** Human specification for the quote table / PDF. */
    public function specification(): string
    {
        $snap = $this->product_snapshot ?? [];
        if (! ($snap['uses_coating'] ?? true)) {
            return $this->product_name.' @ '.PricingService::money($this->rate, false).'/'.$this->measure_unit;
        }
        $coat = $this->coating_name.($this->addon_name ? ' + '.$this->addon_name : '');
        $parts = array_filter([$coat, $this->material_name, $this->design_name]);
        if ($this->product_name !== $this->name) {
            array_unshift($parts, $this->product_name);
        }

        return implode(' · ', $parts);
    }

    /** "Seats: 3 · Back sections: 2 · Length: 72 in" from the product's fields. */
    public function inputSummary(): string
    {
        return self::summariseInputs($this->product_snapshot['fields'] ?? [], $this->inputs ?? []);
    }

    public static function summariseInputs(array $fields, array $inputs): string
    {
        $out = [];
        foreach ($fields as $f) {
            $v = $inputs[$f['key']] ?? ($f['default'] ?? null);
            if ($v === null || $v === '') {
                continue;
            }
            if ($f['type'] === 'counter' && (float) $v == 0) {
                continue;
            }
            if ($f['type'] === 'select') {
                $label = collect($f['options'] ?? [])->first(fn ($o) => (float) $o['value'] == (float) $v)['label'] ?? $v;
                $out[] = $f['label'].': '.$label;
            } elseif ($f['type'] === 'dimension') {
                $out[] = $f['label'].': '.Formula::fmt((float) $v).' '.($inputs[$f['key'].'__unit'] ?? $f['unit'] ?? 'ft');
            } else {
                $out[] = $f['label'].': '.Formula::fmt((float) $v);
            }
        }

        return implode(' · ', $out);
    }
}
