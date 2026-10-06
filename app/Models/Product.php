<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An admin-defined product: its own inputs (fields), named constants, an area
 * formula and a price formula. See App\Services\Catalog\ProductDefinition.
 */
class Product extends Model
{
    use Auditable;

    protected $fillable = [
        'service_id', 'name', 'slug', 'description', 'image_path', 'measure_unit',
        'fields', 'constants', 'area_formula', 'price_formula',
        'uses_coating', 'base_rate', 'default_coating_id', 'default_material_id', 'default_design_id',
        'apply_min', 'min_charge', 'customer_visible', 'sort', 'active',
    ];

    protected $casts = [
        'fields' => 'array',
        'constants' => 'array',
        'uses_coating' => 'boolean',
        'base_rate' => 'float',
        'apply_min' => 'boolean',
        'min_charge' => 'float',
        'customer_visible' => 'boolean',
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Coatings this product may use. Empty = any coating. */
    public function coatings(): BelongsToMany
    {
        return $this->belongsToMany(Coating::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort')->orderBy('name');
    }

    /** What the pricing engine needs; also what is snapshotted onto quote lines. */
    public function definition(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'measure_unit' => $this->measure_unit,
            'fields' => array_values($this->fields ?? []),
            'constants' => array_values($this->constants ?? []),
            'area_formula' => $this->area_formula,
            'price_formula' => $this->price_formula,
            'uses_coating' => $this->uses_coating,
            'base_rate' => (float) $this->base_rate,
            'apply_min' => $this->apply_min,
            'min_charge' => $this->min_charge,
        ];
    }

    public function allowsCoating(?int $coatingId): bool
    {
        $allowed = $this->coatings->pluck('id');

        return $allowed->isEmpty() || $allowed->contains($coatingId);
    }

    /** Default inputs for a new line (each field's default value / unit). */
    public function defaultInputs(): array
    {
        $inputs = [];
        foreach ($this->fields ?? [] as $f) {
            $inputs[$f['key']] = $f['default'] ?? ($f['type'] === 'select' ? ($f['options'][0]['value'] ?? 0) : 0);
            if ($f['type'] === 'dimension') {
                $inputs[$f['key'].'__unit'] = $f['unit'] ?? 'ft';
            }
        }

        return $inputs;
    }
}
