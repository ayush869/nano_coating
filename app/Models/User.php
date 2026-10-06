<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ADMIN = 'admin';
    public const SALES = 'sales';
    public const TECHNICIAN = 'technician';

    public const ROLES = [self::ADMIN => 'Admin', self::SALES => 'Sales', self::TECHNICIAN => 'Technician'];

    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'active'];

    protected $attributes = ['role' => self::SALES, 'active' => true];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }

    /** Maximum discount (% of subtotal) this user may give without approval. */
    public function discountLimit(): float
    {
        $s = Setting::current();

        return match ($this->role) {
            self::ADMIN => (float) $s->discount_limit_admin,
            self::SALES => (float) $s->discount_limit_sales,
            default => 0.0,
        };
    }
}
