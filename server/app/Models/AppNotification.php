<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use HasUuids;
    use SerializesToCamelCase;

    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'order_id',
        'event',
        'payload',
        'sent',
    ];

    protected function casts(): array
    {
        return [
            'sent' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
