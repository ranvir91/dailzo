<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;
    use SerializesToCamelCase;
    use SoftDeletes;

    protected static function booted(): void
    {
        // Deleting a product here is a soft delete (see ProductResource),
        // and there's no restore/force-delete action exposed in Atlas, so a
        // deleted product's images are just dead weight from then on — this
        // fires on both soft and force delete, and Storage::delete() on an
        // already-missing file is a harmless no-op either way.
        static::deleting(function (Product $product) {
            if (! empty($product->images)) {
                Storage::disk('web')->delete($product->images);
            }
        });
    }

    protected $fillable = [
        'name',
        'description',
        'sku',
        'category_id',
        'price',
        'discounted_price',
        'stock',
        'reserved',
        'images',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'price' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'stock' => 'integer',
            'reserved' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}
