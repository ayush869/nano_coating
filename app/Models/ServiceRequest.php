<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A customer's request for a site visit. Carries the customer's own
 * estimate; the technician turns it into a quotation after measuring.
 */
class ServiceRequest extends Model
{
    public const NEW = 'new';
    public const ASSIGNED = 'assigned';
    public const QUOTED = 'quoted';
    public const CLOSED = 'closed';
    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::NEW => 'New',
        self::ASSIGNED => 'Visit scheduled',
        self::QUOTED => 'Quoted',
        self::CLOSED => 'Closed',
        self::CANCELLED => 'Cancelled',
    ];

    public const SLOTS = ['morning' => 'Morning (9–12)', 'afternoon' => 'Afternoon (12–4)', 'evening' => 'Evening (4–7)'];

    protected $fillable = [
        'number', 'customer_id', 'site_id', 'status', 'source', 'preferred_date', 'preferred_slot', 'notes', 'photos',
        'estimate_subtotal', 'estimate_total', 'technician_id', 'visit_at', 'assigned_by', 'assigned_at',
        'staff_notes', 'cancel_reason',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'visit_at' => 'datetime',
        'assigned_at' => 'datetime',
        'photos' => 'array',
        'estimate_subtotal' => 'float',
        'estimate_total' => 'float',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceRequestItem::class)->orderBy('sort');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->latest('id');
    }

    public function latestQuote(): HasOne
    {
        return $this->hasOne(Quote::class)->where('status', '!=', Quote::SUPERSEDED)->latestOfMany();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::NEW, self::ASSIGNED], true);
    }

    /** Every photo on the request: general ones and per item. */
    public function allPhotos(): array
    {
        $photos = $this->photos ?? [];
        foreach ($this->items as $item) {
            $photos = array_merge($photos, $item->photos ?? []);
        }

        return array_values(array_unique($photos));
    }
}
