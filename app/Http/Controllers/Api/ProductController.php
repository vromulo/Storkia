<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Eager load the sellerProfile so it is included in the JSON response
        $query = Product::with('sellerProfile')->where('stock_quantity', '>', 0);
        
        // Ensure you only get approved products if you have an approval system setup
        // $query->whereHas('approval', function ($q) { $q->where('status', 'Approved'); });

        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        if ($request->has('subcategory') && !empty($request->subcategory) && $request->subcategory !== 'All') {
            $query->where('subcategory', $request->subcategory);
        }

        $perPage = $request->get('per_page', 6);
        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $products
        ], 200);
    }
}