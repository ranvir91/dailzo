<?php

namespace Database\Factories;

use App\Models\DeliveryPartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryPartner>
 */
class DeliveryPartnerFactory extends Factory
{
    public function definition(): array
    {
        // name/phone are mirrored onto the paired User (the actual login,
        // role = DELIVERY_PARTNER) below — generated once so both rows agree.
        $name = fake()->name();
        $phone = (string) fake()->unique()->numerify('8#########');

        return [
            'user_id' => User::factory()->state([
                'name' => $name,
                'phone' => $phone,
                'role' => 'DELIVERY_PARTNER',
                'password' => 'password',
            ]),
            'name' => $name,
            'phone' => $phone,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
