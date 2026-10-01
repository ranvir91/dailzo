<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use SerializesToCamelCase;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'gender',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->role === 'ADMIN',
            'octa' => $this->role === 'VENDOR' && ($this->vendorProfile?->is_active ?? false),
            default => false,
        };
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isVendor(): bool
    {
        return $this->role === 'VENDOR';
    }

    public function isDeliveryPartner(): bool
    {
        return $this->role === 'DELIVERY_PARTNER';
    }

    public function vendorProfile()
    {
        return $this->hasOne(Vendor::class);
    }

    public function deliveryPartnerProfile()
    {
        return $this->hasOne(DeliveryPartner::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }
}
