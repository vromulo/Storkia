<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductApproval;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CartSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Target buyer account
        $buyer = User::firstOrCreate(
            ['email' => 'buyer@test.com'],
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'sex' => 'male',
                'contact_no' => '+639123456789',
                'birthday' => '2000-01-01',
                'password' => Hash::make('Password123!'),
                'role' => 'Buyer',
            ]
        );

        // 2. Setup 4 distinct stores
        $storeConfigs = [
            ['Apex Gear Co.', 'Electronics'],
            ['Lumina Boutique', "Women's"],
            ['Pawfect Paws PH', 'Pet'],
            ['Urban Nest Studio', 'Home & Garden'],
        ];

        $sellers = [];
        foreach ($storeConfigs as $index => [$storeName, $category]) {
            $seller = User::firstOrCreate(
                ['email' => "seller_store_{$index}@storkia.com"],
                [
                    'first_name' => 'Owner',
                    'last_name' => "Store {$index}",
                    'sex' => 'female',
                    'contact_no' => '+63918' . rand(1000000, 9999999),
                    'birthday' => '1992-05-10',
                    'password' => Hash::make('password'),
                    'role' => 'Seller',
                ]
            );

            SellerProfile::firstOrCreate(
                ['user_id' => $seller->id],
                [
                    'contact_no' => $seller->contact_no,
                    'province' => 'Cavite',
                    'municipality' => 'Indang',
                    'barangay' => 'Poblacion',
                    'business_name' => $storeName,
                    'line_of_business' => $category,
                    'id_path' => 'sample_id.jpg',
                    'permit_path' => 'sample_permit.jpg',
                ]
            );

            $sellers[] = ['seller' => $seller, 'category' => $category, 'store' => $storeName];
        }

        // 3. Clear existing cart entries for this buyer
        Cart::where('user_id', $buyer->id)->delete();

        // 4. Create 25 products evenly distributed across the 4 stores
        for ($i = 1; $i <= 25; $i++) {
            $assignedStore = $sellers[($i - 1) % 4];

            $product = Product::create([
                'user_id' => $assignedStore['seller']->id,
                'name' => "{$assignedStore['store']} Item #{$i}",
                'category' => $assignedStore['category'],
                'subcategory' => 'General',
                'price' => rand(150, 3500),
                'weight' => '400g',
                'discount' => ($i % 2 === 0) ? rand(5, 20) : 0,
                'pictures' => [],
                'stock_quantity' => rand(20, 100),
                'variants' => null,
            ]);

            ProductApproval::firstOrCreate(
                ['product_id' => $product->id],
                [
                    'user_id' => $assignedStore['seller']->id,
                    'status' => 'Approved',
                ]
            );

            Cart::create([
                'user_id' => $buyer->id,
                'product_id' => $product->id,
                'quantity' => rand(1, 4),
                'variant' => null,
                'sub_variant' => null,
            ]);
        }

        $this->command->info("25 products across 4 stores added to {$buyer->email}'s cart.");
    }
}