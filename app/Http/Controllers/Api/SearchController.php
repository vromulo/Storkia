<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Product;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->input('q');
        $category = $request->input('category');
        $subcategory = $request->input('subcategory');

        $productsQuery = Product::query()
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', function ($q) {
                $q->where('status', 'Approved');
            });

        if ($query) {
            $words = array_filter(explode(' ', $query), fn($w) => strlen($w) > 2);

            $productsQuery->where(function($qBuilder) use ($query, $words) {
                $qBuilder->where('name', 'LIKE', "%{$query}%")
                         ->orWhere('category', 'LIKE', "%{$query}%");

                foreach ($words as $word) {
                    $qBuilder->orWhere('name', 'LIKE', "%{$word}%");
                }
            });
        }

        if ($category) $productsQuery->where('category', $category);
        if ($subcategory) $productsQuery->where('subcategory', $subcategory);

        $products = $productsQuery->paginate(16);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->input('q');
        
        if (!$query || strlen($query) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $words = array_filter(explode(' ', $query), fn($w) => strlen($w) > 2);
        
        $productsQuery = Product::query()
            ->where('stock_quantity', '>', 0)
            ->whereHas('approval', function ($q) {
                $q->where('status', 'Approved');
            });

        $productsQuery->where(function($qBuilder) use ($query, $words) {
            $qBuilder->where('name', 'LIKE', "%{$query}%");
            foreach ($words as $word) {
                $qBuilder->orWhere('name', 'LIKE', "%{$word}%");
            }
        });

        $topMatches = $productsQuery->select('name', 'category')->distinct()->limit(5)->get();

        $suggestions = [];
        $suggestions[] = ['name' => $query, 'category' => ''];

        if ($topMatches->isNotEmpty()) {
            $topName = $topMatches->first()->name;
            
            if (strtolower($topName) !== strtolower($query)) {
                $suggestions[] = ['name' => $topName, 'category' => ''];
            }

            $matchedCategories = $topMatches->pluck('category')->unique()->filter();

            foreach ($matchedCategories as $cat) {
                $suggestions[] = ['name' => $query, 'category' => $cat];
            }
        }

        return response()->json([
            'success' => true,
            'data' => array_values($suggestions)
        ]);
    }
}