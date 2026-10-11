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
        'item_type',
        'description',
        'sku',
        'item_code',
        'hsn_sac_code',
        'brand',
        'unit',
        'category_id',
        'price',
        'mrp',
        'discounted_price',
        'purchase_price',
        'part_no',
        'stock',
        'reserved',
        'images',
        'sort_order',
    ];

    /**
     * taxRate/basePrice/taxAmount are computed, not columns — the product's
     * selling price is entered tax-inclusive, and the split is derived from
     * its category's tax_rate (see Category::tax_rate) on the fly so it
     * always reflects the category's current rate. Needs `category` eager
     * loaded (ProductController's index/show already do) or each one
     * triggers its own query.
     */
    protected $appends = ['tax_rate', 'base_price', 'tax_amount'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'price' => 'decimal:2',
            'mrp' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'stock' => 'integer',
            'reserved' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /** The tax-inclusive price actually charged — discounted price when set, else the list price. */
    public function sellingPrice(): float
    {
        $discounted = (float) ($this->discounted_price ?? 0);

        return $discounted > 0 ? $discounted : (float) $this->price;
    }

    public function getTaxRateAttribute(): float
    {
        return (float) ($this->category?->tax_rate ?? 0);
    }

    /** Splits the tax-inclusive selling price into its base (ex-tax) amount, e.g. ₹1200 at 18% -> ₹1016.95. */
    public function getBasePriceAttribute(): float
    {
        $rate = $this->tax_rate;
        $price = $this->sellingPrice();

        if ($rate <= 0) {
            return round($price, 2);
        }

        return round($price / (1 + ($rate / 100)), 2);
    }

    /** price - basePrice, not independently rounded, so the two always add back up to the selling price. */
    public function getTaxAmountAttribute(): float
    {
        return round($this->sellingPrice() - $this->base_price, 2);
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
