<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Product;
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

        // 3. Batch Generate Buyers (20 Users)
        $firstNames = ['John', 'Maria', 'Carlo', 'Patricia', 'Angelo', 'Bea', 'Miguel', 'Camille', 'Paolo', 'Alyssa'];
        $lastNames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Aquino'];

        for ($i = 1; $i <= 20; $i++) {
            $fName = $firstNames[array_rand($firstNames)];
            $lName = $lastNames[array_rand($lastNames)];

            User::create([
                'first_name' => $fName,
                'last_name' => $lName,
                'middle_initial' => chr(rand(65, 90)),
                'sex' => rand(0, 1) ? 'male' : 'female',
                'contact_no' => '+639' . rand(100000000, 999999999),
                'birthday' => Carbon::now()->subYears(rand(19, 45))->subDays(rand(1, 365))->format('Y-m-d'),
                'email' => strtolower("{$fName}.{$lName}{$i}") . '@example.com',
                'password' => Hash::make('password'),
                'role' => 'Buyer',
            ]);
        }

        // 4. Batch Generate Sellers (Approved, Pending, and Rejected)
        $stores = [
            ['Apex Gear Co.', 'Electronics', 'approved'],
            ['Lumina Boutique', 'Fashion & Apparel', 'approved'],
            ['Artisan Hearth', 'Home & Living', 'approved'],
            ['Pawfect Paws PH', 'Pet', 'approved'],
            ['Pure Botanicals', 'Health & Beauty', 'approved'],
            ['Kiddie Wonderland', 'Kids & Baby', 'approved'],
            ['Summit Outfitter', 'Sports & Outdoors', 'approved'],
            ['Manila Coffee Roasters', 'Food & Beverages', 'approved'],
            ['Verde Living', 'Home & Living', 'approved'],
            ['Silicon Haven', 'Electronics', 'pending'],
            ['Daily Glow Organics', 'Health & Beauty', 'rejected'],
        ];

        foreach ($stores as $index => [$businessName, $lineOfBusiness, $status]) {
            $sellerUser = User::create([
                'first_name' => 'Merchant',
                'last_name' => (string) ($index + 1),
                'middle_initial' => 'S',
                'sex' => $index % 2 === 0 ? 'male' : 'female',
                'contact_no' => '+639' . rand(100000000, 999999999),
                'birthday' => '1990-03-25',
                'email' => "seller{$index}@storkia.com",
                'password' => Hash::make('password'),
                'role' => 'Seller',
            ]);

            // Version 1 Application Snapshot
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
                'rejection_reason' => $status === 'rejected' ? 'Submitted business permit is blurry and unreadable.' : null,
                'reviewed_by' => $status !== 'pending' ? $admin->id : null,
                'reviewed_at' => $status !== 'pending' ? now() : null,
            ]);

            // Seller Profile is populated ONLY if approved (no status column present)
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
            }
        }

        // 5. Batch Generate Products matching products table migration
        $productCatalog = [
            ['name' => 'Minimalist Linen Everyday Shirt', 'price' => 750.00, 'weight' => '300g', 'stock' => 50],
            ['name' => 'Relaxed Fit Chino Trousers', 'price' => 1190.00, 'weight' => '500g', 'stock' => 35],
            ['name' => 'Wireless Noise-Canceling Earbuds', 'price' => 2499.00, 'weight' => '150g', 'stock' => 80],
            ['name' => 'Compact Mechanical Keyboard 75%', 'price' => 3200.00, 'weight' => '850g', 'stock' => 25],
            ['name' => 'Aromatherapy Ceramic Diffuser', 'price' => 680.00, 'weight' => '400g', 'stock' => 40],
            ['name' => 'Ceramic Stoneware Coffee Mug (350ml)', 'price' => 280.00, 'weight' => '320g', 'stock' => 100],
            ['name' => 'Ergonomic Reflective Dog Harness', 'price' => 450.00, 'weight' => '250g', 'stock' => 60],
            ['name' => 'Interactive Cat Scratcher Lounge', 'price' => 590.00, 'weight' => '1.2kg', 'stock' => 20],
            ['name' => 'Organic Rosehip Facial Oil 30ml', 'price' => 420.00, 'weight' => '90g', 'stock' => 75],
            ['name' => 'Gentle Hydrating Milk Cleanser 150ml', 'price' => 360.00, 'weight' => '180g', 'stock' => 90],
            ['name' => 'Wooden Montessori Puzzle Board', 'price' => 480.00, 'weight' => '600g', 'stock' => 45],
            ['name' => 'Insulated Stainless Steel Flask (1L)', 'price' => 890.00, 'weight' => '450g', 'stock' => 110],
            ['name' => 'Single-Origin Benguet Arabica Beans 250g', 'price' => 380.00, 'weight' => '260g', 'stock' => 65],
            ['name' => 'Cold Brew Concentrate Bottle (500ml)', 'price' => 299.00, 'weight' => '650g', 'stock' => 30],
            ['name' => 'Non-Slip Eco Yoga Mat 6mm', 'price' => 950.00, 'weight' => '1kg', 'stock' => 40],
        ];

        foreach ($productCatalog as $item) {
            Product::create([
                'name' => $item['name'],
                'description' => "High-quality {$item['name']}. Carefully inspected for durability and performance.",
                'additional_descriptions' => 'Includes 7-day replacement warranty. Store in a cool, dry place.',
                'price' => $item['price'],
                'weight' => $item['weight'],
                'discount' => rand(0, 1) ? rand(5, 20) : 0,
                'pictures' => ['products/sample_1.jpg', 'products/sample_2.jpg'],
                'variants' => [
                    ['name' => 'Standard', 'sku' => 'SKU-' . rand(1000, 9999), 'price' => $item['price']]
                ],
                'stock_quantity' => $item['stock'],
            ]);
        }
    }
}