<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $products = Product::where('stock_quantity', '>', 0)
            ->latest()
            ->get();

        return view('buyer.home', compact('products'));
    }
}