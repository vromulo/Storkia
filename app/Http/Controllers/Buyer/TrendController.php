<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\SearchLogService;
use App\Services\TrendingProductsService;
use Illuminate\Http\Request;

class TrendController extends Controller
{
    public function __construct(
        protected SearchLogService $searchLog,
        protected TrendingProductsService $trending
    ) {}

    private const SORTS = [
        'trending'  => 'Most trending',
        'ordered'   => 'Most ordered',
        'newest'    => 'Newest',
        'price_low' => 'Price: low to high',
    ];

    public function index(Request $request)
    {
        $sortParam = $request->query('sort');
        $sort = is_string($sortParam) && isset(self::SORTS[$sortParam]) ? $sortParam : 'trending';

        $categoryParam = $request->query('category');
        $category = is_string($categoryParam) && $categoryParam !== '' ? $categoryParam : null;

        $allTrends = $this->trending->all($request->user());

        $categories = $allTrends
            ->map(fn ($item) => (string) $item->product->category)
            ->filter()
            ->countBy()
            ->sortKeys()
            ->all();

        $trends = $category !== null
            ? $allTrends->filter(fn ($item) => $item->product->category === $category)
            : $allTrends;

        $trends = match ($sort) {
            'ordered'   => $trends->sortBy([['ordered', 'desc'], ['score', 'desc'], ['created_ts', 'desc']]),
            'newest'    => $trends->sortBy([['created_ts', 'desc']]),
            'price_low' => $trends->sortBy([['effective_price', 'asc'], ['score', 'desc']]),
            default     => $trends->sortBy([['score', 'desc'], ['created_ts', 'desc']]),
        };
        $trends = $trends->values();

        return view('buyer.trends.index', [
            'trends'     => $trends,
            'totalAll'   => $allTrends->count(),
            'categories' => $categories,
            'category'   => $category,
            'sorts'      => self::SORTS,
            'sort'       => $sort,
            'windowDays' => TrendingProductsService::WINDOW_DAYS,
            'topSearches' => $this->searchLog->topTerms(10),
        ]);
    }
}