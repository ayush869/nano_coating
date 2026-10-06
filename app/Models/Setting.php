<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable;

    protected $fillable = [
        'gst_pct', 'min_item', 'min_job', 'visit_charge', 'valid_days', 'discount_limit_sales', 'discount_limit_admin',
        'allow_advance', 'advance_pct', 'allow_full_payment', 'auto_job_on_payment', 'auto_invoice_on_done',
        'portal_enabled', 'estimate_note',
    ];

    protected $casts = [
        'gst_pct' => 'float',
        'min_item' => 'float',
        'min_job' => 'float',
        'visit_charge' => 'float',
        'valid_days' => 'integer',
        'discount_limit_sales' => 'float',
        'discount_limit_admin' => 'float',
        'allow_advance' => 'boolean',
        'advance_pct' => 'float',
        'allow_full_payment' => 'boolean',
        'auto_job_on_payment' => 'boolean',
        'auto_invoice_on_done' => 'boolean',
        'portal_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }

    /** Rules passed to the pricing engine. */
    public function rules(): array
    {
        return ['gst_pct' => $this->gst_pct, 'min_item' => $this->min_item, 'min_job' => $this->min_job];
    }
}
