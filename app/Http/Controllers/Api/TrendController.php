<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsStorefrontProducts;
use App\Http\Controllers\Controller;
use App\Services\SearchLogService;
use App\Services\TrendingProductsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TrendController extends Controller
{
    use FormatsStorefrontProducts;

    private const SORTS = [
        'trending'  => 'Most trending',
        'ordered'   => 'Most ordered',
        'newest'    => 'Newest',
        'price_low' => 'Price: low to high',
    ];

    public function __construct(
        protected SearchLogService $searchLog,
        protected TrendingProductsService $trending
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sortParam = $request->query('sort');
        $sort = is_string($sortParam) && isset(self::SORTS[$sortParam]) ? $sortParam : 'trending';

        $categoryParam = $request->query('category');
        $category = is_string($categoryParam) && $categoryParam !== '' ? $categoryParam : null;

        $user = $request->user();

        $allTrends = $this->trending->all($user)
            ->reject(fn ($item) => in_array($item->product->publish_status ?? 'active', ['draft', 'inactive'], true))
            ->values();

        $categories = $this->storefrontCategoryCounts(
            $allTrends->map(fn ($item) => $item->product->category)
        );

        $trends = $category !== null
            ? $allTrends->filter(fn ($item) => $item->product->category === $category)
            : $allTrends;

        $trends = match ($sort) {
            'ordered'   => $trends->sortBy([['ordered', 'desc'], ['score', 'desc'], ['created_ts', 'desc']]),
            'newest'    => $trends->sortBy([['created_ts', 'desc']]),
            'price_low' => $trends->sortBy([['effective_price', 'asc'], ['score', 'desc']]),
            default     => $trends->sortBy([['score', 'desc'], ['created_ts', 'desc']]),
        };

        [$pageItems, $pagination] = $this->storefrontPage($trends->values(), $request);

        $this->prepareStorefrontProducts(
            $pageItems->map(fn ($item) => $item->product),
            $user ? (int) $user->id : null
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'trends'       => $pageItems->map(fn ($item) => $this->presentTrend($item))->values()->all(),
                'total_all'    => $allTrends->count(),
                'categories'   => $categories,
                'category'     => $category,
                'sort'         => $sort,
                'sorts'        => $this->sortOptions(),
                'window_days'  => TrendingProductsService::WINDOW_DAYS,
                'top_searches' => $this->searchLog->topTerms(10)
                    ->map(fn ($row) => [
                        'term'      => $row->term,
                        'searchers' => (int) $row->searchers,
                        'searches'  => (int) $row->searches,
                    ])
                    ->values()
                    ->all(),
                'pagination'   => $pagination,
            ],
        ]);
    }

    protected function presentTrend(object $item): array
    {
        return [
            'product'         => $this->storefrontProduct($item->product),
            'ordered'         => (int) $item->ordered,
            'saves'           => (int) $item->saves,
            'score'           => (int) $item->score,
            'is_new'          => (bool) $item->is_new,
            'caption'         => $this->trending->caption($item),
            'effective_price' => (float) $item->effective_price,
        ];
    }

    protected function sortOptions(): array
    {
        $options = [];

        foreach (self::SORTS as $key => $label) {
            $options[] = ['key' => $key, 'label' => $label];
        }

        return $options;
    }
}