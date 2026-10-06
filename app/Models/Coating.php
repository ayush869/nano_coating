<?php

namespace App\Models;

use App\Models\Concerns\RateCardItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Coating extends Model
{
    use RateCardItem;

    public const VALUE_COLUMN = 'rate_per_sqft';

    /** Materials this coating may be used on. Empty means any material. */
    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class);
    }

    public function allowsMaterial(?int $materialId): bool
    {
        $allowed = $this->materials->pluck('id');

        return $allowed->isEmpty() || $allowed->contains($materialId);
    }
}
