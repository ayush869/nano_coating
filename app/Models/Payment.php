<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const CREATED = 'created';
    public const PAID = 'paid';
    public const FAILED = 'failed';

    public const PURPOSES = ['advance' => 'Advance', 'full' => 'Full payment', 'balance' => 'Balance', 'part' => 'Part payment'];

    /** Gateways customers pay through online. */
    public const ONLINE = ['razorpay', 'test'];

    /** Ways staff can record a payment received offline. */
    public const OFFLINE = ['cash' => 'Cash', 'upi' => 'UPI (direct)', 'bank' => 'Bank transfer', 'cheque' => 'Cheque'];

    protected $fillable = [
        'number', 'quote_id', 'customer_id', 'purpose', 'amount', 'currency', 'status', 'gateway',
        'gateway_order_id', 'gateway_payment_id', 'method', 'reference', 'failure_reason', 'meta',
        'recorded_by', 'paid_at',
    ];

    protected $attributes = ['currency' => 'INR', 'status' => self::CREATED];

    protected $casts = ['amount' => 'float', 'meta' => 'array', 'paid_at' => 'datetime'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopePaid(Builder $q): Builder
    {
        return $q->where('status', self::PAID);
    }

    public function getPurposeLabelAttribute(): string
    {
        return self::PURPOSES[$this->purpose] ?? $this->purpose;
    }

    public function getGatewayLabelAttribute(): string
    {
        return ['razorpay' => 'Razorpay', 'test' => 'Test gateway'][$this->gateway] ?? (self::OFFLINE[$this->gateway] ?? $this->gateway);
    }
}
