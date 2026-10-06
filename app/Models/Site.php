<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Site extends Model
{
    protected $fillable = ['customer_id', 'label', 'address', 'city', 'pincode', 'state_code'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stateName(): string
    {
        return config('gst.states')[$this->state_code] ?? $this->state_code;
    }

    public function fullAddress(): string
    {
        return collect([$this->address, trim($this->city.' '.$this->pincode), $this->stateName()])
            ->filter()->implode(', ');
    }
}
