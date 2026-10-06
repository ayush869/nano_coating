<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A service category shown on the website ("Sofa & upholstery", "Walls"). */
class Service extends Model
{
    use Auditable;

    protected $fillable = ['name', 'slug', 'summary', 'description', 'icon', 'image_path', 'sort', 'active'];

    protected $casts = ['active' => 'boolean', 'sort' => 'integer'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort')->orderBy('name');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort')->orderBy('name');
    }
}
