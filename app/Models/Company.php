<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use Auditable;

    protected $table = 'company';

    protected $fillable = ['name', 'tagline', 'logo_path', 'gstin', 'address', 'state_code', 'phone', 'whatsapp', 'email', 'bank_details', 'terms', 'signatory'];

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }

    public function stateName(): string
    {
        return config('gst.states')[$this->state_code] ?? $this->state_code;
    }
}
