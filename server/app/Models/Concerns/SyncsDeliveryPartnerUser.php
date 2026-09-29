<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A delivery partner's login (name/phone/password) lives on its linked User
 * (role = DELIVERY_PARTNER), not on the DeliveryPartner row itself. Both the
 * Atlas and Octa DeliveryPartnerResource forms collect name/phone/password
 * alongside the DeliveryPartner-only fields (vendor_id, is_active) in one
 * form, and call these two methods to persist across both tables —
 * shared here so the two panels can't drift into inconsistent save logic.
 */
trait SyncsDeliveryPartnerUser
{
    /**
     * $data must include name/phone/password (the new login) plus any
     * DeliveryPartner columns (vendor_id, is_active, ...). password is never
     * written to DeliveryPartner itself — only used to create the User.
     */
    public static function createWithUser(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => 'DELIVERY_PARTNER',
            ]);

            return static::create([
                ...collect($data)->except('password')->all(),
                'user_id' => $user->id,
            ]);
        });
    }

    /**
     * Updates this DeliveryPartner and syncs name/phone onto its linked User
     * (password only if one was actually submitted — forms leave it out when
     * the login shouldn't change, matching the "blank = keep unchanged"
     * convention used elsewhere in this app, e.g. UserResource).
     */
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

            return $this->update(collect($data)->except('password')->all());
        });
    }
}
