<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A customer. Also the account behind the customer portal (guard "customer"),
 * signed in with a one-time code sent to their phone.
 */
class Customer extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'phone', 'email', 'gstin', 'notes', 'phone_verified_at', 'last_login_at'];

    protected $hidden = ['remember_token'];

    protected $casts = ['phone_verified_at' => 'datetime', 'last_login_at' => 'datetime'];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** OTP login has no password. */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    /** Search by phone (digits anywhere) or name. */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }
        $digits = preg_replace('/\D/', '', $term);

        return $q->where(function ($w) use ($term, $digits) {
            if (strlen($digits) >= 3) {
                $w->orWhere('phone', 'like', '%'.substr($digits, -10).'%');
            }
            $w->orWhere('name', 'like', '%'.$term.'%');
        });
    }

    /** Store phones as plain digits; drop a leading 91/0 on 10-digit Indian mobiles. */
    public static function normalisePhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        } elseif (strlen($d) === 11 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }

        return $d;
    }
}
