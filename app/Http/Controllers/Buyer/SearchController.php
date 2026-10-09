<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class SearchController extends Controller
{
    /**
     * Master list of all categories and subcategories, ensuring filters 
     * aren't limited only to products currently existing in the database.
     */
    private function getAllCategories()
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

    public function index(Request $request)
    {
        $query = $request->input('q');
        $category = $request->input('category');
        $subcategory = $request->input('subcategory');

        $productsQuery = Product::query();

        if ($query) {
            $words = array_filter(explode(' ', $query), fn($w) => strlen($w) > 2);
            
            $productsQuery->where(function($qBuilder) use ($query, $words) {
                $qBuilder->where('name', 'LIKE', "%{$query}%")
                         ->orWhere('category', 'LIKE', "%{$query}%");
                
                foreach ($words as $word) {
                    $qBuilder->orWhere('name', 'LIKE', "%{$word}%");
                }
            });
        }

        if ($category) {
            $productsQuery->where('category', $category);
        }
        
        if ($subcategory) {
            $productsQuery->where('subcategory', $subcategory);
        }

        $products = $productsQuery->paginate(16)->withQueryString();

        // Use the hardcoded master list for the dropdowns
        $allCategoryData = $this->getAllCategories();
        $categories = array_keys($allCategoryData);
        $subcategories = ($category && isset($allCategoryData[$category])) ? $allCategoryData[$category] : [];

        return view('buyer.search.search', compact('products', 'query', 'categories', 'subcategories', 'category', 'subcategory'));
    }

    public function suggestions(Request $request)
    {
        $query = $request->input('q');
        
        if (!$query || strlen($query) < 2) {
            return response()->json([]);
        }

        $words = array_filter(explode(' ', $query), fn($w) => strlen($w) > 2);

        $productsQuery = Product::query();
        $productsQuery->where(function($qBuilder) use ($query, $words) {
            $qBuilder->where('name', 'LIKE', "%{$query}%");
            foreach ($words as $word) {
                $qBuilder->orWhere('name', 'LIKE', "%{$word}%");
            }
        });

        // Try to get a valid matching product name to suggest, otherwise default to the search query text
        $topMatch = $productsQuery->select('name')->first();
        $suggestedName = $topMatch ? $topMatch->name : $query;

        $suggestions = [];
        $categories = array_keys($this->getAllCategories());

        // 1. Add "All Categories" suggestion at the top
        $suggestions[] = [
            'name' => $suggestedName,
            'category' => ''
        ];

        // 2. Loop through every existing category instead of querying DB limitations
        foreach ($categories as $cat) {
            $suggestions[] = [
                'name' => $suggestedName,
                'category' => $cat
            ];
        }

        return response()->json($suggestions);
    }
}