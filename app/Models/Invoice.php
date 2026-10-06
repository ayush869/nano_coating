<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tax invoice issued from an accepted quote. Amounts, lines and customer
 * details come from the quote's snapshot, which is frozen once converted.
 */
class Invoice extends Model
{
    protected $fillable = ['number', 'quote_id', 'service_job_id', 'date', 'created_by'];

    protected $casts = ['date' => 'date'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
