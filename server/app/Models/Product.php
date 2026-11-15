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
        'brand_id',
        'unit',
        'parent_id',
        'variant_label',
        'category_id',
        'price',
        'discounted_price',
        'purchase_price',
        'part_no',
        'tags',
        'stock',
        'reserved',
        'images',
        'sort_order',
        'is_active',
        'is_freebie',
        'freebie_price',
        'freebie_min_order_value',
    ];

    /**
     * taxRate/basePrice/taxAmount are computed, not columns — the product's
     * selling price is entered tax-inclusive, and the split is derived from
     * its category's tax_rate (see Category::tax_rate) on the fly so it
     * always reflects the category's current rate. Needs `category` eager
     * loaded (ProductController's index/show already do) or each one
     * triggers its own query.
     */
    protected $appends = ['tax_rate', 'base_price', 'tax_amount', 'has_variants', 'display_name'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'tags' => 'array',
            'price' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'stock' => 'integer',
            'reserved' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_freebie' => 'boolean',
            'freebie_price' => 'decimal:2',
            'freebie_min_order_value' => 'decimal:2',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    /** The tax-inclusive price actually charged — discounted price when set, else `price` (the MRP). */
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

    /** The product this is a pack-size/weight variant of (e.g. "Aata 10kg" -> parent "Aata 5kg"), if any. */
    public function parent()
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    /** Other pack-size/weight variants of this product — each is a fully independent, orderable Product row. */
    public function variants()
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    /**
     * "Aata 10kg (10kg)" — the single place that appends a product's pack
     * size to its name, so order items/invoices/cart lines can tell apart
     * the same base product ordered in different pack sizes, without each
     * caller re-implementing the same string concat.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->variant_label ? "{$this->name} ({$this->variant_label})" : $this->name;
    }

    /**
     * Lets the catalog listing (which doesn't embed `variants`, see
     * ProductController::index()) tell a tile whether tapping "add" should
     * jump to the pack-size picker instead of adding directly — without an
     * extra request per product. Reads the `variants_count` the listing
     * eager-loads via withCount('variants') when present, falling back to
     * a query only when it isn't (e.g. a single show()/store() response).
     */
    public function getHasVariantsAttribute(): bool
    {
        if (array_key_exists('variants_count', $this->attributes)) {
            return (int) $this->attributes['variants_count'] > 0;
        }

        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }
}
