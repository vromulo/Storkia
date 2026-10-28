<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    private function getCategories(): array
    {
        return [
            'Pet' => ['Dog Food & Treats', 'Cat Litter & Accessories', 'Aquariums & Fish Supplies', 'Bird Feeders & Food', 'Pet Grooming Products', 'Pet Health & Wellness'],
            'Kids' => ['Baby Clothes & Accessories', 'Toys & Games', 'Educational Materials', 'Strollers & Gear', 'Nursery Furniture', 'Safety and Health'],
            'Electronics' => ['Mobile Phones & Accessories', 'Laptops, Desktops & Monitors', 'Audio & Video Equipment', 'Smart Home Devices', 'Cameras & Photography', 'Wearable Technology'],
            'Home & Garden' => ['Kitchen Appliances', 'Furniture & Decor', 'Gardening Tools', 'Outdoor Living', 'Home Improvement Tools', 'Bedding & Bath'],
            'Women\'s' => ['Dresses & Skirts', 'Tops & Blouses', 'Activewear & Yoga Pants', 'Lingerie & Sleepwear', 'Jackets & Coats', 'Shoes & Accessories'],
            'Men\'s' => ['Suits & Blazers', 'Casual Shirts & Pants', 'Outerwear & Jackets', 'Activewear & Fitness Gear', 'Shoes & Accessories', 'Grooming Products'],
            'Health & Beauty' => ['Skincare Products', 'Haircare Solutions', 'Makeup & Cosmetics', 'Personal Care Appliances', 'Men\'s Grooming', 'Health Supplements'],
            'Books & Media' => ['Fiction & Non-Fiction Books', 'Magazines & Periodicals', 'Music CDs & Vinyl Records', 'Movie DVDs & Blu-ray', 'Video Games & Consoles', 'Educational DVDs'],
            'Sports & Outdoors' => ['Fitness Equipment', 'Camping & Hiking Gear', 'Sports Apparel', 'Cycling & Bikes', 'Water Sports', 'Team Sports Equipment'],
            'Food & Gourmet' => ['Baking Supplies & Ingredients', 'Coffee, Tea & Beverages', 'Snacks & Candy', 'Specialty Foods', 'Organic and Health Foods', 'Meal Kits & Prepped Foods'],
            'Furniture & Office' => ['Office Desks & Chairs', 'Storage Cabinets & Shelving', 'Conference & Meeting Furniture', 'Computer Tables & Workstations', 'Ergonomic Accessories', 'Office Lighting & Fixtures'],
            'Jewelry & Watches' => ['Necklaces & Pendants', 'Rings & Earrings', 'Bracelets & Bangles', 'Watches for Men & Women', 'Fashion Jewelry', 'Jewelry Storage & Care'],
        ];
    }

    public function show(Request $request, $slug): JsonResponse
    {
        $categories = $this->getCategories();
        $matchedCategory = null;
        $categoryName = '';

        foreach ($categories as $name => $subcategories) {
            if (Str::slug($name) === $slug) {
                $matchedCategory = $subcategories;
                $categoryName = $name;
                break;
            }
        }

        if (!$matchedCategory && $categoryName === '') {
            return response()->json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        $selectedSubcategory = $request->query('subcategory', 'All');

        $query = Product::where('category', $categoryName)
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', function ($q) {
                $q->where('status', 'Approved');
            });

        if ($selectedSubcategory !== 'All') {
            $query->where('subcategory', $selectedSubcategory);
        }

        $products = $query->latest()->paginate(16);

        $minPrice = 0;
        $maxPrice = 5000;
        
        if ($products->count() > 0) {
            $prices = $products->map(function ($p) {
                return $p->discount > 0 ? ($p->price - ($p->price * ($p->discount / 100))) : $p->price;
            });
            $minPrice = floor($prices->min() ?? 0);
            $maxPrice = ceil($prices->max() > 0 ? $prices->max() : 5000);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'category_name'  => $categoryName,
                'slug'           => $slug,
                'subcategories'  => $matchedCategory,
                'min_price'      => $minPrice,
                'max_price'      => $maxPrice,
                'products'       => $products,
            ]
        ]);
    }
}