<?php

namespace App\Models;

use App\Models\Concerns\AssignsSequentialNumber;
use App\Support\SerializesToCamelCase;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use AssignsSequentialNumber;
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
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

    public function sequentialNumberColumn(): string
    {
        return 'user_number';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
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
