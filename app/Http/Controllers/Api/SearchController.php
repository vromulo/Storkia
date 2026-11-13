<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Buyer\SellerController as BuyerSellerController;
use App\Http\Controllers\Controller;
use App\Models\BuyerWishlist;
use App\Models\OrderItem;
use App\Models\OrderItemReview;
use App\Models\Product;
use App\Models\SellerProfile;
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
        $query = trim((string) ($request->input('q') ?? $request->input('search') ?? ''));
        $category = trim((string) $request->input('category', ''));
        if (strcasecmp($category, 'All') === 0) {
            $category = '';
        }
        $subcategory = trim((string) $request->input('subcategory', ''));
        if (strcasecmp($subcategory, 'All') === 0) {
            $subcategory = '';
        }
        $sort = strtolower(trim((string) $request->input('sort', 'relevance')));

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
                    ->orWhere('category', 'LIKE', "%{$query}%")
                    ->orWhere('subcategory', 'LIKE', "%{$query}%");
                foreach ($words as $word) {
                    $qBuilder->orWhere('name', 'LIKE', "%{$word}%");
                }
            });
        }

        if ($category !== '') {
            $productsQuery->where('category', $category);
        }

        if ($subcategory !== '') {
            $productsQuery->where('subcategory', $subcategory);
        }

        if ($sort === 'price_asc') {
            $productsQuery->orderByRaw('(price - (price * (COALESCE(discount, 0) / 100))) ASC');
        } elseif ($sort === 'price_desc') {
            $productsQuery->orderByRaw('(price - (price * (COALESCE(discount, 0) / 100))) DESC');
        } else {
            $productsQuery->latest()->latest('id');
        }

        $products = $productsQuery->paginate(16);
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

        $matchedSellers = collect();
        if ($query !== '' || $category !== '') {
            $sellerQuery = SellerProfile::query();
            if ($query !== '') {
                $sellerQuery->where(function ($sq) use ($query) {
                    $sq->where('business_name', 'LIKE', "%{$query}%")
                        ->orWhere('line_of_business', 'LIKE', "%{$query}%")
                        ->orWhere('municipality', 'LIKE', "%{$query}%");
                });
            }
            if ($category !== '') {
                $sellerQuery->where('line_of_business', $category);
            }
            $matchedSellers = $sellerQuery->latest()->limit(8)->get()->map(function (SellerProfile $s) {
                return array_merge($s->toArray(), [
                    'stats' => BuyerSellerController::buildStoreMetrics($s),
                ]);
            })->values();
        }

        $allCategories = $this->getAllCategories();

        $paginatedArray = $products->toArray();
        $paginatedArray['matched_sellers'] = $matchedSellers;
        $paginatedArray['sellers'] = $matchedSellers;

        return response()->json([
            'success'         => true,
            'data'            => $paginatedArray,
            'matched_sellers' => $matchedSellers,
            'categories'      => array_keys($allCategories),
            'subcategories'   => ($category !== '' && isset($allCategories[$category])) ? $allCategories[$category] : [],
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

        $topMatches = $productsQuery->select('name')->distinct()->limit(6)->pluck('name');

        $suggestions = [];
        if ($topMatches->isNotEmpty()) {
            foreach ($topMatches as $name) {
                $suggestions[] = [
                    'name'     => $name,
                    'category' => '',
                ];
            }
        } else {
            $suggestions[] = [
                'name'     => $query,
                'category' => '',
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => array_values($suggestions),
        ]);
    }
}