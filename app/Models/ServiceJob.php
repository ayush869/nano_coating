<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceJob extends Model
{
    public const STATUSES = ['scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'done' => 'Done'];

    protected $fillable = ['quote_id', 'technician_id', 'scheduled_on', 'status', 'notes', 'photos', 'completed_at'];

    protected $attributes = ['status' => 'scheduled'];

    protected $casts = ['scheduled_on' => 'date', 'completed_at' => 'datetime', 'photos' => 'array'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
