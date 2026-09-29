<?php

namespace App\Models;

use App\Models\Concerns\AssignsSequentialNumber;
use App\Support\SerializesToCamelCase;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A vendor fulfills orders superadmin routes to them (by pincode coverage)
 * using their own delivery partners. Auth identity lives on the linked User
 * (role = VENDOR) — this table is the profile: business name, active state,
 * pincode coverage, and the partners/orders that belong to it.
 */
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use AssignsSequentialNumber;

    use HasFactory;
    use HasUuids;
    use SerializesToCamelCase;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'business_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function sequentialNumberColumn(): string
    {
        return 'vendor_number';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryPartners()
    {
        return $this->hasMany(DeliveryPartner::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function servicePincodes()
    {
        return $this->belongsToMany(ServicePincode::class, 'vendor_service_pincodes');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * The Atlas VendorResource form collects the vendor's login (name/phone/
     * password — a User, role = VENDOR) alongside business_name/is_active/
     * service_pincode_ids in one form. $data must include name/phone/password
     * plus is_active/business_name; service_pincode_ids (if present) is
     * synced to the pincode-coverage pivot after creating.
     */
    public static function createWithUser(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => 'VENDOR',
            ]);

            $vendor = static::create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (! empty($data['service_pincode_ids'])) {
                $vendor->servicePincodes()->sync($data['service_pincode_ids']);
            }

            return $vendor;
        });
    }

    /** Updates this vendor and syncs name/phone (+ password if provided) onto its linked User. */
    public function updateWithUser(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $userUpdate = collect($data)->only(['name', 'phone'])->all();
            if (filled($data['password'] ?? null)) {
                $userUpdate['password'] = $data['password'];
            }
            if ($userUpdate) {
                $this->user->update($userUpdate);
            }

            if (array_key_exists('service_pincode_ids', $data)) {
                $this->servicePincodes()->sync($data['service_pincode_ids'] ?? []);
            }

            return $this->update([
                'business_name' => $data['business_name'],
                'is_active' => $data['is_active'] ?? $this->is_active,
            ]);
        });
    }
}
