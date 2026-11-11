<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\LoadsSuperDeals;
use App\Http\Controllers\Controller;
use App\Services\SuperDealPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    use LoadsSuperDeals;

    private const SORTS = [
        'ending'     => 'Ending soon',
        'discount'   => 'Biggest discount',
        'price_low'  => 'Price: low to high',
        'price_high' => 'Price: high to low',
    ];

    public function __construct(
        protected SuperDealPricingService $pricing
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sortParam = $request->query('sort');
        $sort = is_string($sortParam) && isset(self::SORTS[$sortParam]) ? $sortParam : 'ending';

        $categoryParam = $request->query('category');
        $category = is_string($categoryParam) && $categoryParam !== '' ? $categoryParam : null;

        $allDeals = $this->loadSuperDealEntries($this->pricing);

        $categories = $this->storefrontCategoryCounts(
            $allDeals->map(fn (array $deal) => $deal['product']->category)
        );

        $deals = $category !== null
            ? $allDeals->filter(fn (array $deal) => $deal['product']->category === $category)
            : $allDeals;

        $deals = match ($sort) {
            'discount'   => $deals->sortBy([['percent_off', 'desc'], ['ends_ts', 'asc']]),
            'price_low'  => $deals->sortBy([['deal_price', 'asc'], ['ends_ts', 'asc']]),
            'price_high' => $deals->sortBy([['deal_price', 'desc'], ['ends_ts', 'asc']]),
            default      => $deals->sortBy([['ends_ts', 'asc'], ['percent_off', 'desc']]),
        };

        [$pageEntries, $pagination] = $this->storefrontPage($deals->values(), $request);

        $buyerId = $request->user() ? (int) $request->user()->id : null;

        return response()->json([
            'success' => true,
            'data'    => [
                'deals'       => $this->presentSuperDealEntries($pageEntries, $buyerId),
                'total_all'   => $allDeals->count(),
                'categories'  => $categories,
                'category'    => $category,
                'sort'        => $sort,
                'sorts'       => $this->sortOptions(),
                'pagination'  => $pagination,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
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