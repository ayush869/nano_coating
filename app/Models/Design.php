<?php

namespace App\Models;

use App\Models\Concerns\RateCardItem;
use Illuminate\Database\Eloquent\Model;

class Design extends Model
{
    use RateCardItem;

    public const VALUE_COLUMN = 'factor';
}
