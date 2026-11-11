<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\LoadsSuperDeals;
use App\Http\Controllers\Api\Concerns\QueriesAnnouncements;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\SearchLogService;
use App\Services\StorkSchemaBootstrapService;
use App\Services\SuperDealPricingService;
use App\Services\TrendingProductsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HomeFeedController extends Controller
{
    use LoadsSuperDeals;
    use QueriesAnnouncements;

    public function __construct(
        protected SuperDealPricingService $pricing,
        protected TrendingProductsService $trending,
        protected SearchLogService $searchLog
    ) {}

    public function index(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $buyerId = $user ? (int) $user->id : null;

        $dealsLimit = min(max((int) $request->query('deals_limit', 6), 1), 12);
        $trendLimit = min(max((int) $request->query('trend_limit', 6), 1), 12);

        $dealEntries = $this->loadSuperDealEntries($this->pricing);

        $readIds = $buyerId ? $this->buyerReadAnnouncementIds($buyerId) : [];

        return response()->json([
            'success' => true,
            'data'    => [
                'banners'              => $this->banners(),
                'super_deals'          => [
                    'items' => $this->presentSuperDealEntries($dealEntries->take($dealsLimit)->values(), $buyerId),
                    'total' => $dealEntries->count(),
                ],
                'trending'             => $this->trendingTile($trendLimit, $buyerId),
                'bundle_count'         => $this->activeBundleCount(),
                'unread_announcements' => $this->unreadAnnouncementCount($readIds),
                'server_time'          => now()->toIso8601String(),
            ],
        ]);
    }

    protected function banners(): array
    {
        if (! Schema::hasTable('hero_banners')) {
            return [];
        }

        return DB::table('hero_banners')
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn ($banner) => [
                'id'         => (int) $banner->id,
                'title'      => $banner->title,
                'subtitle'   => $banner->subtitle,
                'badge_text' => $banner->badge_text,
                'cta_text'   => $banner->cta_text ?: 'Shop Now',
                'cta_url'    => $banner->cta_url,
                'cta_target' => $this->resolveBannerTarget($banner->cta_url),
                'image_url'  => $this->storefrontUrl($banner->image_path),
                'sort_order' => (int) $banner->sort_order,
            ])
            ->values()
            ->all();
    }

    protected function resolveBannerTarget(?string $url): array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return ['type' => 'home', 'value' => null];
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $queryString = (string) parse_url($url, PHP_URL_QUERY);

        if (preg_match('#^https?://#i', $url)) {
            $host = parse_url($url, PHP_URL_HOST);
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

            if ($host && $appHost && strcasecmp($host, $appHost) !== 0) {
                return ['type' => 'external', 'value' => $url];
            }
        }

        $path = '/' . trim($path, '/');

        $static = [
            '/'              => 'home',
            '/shop'          => 'home',
            '/super-deals'   => 'deals',
            '/bundles'       => 'bundles',
            '/trends'        => 'trends',
            '/announcements' => 'announcements',
            '/sellers'       => 'sellers',
        ];

        if (isset($static[$path])) {
            return ['type' => $static[$path], 'value' => null];
        }

        if (preg_match('#^/product/(\d+)$#', $path, $m)) {
            return ['type' => 'product', 'value' => $m[1]];
        }

        if (preg_match('#^/sellers/(\d+)$#', $path, $m)) {
            return ['type' => 'seller', 'value' => $m[1]];
        }

        if (preg_match('#^/category/([^/]+)$#', $path, $m)) {
            return ['type' => 'category', 'value' => urldecode($m[1])];
        }

        if ($path === '/search') {
            parse_str($queryString, $params);
            $term = isset($params['q']) && is_string($params['q']) ? trim($params['q']) : '';

            return ['type' => 'search', 'value' => $term !== '' ? $term : null];
        }

        return ['type' => 'none', 'value' => null];
    }

    protected function isListable(Product $product): bool
    {
        return ! in_array($product->publish_status ?? 'active', ['draft', 'inactive'], true);
    }

    protected function trendingTile(int $limit, ?int $buyerId): array
    {
        $tile = collect();

        foreach ($this->searchLog->topSearchedProducts($limit + 2) as $hit) {
            if (! $this->isListable($hit->product)) {
                continue;
            }

            $tile->push([
                'product' => $hit->product,
                'label'   => 'Searched "' . $hit->term . '"',
                'detail'  => number_format($hit->searchers) . ' ' . Str::plural('shopper', $hit->searchers),
                'source'  => 'search',
            ]);
        }

        $tile = $tile->take($limit)->values();

        if ($tile->count() < $limit) {
            $exclude = $tile->map(fn (array $item) => (int) $item['product']->id)->all();

            foreach ($this->trending->top(($limit - $tile->count()) + 6, $exclude) as $item) {
                if ($tile->count() >= $limit) {
                    break;
                }

                if (! $this->isListable($item->product)) {
                    continue;
                }

                $tile->push([
                    'product' => $item->product,
                    'label'   => $this->trending->caption($item),
                    'detail'  => null,
                    'source'  => 'trending',
                ]);
            }
        }

        if ($tile->isEmpty()) {
            return [];
        }

        $this->prepareStorefrontProducts($tile->pluck('product'), $buyerId);

        return $tile->map(fn (array $item) => [
            'product' => $this->storefrontProduct($item['product']),
            'label'   => $item['label'],
            'detail'  => $item['detail'],
            'source'  => $item['source'],
        ])->values()->all();
    }

    protected function activeBundleCount(): int
    {
        if (! Schema::hasTable('bundle_deals')) {
            return 0;
        }

        $query = DB::table('bundle_deals')
            ->join('seller_profiles', 'seller_profiles.id', '=', 'bundle_deals.seller_profile_id')
            ->where('bundle_deals.status', 'active')
            ->where('bundle_deals.starts_at', '<=', now())
            ->where('bundle_deals.ends_at', '>=', now());

        if (Schema::hasColumn('seller_profiles', 'is_on_vacation')) {
            $query->where('seller_profiles.is_on_vacation', false);
        }

        return (int) $query->count();
    }
}