<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared behaviour for rate-card masters. Rows are deactivated, never deleted.
 */
trait RateCardItem
{
    use Auditable;

    public function initializeRateCardItem(): void
    {
        $this->mergeFillable(['name', static::VALUE_COLUMN, 'description', 'sort', 'active']);
        $this->mergeCasts([static::VALUE_COLUMN => 'float', 'active' => 'boolean', 'sort' => 'integer']);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort')->orderBy('name');
    }

    public function value(): float
    {
        return (float) $this->{static::VALUE_COLUMN};
    }
}
