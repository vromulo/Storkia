<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\SuperDealPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DealController extends Controller
{
    public function __construct(
        protected SuperDealPricingService $pricing
    ) {}

    private const SORTS = [
        'ending'     => 'Ending soon',
        'discount'   => 'Biggest discount',
        'price_low'  => 'Price: low to high',
        'price_high' => 'Price: high to low',
    ];

    public function index(Request $request)
    {
        $sortParam = $request->query('sort');
        $sort = is_string($sortParam) && isset(self::SORTS[$sortParam]) ? $sortParam : 'ending';

        $categoryParam = $request->query('category');
        $category = is_string($categoryParam) && $categoryParam !== '' ? $categoryParam : null;

        $allDeals = $this->loadActiveDeals($request);

        // Category chips are always built from the full list so they don't disappear once one is selected.
        $categories = $allDeals
            ->map(fn ($deal) => (string) $deal->product->category)
            ->filter()
            ->countBy()
            ->sortKeys()
            ->all();

        $deals = $category !== null
            ? $allDeals->filter(fn ($deal) => $deal->product->category === $category)
            : $allDeals;

        $deals = match ($sort) {
            'discount'   => $deals->sortBy([['percent_off', 'desc'], ['ends_ts', 'asc']]),
            'price_low'  => $deals->sortBy([['deal_price', 'asc'], ['ends_ts', 'asc']]),
            'price_high' => $deals->sortBy([['deal_price', 'desc'], ['ends_ts', 'asc']]),
            default      => $deals->sortBy([['ends_ts', 'asc'], ['percent_off', 'desc']]),
        };
        $deals = $deals->values();

        return view('buyer.deals.index', [
            'deals'      => $deals,
            'totalAll'   => $allDeals->count(),
            'categories' => $categories,
            'category'   => $category,
            'sorts'      => self::SORTS,
            'sort'       => $sort,
        ]);
    }

    /**
     * Every Super Deal a buyer could actually claim right now, priced exactly the way
     * SuperDealPricingService prices it at checkout.
     */
    private function loadActiveDeals(Request $request)
    {
        if (! Schema::hasTable('super_deals')) {
            return collect();
        }

        $now = now();

        $rows = DB::table('super_deals')
            ->where('deal_type', 'super_deal')
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->where(function ($q) {
                $q->whereNull('flash_quantity')
                  ->orWhereColumn('sold_quantity', '<', 'flash_quantity');
            })
            ->orderBy('ends_at')
            ->orderBy('id')
            ->get()
            ->unique('product_id'); // earliest-ending deal wins, same as getActiveSuperDeal()

        if ($rows->isEmpty()) {
            return collect();
        }

        // Storefront standard (same as HomeController): approved and in stock only.
        $products = Product::whereIn('id', $rows->pluck('product_id'))
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
            ->get()
            ->keyBy('id');

        $wishlisted = [];
        $user = $request->user();
        if ($user && $products->isNotEmpty() && Schema::hasTable('buyer_wishlists')) {
            $wishlisted = DB::table('buyer_wishlists')
                ->where('buyer_id', $user->id)
                ->whereIn('product_id', $products->keys())
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $rows->map(function ($row) use ($products, $wishlisted) {
            $product = $products->get($row->product_id);
            if (! $product) {
                return null;
            }

            $basePrice = round((float) $product->price, 2);
            // Same calculation checkout and the home card use, so every surface shows one price.
            $dealPrice = $this->pricing->superDealUnitPrice($row, $basePrice);

            $hasFlashCap = $row->flash_quantity !== null;
            $flashQty    = $hasFlashCap ? (int) $row->flash_quantity : null;
            $claimed     = (int) $row->sold_quantity;
            $remaining   = $hasFlashCap
                ? min(max(0, $flashQty - $claimed), (int) $product->stock_quantity)
                : (int) $product->stock_quantity;

            if ($remaining <= 0) {
                return null;
            }

            $endsAt = Carbon::parse($row->ends_at);

            return (object) [
                'product'        => $product,
                'deal_id'        => (int) $row->id,
                'deal_price'     => $dealPrice,
                'original_price' => $basePrice,
                'percent_off'    => ($basePrice > 0 && $dealPrice < $basePrice)
                    ? max(1, (int) round((1 - ($dealPrice / $basePrice)) * 100))
                    : 0,
                'has_flash_cap'  => $hasFlashCap,
                'remaining'      => $remaining,
                'claimed_percent'=> $hasFlashCap && $flashQty > 0 ? min(100, (int) round(($claimed / $flashQty) * 100)) : 0,
                'ends_at'        => $endsAt,
                'ends_ts'        => $endsAt->timestamp,
                'is_wishlisted'  => in_array((int) $product->id, $wishlisted, true),
            ];
        })->filter()->values();
    }
}
