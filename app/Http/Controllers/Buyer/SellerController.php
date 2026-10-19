<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\Schema;

class SellerController extends Controller
{
    /**
     * Master list of categories and their subcategories.
     */
    private function getMasterCategories(): array
    {
        return [
            'Pet' => ['Dog Food & Treats', 'Cat Litter & Accessories', 'Aquariums & Fish Supplies', 'Bird Feeders & Food', 'Pet Grooming Products', 'Pet Health & Wellness'],
            'Kids' => ['Baby Clothes & Accessories', 'Toys & Games', 'Educational Materials', 'Strollers & Gear', 'Nursery Furniture', 'Safety and Health'],
            'Electronics' => ['Mobile Phones & Accessories', 'Laptops, Desktops & Monitors', 'Audio & Video Equipment', 'Smart Home Devices', 'Cameras & Photography', 'Wearable Technology'],
            'Home & Garden' => ['Kitchen Appliances', 'Furniture & Decor', 'Gardening Tools', 'Outdoor Living', 'Home Improvement Tools', 'Bedding & Bath'],
            'Women\'s' => ['Dresses & Skirts', 'Tops & Blouses', 'Activewear & Yoga Pants', 'Lingerie & Sleepwear', 'Jackets & Coats', 'Shoes & Accessories'],
            'Men\'s' => ['Suits & Blazers', 'Casual Shirts & Pants', 'Outerwear & Jackets', 'Activewear & Fitness Gear', 'Shoes & Accessories', 'Grooming Products'],
            'Health & Beauty' => ['Skincare Products', 'Haircare Solutions', 'Makeup & Cosmetics', 'Personal Care Appliances', "Men's Grooming", 'Health Supplements'],
            'Books & Media' => ['Fiction & Non-Fiction Books', 'Magazines & Periodicals', 'Music CDs & Vinyl Records', 'Movie DVDs & Blu-ray', 'Video Games & Consoles', 'Educational DVDs'],
            'Sports & Outdoors' => ['Fitness Equipment', 'Camping & Hiking Gear', 'Sports Apparel', 'Cycling & Bikes', 'Water Sports', 'Team Sports Equipment'],
            'Food & Gourmet' => ['Baking Supplies & Ingredients', 'Coffee, Tea & Beverages', 'Snacks & Candy', 'Specialty Foods', 'Organic and Health Foods', 'Meal Kits & Prepped Foods'],
            'Furniture & Office' => ['Office Desks & Chairs', 'Storage Cabinets & Shelving', 'Conference & Meeting Furniture', 'Computer Tables & Workstations', 'Ergonomic Accessories', 'Office Lighting & Fixtures'],
            'Jewelry & Watches' => ['Necklaces & Pendants', 'Rings & Earrings', 'Rings & Engagement', 'Earrings & Studs', 'Bracelets & Bangles', 'Watches for Men & Women', "Men's Watches", "Women's Watches", 'Fashion Jewelry', 'Fine Jewelry Sets', 'Jewelry Storage & Care'],
        ];
    }

    public function index()
    {
        $sellers = SellerProfile::latest()->get();
        $masterCategories = $this->getMasterCategories();

        // Force the dropdown to show ALL master main categories
        $mainCategories = collect(array_keys($masterCategories))->sort()->values();

        // Merge any existing lines of business from active sellers just in case they aren't in the master list
        $activeLines = $sellers->pluck('line_of_business')->filter();
        $mainCategories = $mainCategories->merge($activeLines)->unique()->sort()->values();

        return view('buyer.sellers', compact('sellers', 'mainCategories'));
    }

    public function show($id)
    {
        $seller = SellerProfile::with('user')->findOrFail($id);
        $masterCategories = $this->getMasterCategories();

        $products = collect();

        if (class_exists(Product::class) && Schema::hasTable('products')) {
            $query = Product::query();

            if (Schema::hasColumn('products', 'user_id')) {
                $query->where('user_id', $seller->user_id);
            } elseif (Schema::hasColumn('products', 'seller_id')) {
                $query->where('seller_id', $seller->id);
            } elseif (Schema::hasColumn('products', 'seller_profile_id')) {
                $query->where('seller_profile_id', $seller->id);
            }

            if (Schema::hasColumn('products', 'status')) {
                $query->whereIn('status', ['active', 'Active', 'published', 'Published', 1, '1']);
            } elseif (Schema::hasColumn('products', 'is_active')) {
                $query->where('is_active', true);
            }

            $products = $query->latest()->get();
        }

        $categoriesById = collect();
        if (class_exists(Category::class) && Schema::hasTable('categories')) {
            $categoriesById = Category::all()->keyBy('id');
        }

        // Force the dropdown to show ALL subcategories corresponding to this seller's main category
        $subCategories = collect($masterCategories[$seller->line_of_business] ?? []);

        $products->each(function ($product) use ($categoriesById, $seller) {
            $subCategoryName = null;

            // Correctly references the 'subcategory' column without an underscore
            if (!empty($product->subcategory)) {
                $subCategoryName = $product->subcategory;
            } elseif (!empty($product->category_id) && $categoriesById->has($product->category_id)) {
                $cat = $categoriesById->get($product->category_id);
                if ($cat && $cat->name !== $seller->line_of_business) {
                    $subCategoryName = $cat->name;
                }
            } elseif (!empty($product->category) && $product->category !== $seller->line_of_business) {
                $subCategoryName = $product->category;
            }

            $product->resolved_sub_category = $subCategoryName ?: 'Uncategorized';
        });

        // Merge any specific subcategories mapped from the actual products
        $productSubCategories = $products->pluck('resolved_sub_category')->filter(fn($val) => $val !== 'Uncategorized');
        $subCategories = $subCategories->merge($productSubCategories)->unique()->sort()->values();

        $stats = [
            'chat_response_rate' => $seller->chat_response_rate ?? 98,
            'following'          => $seller->following_count ?? 14,
            'followers'          => $seller->followers_count ?? 128,
            'ratings'            => number_format((float) ($seller->rating ?? 4.9), 1),
            'joined'             => $seller->created_at ? $seller->created_at->diffForHumans() : 'Recently',
            'products_count'     => $products->count(),
        ];

        return view('buyer.seller-profile-show', [
            'title'         => $seller->business_name,
            'seller'        => $seller,
            'products'      => $products,
            'subCategories' => $subCategories,
            'stats'         => $stats,
        ]);
    }
}