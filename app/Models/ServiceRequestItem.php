<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestItem extends Model
{
    protected $fillable = [
        'service_request_id', 'sort', 'product_id', 'product_name', 'product_snapshot', 'name', 'qty', 'inputs',
        'coating_id', 'addon_id', 'material_id', 'design_id', 'estimate_area', 'estimate_amount', 'area_text',
        'photos', 'notes',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'inputs' => 'array',
        'photos' => 'array',
        'qty' => 'float',
        'estimate_area' => 'float',
        'estimate_amount' => 'float',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
