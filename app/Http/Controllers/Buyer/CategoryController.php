<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function show(Request $request, $slug)
    {
        $categories = [
            'Pet' => [
                ['name' => 'Dog Food & Treats', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Dog'],
                ['name' => 'Cat Litter & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Cat'],
                ['name' => 'Aquariums & Fish Supplies', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Fish'],
                ['name' => 'Bird Feeders & Food', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Bird'],
                ['name' => 'Pet Grooming Products', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Groom'],
                ['name' => 'Pet Health & Wellness', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Health'],
            ],
            'Kids' => [
                ['name' => 'Baby Clothes & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Baby'],
                ['name' => 'Toys & Games', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Toys'],
                ['name' => 'Educational Materials', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Learn'],
                ['name' => 'Strollers & Gear', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Gear'],
                ['name' => 'Nursery Furniture', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Nursery'],
                ['name' => 'Safety and Health', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Safety'],
            ],
            'Electronics' => [
                ['name' => 'Mobile Phones & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Phones'],
                ['name' => 'Laptops, Desktops & Monitors', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Laptops'],
                ['name' => 'Audio & Video Equipment', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Audio'],
                ['name' => 'Smart Home Devices', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Smart'],
                ['name' => 'Cameras & Photography', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Camera'],
                ['name' => 'Wearable Technology', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Watch'],
            ],
            'Home & Garden' => [
                ['name' => 'Kitchen Appliances', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Kitchen'],
                ['name' => 'Furniture & Decor', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Decor'],
                ['name' => 'Gardening Tools', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Garden'],
                ['name' => 'Outdoor Living', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Outdoor'],
                ['name' => 'Home Improvement Tools', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Tools'],
                ['name' => 'Bedding & Bath', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Bedding'],
            ],
            'Women\'s' => [
                ['name' => 'Dresses & Skirts', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Dress'],
                ['name' => 'Tops & Blouses', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Tops'],
                ['name' => 'Activewear & Yoga Pants', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Yoga'],
                ['name' => 'Lingerie & Sleepwear', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Sleep'],
                ['name' => 'Jackets & Coats', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Coats'],
                ['name' => 'Shoes & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Shoes'],
            ],
            'Men\'s' => [
                ['name' => 'Suits & Blazers', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Suits'],
                ['name' => 'Casual Shirts & Pants', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Shirts'],
                ['name' => 'Outerwear & Jackets', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Jackets'],
                ['name' => 'Activewear & Fitness Gear', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Fitness'],
                ['name' => 'Shoes & Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Shoes'],
                ['name' => 'Grooming Products', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Grooming'],
            ],
            'Health & Beauty' => [
                ['name' => 'Skincare Products', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Skin'],
                ['name' => 'Haircare Solutions', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Hair'],
                ['name' => 'Makeup & Cosmetics', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Makeup'],
                ['name' => 'Personal Care Appliances', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Care'],
                ['name' => "Men's Grooming", 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Mens'],
                ['name' => 'Health Supplements', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Health'],
            ],
            'Books & Media' => [
                ['name' => 'Fiction & Non-Fiction Books', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Books'],
                ['name' => 'Magazines & Periodicals', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Mags'],
                ['name' => 'Music CDs & Vinyl Records', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Music'],
                ['name' => 'Movie DVDs & Blu-ray', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Movies'],
                ['name' => 'Video Games & Consoles', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Games'],
                ['name' => 'Educational DVDs', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Edu'],
            ],
            'Sports & Outdoors' => [
                ['name' => 'Fitness Equipment', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Fitness'],
                ['name' => 'Camping & Hiking Gear', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Camp'],
                ['name' => 'Sports Apparel', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Apparel'],
                ['name' => 'Cycling & Bikes', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Bike'],
                ['name' => 'Water Sports', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Water'],
                ['name' => 'Team Sports Equipment', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Team'],
            ],
            'Food & Gourmet' => [
                ['name' => 'Baking Supplies & Ingredients', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Bake'],
                ['name' => 'Coffee, Tea & Beverages', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Coffee'],
                ['name' => 'Snacks & Candy', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Snacks'],
                ['name' => 'Specialty Foods', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Special'],
                ['name' => 'Organic and Health Foods', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Organic'],
                ['name' => 'Meal Kits & Prepped Foods', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Meals'],
            ],
            'Furniture & Office' => [
                ['name' => 'Office Desks & Chairs', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Desks'],
                ['name' => 'Storage Cabinets & Shelving', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Storage'],
                ['name' => 'Conference & Meeting Furniture', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Meet'],
                ['name' => 'Computer Tables & Workstations', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Tables'],
                ['name' => 'Ergonomic Accessories', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Ergo'],
                ['name' => 'Office Lighting & Fixtures', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Lights'],
            ],
            'Jewelry & Watches' => [
                ['name' => 'Necklaces & Pendants', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Necklace'],
                ['name' => 'Rings & Earrings', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Rings'],
                ['name' => 'Bracelets & Bangles', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Bracelets'],
                ['name' => 'Watches for Men & Women', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Watches'],
                ['name' => 'Fashion Jewelry', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Fashion'],
                ['name' => 'Jewelry Storage & Care', 'image' => 'https://placehold.co/150x150/F6D8BD/5D3140?text=Care'],
            ]
        ];

        $matchedCategory = null;
        $categoryName = '';

        foreach ($categories as $name => $subcategories) {
            if (Str::slug($name) === $slug) {
                $matchedCategory = $subcategories;
                $categoryName = $name;
                break;
            }
        }

        if (! $matchedCategory && $categoryName === '') {
            abort(404);
        }

        $selectedSubcategory = $request->query('subcategory', 'All');
        $products = [];

        return view('category.show', [
            'categoryName' => $categoryName,
            'subcategories' => $matchedCategory,
            'selectedSubcategory' => $selectedSubcategory,
            'products' => $products,
        ]);
    }
}