<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryPartner extends Model
{
    use HasFactory;
    use HasUuids;
    use SerializesToCamelCase;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
    ];

    public function assignments()
    {
        return $this->hasMany(DeliveryAssignment::class);
    }
}
