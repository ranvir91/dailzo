<?php

namespace App\Models;

use App\Support\SerializesToCamelCase;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use SerializesToCamelCase;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'tax_rate',
        'mrp',
    ];

    /**
     * basePrice/taxAmount are computed from this row's own snapshotted
     * price/tax_rate (set once at order time — see OrderController::store),
     * never the product's current category rate, so a past invoice stays
     * correct even if the category's tax_rate changes later.
     */
    protected $appends = ['base_price', 'tax_amount'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'mrp' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Per-unit base (ex-tax) price — price is tax-inclusive, split using this row's frozen tax_rate. */
    public function getBasePriceAttribute(): float
    {
        $rate = (float) $this->tax_rate;
        $price = (float) $this->price;

        if ($rate <= 0) {
            return round($price, 2);
        }

        return round($price / (1 + ($rate / 100)), 2);
    }

    /** Per-unit tax amount — price - basePrice, not independently rounded. */
    public function getTaxAmountAttribute(): float
    {
        return round((float) $this->price - $this->base_price, 2);
    }
}
