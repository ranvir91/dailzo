<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePincode extends Model
{
    use HasFactory;
    use SerializesToCamelCase;

    protected $fillable = [
        'pincode',
        'area_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
