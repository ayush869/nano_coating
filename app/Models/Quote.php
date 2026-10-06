<?php

namespace App\Models;

use App\Services\Pricing\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quote extends Model
{
    public const DRAFT = 'draft';
    public const SENT = 'sent';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const EXPIRED = 'expired';
    public const CONVERTED = 'converted';
    public const INVOICED = 'invoiced';
    public const SUPERSEDED = 'superseded';

    public const STATUSES = [
        self::DRAFT => 'Draft',
        self::SENT => 'Sent',
        self::ACCEPTED => 'Accepted',
        self::REJECTED => 'Rejected',
        self::EXPIRED => 'Expired',
        self::CONVERTED => 'Converted to job',
        self::INVOICED => 'Invoiced',
        self::SUPERSEDED => 'Superseded',
    ];

    /** Allowed manual status moves. Converting and invoicing go through their own actions. */
    public const TRANSITIONS = [
        self::DRAFT => [self::SENT],
        self::SENT => [self::ACCEPTED, self::REJECTED, self::EXPIRED],
    ];

    /** Statuses from which a revision (-R1, -R2 ...) can be made. */
    public const REVISABLE = [self::SENT, self::ACCEPTED, self::REJECTED, self::EXPIRED];

    /** Statuses counted as won in reports. */
    public const WON = [self::ACCEPTED, self::CONVERTED, self::INVOICED];

    /** Statuses a customer can see in the portal. */
    public const CUSTOMER_VISIBLE = [self::SENT, self::ACCEPTED, self::REJECTED, self::EXPIRED, self::CONVERTED, self::INVOICED];

    protected $fillable = [
        'number', 'revision', 'customer_id', 'site_id', 'service_request_id', 'date', 'valid_till', 'status',
        'customer_snapshot', 'place_of_supply', 'intra_state', 'gst_pct', 'min_item', 'min_job', 'advance_pct',
        'discount_type', 'discount_value', 'discount_amount', 'visit_charge',
        'subtotal', 'taxable_before_min', 'min_job_adjustment', 'taxable', 'cgst', 'sgst', 'igst', 'total', 'amount_paid',
        'needs_approval', 'approved_by', 'notes', 'superseded_by_id', 'sent_at', 'viewed_at', 'decided_at',
        'decided_by', 'rejection_reason', 'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'valid_till' => 'date',
        'customer_snapshot' => 'array',
        'intra_state' => 'boolean',
        'needs_approval' => 'boolean',
        'revision' => 'integer',
        'gst_pct' => 'float',
        'min_item' => 'float',
        'min_job' => 'float',
        'advance_pct' => 'float',
        'discount_value' => 'float',
        'discount_amount' => 'float',
        'visit_charge' => 'float',
        'subtotal' => 'float',
        'taxable_before_min' => 'float',
        'min_job_adjustment' => 'float',
        'taxable' => 'float',
        'cgst' => 'float',
        'sgst' => 'float',
        'igst' => 'float',
        'total' => 'float',
        'amount_paid' => 'float',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'superseded_by_id');
    }

    public function serviceJob(): HasOne
    {
        return $this->hasOne(ServiceJob::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Quote::class, 'number', 'number')->orderBy('revision');
    }

    public function getDisplayNumberAttribute(): string
    {
        return $this->number.($this->revision > 0 ? '-R'.$this->revision : '');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Excludes revisions that have been replaced by a newer one. */
    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('status', '!=', self::SUPERSEDED);
    }

    public function isEditable(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function canRevise(): bool
    {
        return in_array($this->status, self::REVISABLE, true) && $this->amount_paid <= 0;
    }

    public function canMoveTo(string $status): bool
    {
        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            return false;
        }

        return $status !== self::SENT || ! $this->awaitingApproval();
    }

    public function awaitingApproval(): bool
    {
        return $this->needs_approval && ! $this->approved_by;
    }

    public function discountPct(): float
    {
        return $this->subtotal > 0 ? $this->discount_amount / $this->subtotal * 100 : 0.0;
    }

    // ---------------------------------------------------------------- payments

    public function balance(): float
    {
        return PricingService::round2(max(0, $this->total - $this->amount_paid));
    }

    public function isFullyPaid(): bool
    {
        return $this->total > 0 && $this->balance() <= 0;
    }

    public function paymentStatus(): string
    {
        if ($this->amount_paid <= 0) {
            return 'unpaid';
        }

        return $this->isFullyPaid() ? 'paid' : 'part_paid';
    }

    public function paymentStatusLabel(): string
    {
        return ['unpaid' => 'Unpaid', 'part_paid' => 'Part paid', 'paid' => 'Fully paid'][$this->paymentStatus()];
    }

    public function advanceAmount(): float
    {
        return min($this->balance(), PricingService::advance($this->total, $this->advance_pct));
    }

    /** Can the customer pay online right now? */
    public function payableOnline(): bool
    {
        return in_array($this->status, [self::SENT, self::ACCEPTED, self::CONVERTED, self::INVOICED], true)
            && ! ($this->status === self::SENT && $this->valid_till->isPast() && ! $this->valid_till->isToday())
            && ! $this->awaitingApproval()
            && $this->balance() > 0;
    }

    /** Recalculate amount_paid from paid payments. */
    public function refreshAmountPaid(): void
    {
        $this->update(['amount_paid' => PricingService::round2((float) $this->payments()->paid()->sum('amount'))]);
    }

    // ---------------------------------------------------------------- misc

    /** Mark sent quotes past their validity as expired. */
    public static function expireOverdue(): int
    {
        return static::where('status', self::SENT)
            ->whereDate('valid_till', '<', now()->toDateString())
            ->where('amount_paid', '<=', 0)
            ->update(['status' => self::EXPIRED, 'decided_at' => now()]);
    }

    public function whatsappNumber(): ?string
    {
        $d = preg_replace('/\D/', '', $this->customer_snapshot['phone'] ?? '');
        if ($d === '') {
            return null;
        }

        return strlen($d) === 10 ? '91'.$d : $d;
    }
}
