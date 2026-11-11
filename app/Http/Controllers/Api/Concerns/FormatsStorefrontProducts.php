<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\BuyerWishlist;
use App\Models\OrderItem;
use App\Models\OrderItemReview;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

trait FormatsStorefrontProducts
{
    protected function storefrontUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    protected function applyStorefrontVisibility(Builder $query): Builder
    {
        $query->where('stock_quantity', '>', 0)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'));

        if (Schema::hasColumn('products', 'publish_status')) {
            $query->whereNotIn('publish_status', ['draft', 'inactive']);
        }

        return $query;
    }

    protected function isStorefrontVisible(Product $product): bool
    {
        return (int) $product->stock_quantity > 0
            && $product->approval?->status === 'Approved'
            && ! in_array($product->publish_status ?? 'active', ['draft', 'inactive'], true);
    }

    protected function prepareStorefrontProducts(Collection $products, ?int $buyerId = null): void
    {
        if ($products->isEmpty()) {
            return;
        }

        (new EloquentCollection($products->values()->all()))
            ->loadMissing(['approval', 'user.sellerProfile', 'sellerProfile']);

        $productIds = $products->pluck('id')->all();

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

        $wishlistedIds = ($buyerId && Schema::hasTable('buyer_wishlists'))
            ? BuyerWishlist::where('buyer_id', $buyerId)
                ->whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        foreach ($products as $product) {
            $stat = $reviewStats->get($product->id);

            $product->setAttribute('sold_count', (int) ($soldCounts[$product->id] ?? 0));
            $product->setAttribute('avg_rating', $stat ? round((float) $stat->avg_rating, 1) : 0.0);
            $product->setAttribute('reviews_count', $stat ? (int) $stat->reviews_count : 0);
            $product->setAttribute('is_wishlisted', in_array((int) $product->id, $wishlistedIds, true));
        }
    }

    protected function storefrontProduct(Product $product, array $extra = []): array
    {
        $seller = $product->user?->sellerProfile ?? $product->sellerProfile;

        $payload = $product->toArray();
        unset($payload['user']);

        if ($seller) {
            $payload['seller_profile'] = $seller->toArray();
        }

        return array_merge($payload, $extra);
    }

    protected function storefrontSeller(SellerProfile $seller): array
    {
        return [
            'id'               => (int) $seller->id,
            'user_id'          => (int) $seller->user_id,
            'business_name'    => $seller->business_name,
            'line_of_business' => $seller->line_of_business,
            'logo_path'        => $seller->logo_path,
            'logo_url'         => $this->storefrontUrl($seller->logo_path),
            'is_on_vacation'   => (bool) ($seller->is_on_vacation ?? false),
        ];
    }

    protected function storefrontPage(Collection $items, Request $request, int $defaultPerPage = 20, int $maxPerPage = 50): array
    {
        $perPage = min(max((int) $request->query('per_page', $defaultPerPage), 1), $maxPerPage);
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max((int) $request->query('page', 1), 1);

        return [
            $items->forPage($page, $perPage)->values(),
            [
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
            ],
        ];
    }

    protected function storefrontCategoryCounts(Collection $categoryNames): array
    {
        return $categoryNames
            ->map(fn ($name) => (string) $name)
            ->filter()
            ->countBy()
            ->sortKeys()
            ->map(fn ($count, $name) => ['name' => (string) $name, 'count' => (int) $count])
            ->values()
            ->all();
    }

    protected function storefrontFailure(string $message, int $status, string $code, array $errors = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'code'    => $code,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}