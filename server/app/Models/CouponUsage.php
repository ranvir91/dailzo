<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    use HasUuids;
    use SerializesToCamelCase;

    protected $fillable = [
        'coupon_id',
        'user_id',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
