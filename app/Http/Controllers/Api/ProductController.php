<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('sellerProfile')->where('stock_quantity', '>', 0);

        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        if ($request->has('subcategory') && !empty($request->subcategory) && $request->subcategory !== 'All') {
            $query->where('subcategory', $request->subcategory);
        }

        if ($request->has('seller_id') && !empty($request->seller_id)) {
            $query->whereHas('sellerProfile', function ($q) use ($request) {
                $q->where('id', $request->seller_id);
            });
        }

        $perPage = $request->get('per_page', 6);
        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $products
        ], 200);
    }
}