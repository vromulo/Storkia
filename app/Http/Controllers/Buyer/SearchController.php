<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerWishlist;
use App\Models\Product;
use App\Services\SearchLogService;
use App\Services\SearchTypoCorrectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SearchController extends Controller
{
    public function __construct(
        protected SearchTypoCorrectionService $typoCorrectionService,
        protected SearchLogService $searchLog
    ) {}

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

    /**
     * Single place that decides which products the search can see:
     * Approved, in-stock, and active products only.
     */
    protected function baseProductsQuery(): Builder
    {
        $query = Product::query()
            ->with(['approval', 'user.sellerProfile'])
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'));

        if (Schema::hasColumn('products', 'publish_status')) {
            $query->whereNotIn('publish_status', ['draft', 'inactive']);
        }

        return $query;
    }

    protected function runSearch(?string $query, ?string $category, ?string $subcategory)
    {
        $productsQuery = $this->baseProductsQuery();

        if ($query) {
            $words = array_filter(explode(' ', $query), fn ($w) => strlen($w) > 2);

            $productsQuery->where(function ($qBuilder) use ($query, $words) {
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

        return $productsQuery->latest()->latest('id')->get();
    }

    public function index(Request $request)
    {
        $query = trim((string) $request->input('q', '')) ?: null;
        $category = $request->input('category');
        $subcategory = $request->input('subcategory');
        $forceExact = $request->boolean('exact');

        $products = $this->runSearch($query, $category, $subcategory);

        $correctedQuery = null;
        if ($query && $products->isEmpty() && ! $forceExact) {
            $correction = $this->typoCorrectionService->correct($query, $this->baseProductsQuery());
            if ($correction) {
                $correctedProducts = $this->runSearch($correction['query'], $category, $subcategory);
                if ($correctedProducts->isNotEmpty()) {
                    $products = $correctedProducts;
                    $correctedQuery = $correction['query'];
                }
            }
        }

        $this->searchLog->record($correctedQuery ?? $query, $products->count(), $request);

        $allCategoryData = $this->getAllCategories();
        $categories = array_keys($allCategoryData);
        $subcategories = ($category && isset($allCategoryData[$category])) ? $allCategoryData[$category] : [];

        $productMetadata = $products->map(function ($p) {
            $basePrice = (float) ($p->price ?? 0);
            $discount = (float) ($p->discount ?? 0);
            $effectivePrice = $discount > 0
                ? round($basePrice - ($basePrice * ($discount / 100)), 2)
                : round($basePrice, 2);

            return [
                'id'          => (int) $p->id,
                'subcategory' => trim((string) ($p->subcategory ?? '')) ?: 'General',
                'price'       => $effectivePrice,
            ];
        })->values();

        $minPrice = (int) floor((float) ($productMetadata->min('price') ?? 0));
        $maxBase = (float) ($products->max(fn ($p) => (float) ($p->price ?? 0)) ?? 0);
        $maxPrice = (int) ceil($maxBase > 0 ? $maxBase : 5000);
        $maxPrice = max($maxPrice, $minPrice);

        $wishlistedProductIds = auth()->check()
            ? BuyerWishlist::where('buyer_id', auth()->id())->pluck('product_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return view('buyer.search.search', compact(
            'products',
            'query',
            'categories',
            'subcategories',
            'category',
            'subcategory',
            'correctedQuery',
            'productMetadata',
            'minPrice',
            'maxPrice',
            'wishlistedProductIds'
        ));
    }

    public function suggestions(Request $request)
    {
        $query = trim((string) $request->input('q', ''));

        if ($query === '' || strlen($query) < 2) {
            return response()->json([]);
        }

        $words = array_filter(explode(' ', $query), fn ($w) => strlen($w) > 2);

        $productsQuery = $this->baseProductsQuery();
        $productsQuery->where(function ($qBuilder) use ($query, $words) {
            $qBuilder->where('name', 'LIKE', "%{$query}%");
            foreach ($words as $word) {
                $qBuilder->orWhere('name', 'LIKE', "%{$word}%");
            }
        });

        $topMatch = $productsQuery->select('name')->first();
        $suggestedName = $topMatch ? $topMatch->name : $query;

        $suggestions = [];
        $categories = array_keys($this->getAllCategories());

        $suggestions[] = [
            'name'     => $suggestedName,
            'category' => '',
        ];

        foreach ($categories as $cat) {
            $suggestions[] = [
                'name'     => $suggestedName,
                'category' => $cat,
            ];
        }

        return response()->json($suggestions);
    }
}