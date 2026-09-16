<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliveryPartner;
use App\Models\Product;
use App\Models\ServicePincode;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Store settings singleton.
        StoreSetting::current();

        // Categories (mirrors the old Prisma seed).
        $categories = collect([
            ['name' => 'Dairy', 'slug' => 'dairy'],
            ['name' => 'Bakery', 'slug' => 'bakery'],
            ['name' => 'Groceries', 'slug' => 'groceries'],
        ])->mapWithKeys(fn ($c) => [
            $c['slug'] => Category::updateOrCreate(['slug' => $c['slug']], $c),
        ]);

        // Demo customer — logs in via phone/OTP on the mobile app.
        User::firstOrCreate(['phone' => '9999999999'], ['name' => 'Demo Customer', 'role' => 'CUSTOMER']);

        // Admin — mobile OTP login AND the Filament panel (email + password).
        // Set SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD in the environment before the
        // first seed so production never starts with a publicly-known password.
        // firstOrCreate: an existing admin (e.g. one whose password was changed in
        // the panel) is left untouched on re-seed.
        User::firstOrCreate(['phone' => '9999999998'], [
            'name' => 'Admin',
            'role' => 'ADMIN',
            'email' => config('dailzo.seed_admin_email'),
            'password' => config('dailzo.seed_admin_password'),
        ]);

        // Demo delivery partner — logs in to the partner mobile app with
        // phone_number + password. Change/rotate for production the same way
        // as the admin credentials above.
        DeliveryPartner::firstOrCreate(['phone' => '8999999999'], [
            'name' => 'Demo Partner',
            'password' => config('dailzo.seed_admin_password'),
            'is_active' => true,
        ]);

        // A little sample content so the mobile app has something to show in dev.
        $samples = [
            ['dairy', 'Fresh Milk 1L', 60, 54, 40],
            ['dairy', 'Curd 400g', 45, null, 30],
            ['bakery', 'Whole Wheat Bread', 40, 35, 25],
            ['bakery', 'Butter Croissant', 55, null, 15],
            ['groceries', 'Basmati Rice 5kg', 520, 479, 20],
            ['groceries', 'Toor Dal 1kg', 145, null, 35],
        ];

        foreach ($samples as [$slug, $name, $price, $discounted, $stock]) {
            Product::firstOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categories[$slug]->id,
                    'price' => $price,
                    'discounted_price' => $discounted,
                    'stock' => $stock,
                    'images' => [],
                ],
            );
        }

        foreach (['121001', '121002', '121003', '110001'] as $pincode) {
            ServicePincode::firstOrCreate(['pincode' => $pincode], ['is_active' => true]);
        }
    }
}
