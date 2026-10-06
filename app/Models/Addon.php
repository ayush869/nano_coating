<?php

namespace App\Models;

use App\Models\Concerns\RateCardItem;
use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    use RateCardItem;

    public const VALUE_COLUMN = 'rate_per_sqft';
}
