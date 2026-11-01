<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The one definition of "trending", shared by the Trends page and the home page tile
 * so the two can never disagree.
 */
class TrendingProductsService
{
    public const WINDOW_DAYS = 30;
    protected const NEW_ARRIVAL_DAYS = 14;

    // Weight of one order vs. one wishlist save in the trending score.
    protected const ORDER_WEIGHT = 3;
    protected const SAVE_WEIGHT = 2;

    // Orders in these states don't count as demand.
    protected const IGNORED_ORDER_STATUSES = ['cancelled', 'rejected', 'returned_to_seller'];

    /**
     * Products buyers are ordering or saving right now, plus brand-new arrivals so
     * the list is never empty on a quiet week. Unsorted: callers sort/filter as they need.
     *
     * @return Collection<int, object{product: Product, ordered: int, saves: int, score: int, is_new: bool, created_ts: int, effective_price: float}>
     */
    public function all(?Authenticatable $user = null): Collection
    {
        $since = now()->subDays(self::WINDOW_DAYS);
        $newSince = now()->subDays(self::NEW_ARRIVAL_DAYS);

        $ordered = OrderItem::query()
            ->join('seller_orders', 'seller_orders.id', '=', 'order_items.seller_order_id')
            ->whereNotIn('seller_orders.status', self::IGNORED_ORDER_STATUSES)
            ->where('seller_orders.created_at', '>=', $since)
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units')
            ->pluck('units', 'product_id');

        $saved = Schema::hasTable('buyer_wishlists')
            ? DB::table('buyer_wishlists')
                ->where('created_at', '>=', $since)
                ->groupBy('product_id')
                ->selectRaw('product_id, COUNT(*) as saves')
                ->pluck('saves', 'product_id')
            : collect();

        $signalIds = $ordered->keys()->merge($saved->keys())->unique()->values();

        // Storefront standard (same as HomeController): approved and in stock only.
        $products = Product::where('stock_quantity', '>', 0)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
            ->where(function ($q) use ($signalIds, $newSince) {
                $q->whereIn('id', $signalIds)->orWhere('created_at', '>=', $newSince);
            })
            ->get();

        $wishlisted = [];
        if ($user && $products->isNotEmpty() && Schema::hasTable('buyer_wishlists')) {
            $wishlisted = DB::table('buyer_wishlists')
                ->where('buyer_id', $user->getAuthIdentifier())
                ->whereIn('product_id', $products->pluck('id'))
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $products->map(function (Product $product) use ($ordered, $saved, $wishlisted, $newSince) {
            $orderedUnits = (int) ($ordered[$product->id] ?? 0);
            $saves        = (int) ($saved[$product->id] ?? 0);
            $discount     = (float) ($product->discount ?? 0);

            $product->setAttribute('is_wishlisted', in_array((int) $product->id, $wishlisted, true));

            return (object) [
                'product'         => $product,
                'ordered'         => $orderedUnits,
                'saves'           => $saves,
                'score'           => ($orderedUnits * self::ORDER_WEIGHT) + ($saves * self::SAVE_WEIGHT),
                'is_new'          => $product->created_at && $product->created_at->gte($newSince),
                'created_ts'      => $product->created_at?->timestamp ?? 0,
                'effective_price' => $discount > 0
                    ? round((float) $product->price * (1 - ($discount / 100)), 2)
                    : (float) $product->price,
            ];
        })->values();
    }

    /**
     * The top N trending products, in exactly the order the Trends page shows by default.
     * $excludeIds lets the caller skip products it is already showing.
     */
    public function top(int $limit, array $excludeIds = []): Collection
    {
        $excludeIds = array_map('intval', $excludeIds);

        return $this->all()
            ->reject(fn ($item) => in_array((int) $item->product->id, $excludeIds, true))
            ->sortBy([['score', 'desc'], ['created_ts', 'desc']])
            ->take($limit)
            ->values();
    }

    /** The short line shown under a trending product ("12 ordered this month"), or null. */
    public function caption(object $item): ?string
    {
        if ($item->ordered > 0) {
            return number_format($item->ordered) . ' ordered this month';
        }

        if ($item->saves > 0) {
            return number_format($item->saves) . ' ' . Str::plural('save', $item->saves) . ' this month';
        }

        return $item->is_new ? 'New arrival' : null;
    }
}