<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = ['phone', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'ip'];

    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'attempts' => 'integer'];
}
