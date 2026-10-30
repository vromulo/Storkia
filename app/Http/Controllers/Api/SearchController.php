<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerWishlist;
use App\Models\OrderItem;
use App\Models\OrderItemReview;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SearchController extends Controller
{
    private function getAllCategories(): array
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

    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $category = $request->input('category');
        $subcategory = $request->input('subcategory');

        $productsQuery = Product::with(['approval', 'sellerProfile', 'user.sellerProfile'])
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', function ($q) {
                $q->where('status', 'Approved');
            });

        if (Schema::hasColumn('products', 'publish_status')) {
            $productsQuery->whereNotIn('publish_status', ['draft', 'inactive']);
        }

        if ($query !== '') {
            $words = array_filter(explode(' ', $query), fn ($w) => strlen($w) > 2);

            $productsQuery->where(function ($qBuilder) use ($query, $words) {
                $qBuilder->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('category', 'LIKE', "%{$query}%");

                foreach ($words as $word) {
                    $qBuilder->orWhere('name', 'LIKE', "%{$word}%");
                }
            });
        }

        if (! empty($category)) {
            $productsQuery->where('category', $category);
        }

        if (! empty($subcategory)) {
            $productsQuery->where('subcategory', $subcategory);
        }

        $products = $productsQuery->latest()->paginate(16);
        $collection = $products->getCollection();
        $user = $request->user();

        if ($collection->isNotEmpty()) {
            $productIds = $collection->pluck('id')->all();

            $soldCounts = OrderItem::whereIn('product_id', $productIds)
                ->whereHas('sellerOrder', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))
                ->selectRaw('product_id, SUM(quantity) as total_sold')
                ->groupBy('product_id')
                ->pluck('total_sold', 'product_id');

            $reviewStats = Schema::hasTable('order_item_reviews')
                ? OrderItemReview::whereIn('product_id', $productIds)
                    ->selectRaw('product_id, AVG(rating) as avg_rating, COUNT(*) as reviews_count')
                    ->groupBy('product_id')
                    ->get()
                    ->keyBy('product_id')
                : collect();

            $wishlistedIds = ($user && Schema::hasTable('buyer_wishlists'))
                ? BuyerWishlist::where('buyer_id', $user->id)
                    ->whereIn('product_id', $productIds)
                    ->pluck('product_id')
                    ->all()
                : [];

            foreach ($collection as $prod) {
                $prod->setAttribute('sold_count', (int) ($soldCounts[$prod->id] ?? 0));
                $rStat = $reviewStats->get($prod->id);
                $prod->setAttribute('avg_rating', $rStat ? round((float) $rStat->avg_rating, 1) : 0.0);
                $prod->setAttribute('reviews_count', $rStat ? (int) $rStat->reviews_count : 0);
                $prod->setAttribute('is_wishlisted', in_array($prod->id, $wishlistedIds, true));
            }
        }

        $allCategories = $this->getAllCategories();

        return response()->json([
            'success'       => true,
            'data'          => $products,
            'categories'    => array_keys($allCategories),
            'subcategories' => ($category && isset($allCategories[$category])) ? $allCategories[$category] : [],
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $words = array_filter(explode(' ', $query), fn ($w) => strlen($w) > 2);

        $productsQuery = Product::query()
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', function ($q) {
                $q->where('status', 'Approved');
            });

        if (Schema::hasColumn('products', 'publish_status')) {
            $productsQuery->whereNotIn('publish_status', ['draft', 'inactive']);
        }

        $productsQuery->where(function ($qBuilder) use ($query, $words) {
            $qBuilder->where('name', 'LIKE', "%{$query}%");
            foreach ($words as $word) {
                $qBuilder->orWhere('name', 'LIKE', "%{$word}%");
            }
        });

        $topMatch = $productsQuery->select('name')->first();
        $suggestedName = $topMatch ? $topMatch->name : $query;

        $suggestions = [];
        $suggestions[] = [
            'name'     => $suggestedName,
            'category' => '',
        ];

        foreach (array_keys($this->getAllCategories()) as $cat) {
            $suggestions[] = [
                'name'     => $suggestedName,
                'category' => $cat,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => array_values($suggestions),
        ]);
    }
}