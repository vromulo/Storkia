<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductApproval;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Dedicated Administrator Record
        $admin = Admin::updateOrCreate(
            ['email' => 'admin@storkia.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Default Test Buyer & Courier
        User::updateOrCreate(
            ['email' => 'buyer@storkia.com'],
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'middle_initial' => 'A',
                'sex' => 'female',
                'contact_no' => '+639171234567',
                'birthday' => '1995-05-15',
                'password' => Hash::make('password'),
                'role' => 'Buyer',
            ]
        );

        User::updateOrCreate(
            ['email' => 'courier@storkia.com'],
            [
                'first_name' => 'Rider',
                'last_name' => 'Express',
                'middle_initial' => 'R',
                'sex' => 'male',
                'contact_no' => '+639181234567',
                'birthday' => '1992-08-20',
                'password' => Hash::make('password'),
                'role' => 'Logistics',
            ]
        );

        // 3. Batch Generate Buyers
        $firstNames = ['John', 'Maria', 'Carlo', 'Patricia', 'Angelo'];
        $lastNames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo'];
        for ($i = 1; $i <= 5; $i++) {
            $fName = $firstNames[array_rand($firstNames)];
            $lName = $lastNames[array_rand($lastNames)];
            User::create([
                'first_name' => $fName,
                'last_name' => $lName,
                'middle_initial' => chr(rand(65, 90)),
                'sex' => rand(0, 1) ? 'male' : 'female',
                'contact_no' => '+639' . rand(100000000, 999999999),
                'birthday' => Carbon::now()->subYears(rand(19, 45))->format('Y-m-d'),
                'email' => strtolower("{$fName}.{$lName}{$i}") . '@example.com',
                'password' => Hash::make('password'),
                'role' => 'Buyer',
            ]);
        }

        // 4. Batch Generate Sellers
        $stores = [
            ['Apex Gear Co.', 'Electronics', 'approved'],
            ['Lumina Boutique', 'Women\'s', 'approved'],
            ['Pawfect Paws PH', 'Pet', 'approved'],
        ];

        $approvedSellers = [];

        foreach ($stores as $index => [$businessName, $lineOfBusiness, $status]) {
            $sellerUser = User::create([
                'first_name' => 'Merchant',
                'last_name' => (string) ($index + 1),
                'middle_initial' => 'S',
                'sex' => 'female',
                'contact_no' => '+639' . rand(100000000, 999999999),
                'birthday' => '1990-03-25',
                'email' => "seller{$index}@storkia.com",
                'password' => Hash::make('password'),
                'role' => 'Seller',
            ]);

            SellerApplication::create([
                'user_id' => $sellerUser->id,
                'version' => 1,
                'contact_no' => $sellerUser->contact_no,
                'province' => 'Cavite',
                'municipality' => 'Indang',
                'barangay' => 'Poblacion',
                'street' => 'San Gregorio St.',
                'house_details' => "Unit {$index}A",
                'business_name' => $businessName,
                'line_of_business' => $lineOfBusiness,
                'id_path' => 'seller_documents/ids/sample_id.jpg',
                'permit_path' => 'seller_documents/permits/sample_permit.jpg',
                'status' => $status,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if ($status === 'approved') {
                SellerProfile::create([
                    'user_id' => $sellerUser->id,
                    'contact_no' => $sellerUser->contact_no,
                    'province' => 'Cavite',
                    'municipality' => 'Indang',
                    'barangay' => 'Poblacion',
                    'street' => 'San Gregorio St.',
                    'house_details' => "Unit {$index}A",
                    'business_name' => $businessName,
                    'line_of_business' => $lineOfBusiness,
                    'id_path' => 'seller_documents/ids/sample_id.jpg',
                    'permit_path' => 'seller_documents/permits/sample_permit.jpg',
                ]);

                $approvedSellers[] = $sellerUser;
            }
        }

        // 5. Generate Products Assigned to Sellers
        $catalog = [
            ['Apex Gear Co.', 'Wireless Noise-Canceling Earbuds', 2499.00, 'Electronics', 'Audio & Video Equipment', 15],
            ['Apex Gear Co.', 'Compact Mechanical Keyboard 75%', 3200.00, 'Electronics', 'Laptops, Desktops & Monitors', 10],
            ['Lumina Boutique', 'Minimalist Linen Everyday Shirt', 750.00, 'Women\'s', 'Tops & Blouses', 20],
            ['Lumina Boutique', 'Relaxed Fit Chino Trousers', 1190.00, 'Women\'s', 'Activewear & Yoga Pants', 25],
            ['Pawfect Paws PH', 'Ergonomic Reflective Dog Harness', 450.00, 'Pet', 'Dog Food & Treats', 30],
            ['Pawfect Paws PH', 'Interactive Cat Scratcher Lounge', 590.00, 'Pet', 'Cat Litter & Accessories', 12],
        ];

        foreach ($catalog as $item) {
            [$storeName, $prodName, $price, $category, $subcategory, $discount] = $item;
            
            $sellerProfile = SellerProfile::where('business_name', $storeName)->first();
            $sellerId = $sellerProfile ? $sellerProfile->user_id : $approvedSellers[0]->id;

            $product = Product::create([
                'user_id'        => $sellerId,
                'name'           => $prodName,
                'category'       => $category,
                'subcategory'    => $subcategory,
                'description'    => "High quality {$prodName} guaranteed by {$storeName}.",
                'price'          => $price,
                'weight'         => '500g',
                'discount'       => $discount,
                'pictures'       => [],
                'variants'       => [
                    'title' => 'Color',
                    'price_dependency' => 'main',
                    'items' => [
                        ['name' => 'Standard Black', 'price' => $price, 'weight' => 0.5, 'stock' => 50, 'subs' => []],
                        ['name' => 'Warm Cream', 'price' => $price, 'weight' => 0.5, 'stock' => 30, 'subs' => []],
                    ]
                ],
                'stock_quantity' => 80,
            ]);

            ProductApproval::create([
                'product_id'  => $product->id,
                'user_id'     => $sellerId,
                'status'      => 'Approved',
                'reviewed_by' => $admin->id,
            ]);
        }

        // 6. Seed Cart Items
        $this->call(CartSeeder::class);
    }
}