<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductRequest;
use App\Http\Requests\Seller\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::latest()->paginate(10);
        return view('seller.products.index', compact('products'));
    }

    public function create()
    {
        return view('seller.products.create');
    }

    public function inventory()
    {
        $products = Product::latest()->get(['id', 'name', 'stock_quantity']);
        return view('seller.products.inventory', compact('products'));
    }

    public function archived()
    {
        $products = Product::onlyTrashed()->latest()->get();
        return view('seller.products.archived', compact('products'));
    }

    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();

        $picturePaths = [];
        if ($request->hasFile('pictures')) {
            foreach ($request->file('pictures') as $file) {
                $picturePaths[] = $file->store('products', 'public');
            }
        }

        $priceDependency = !empty($validated['sub_variant_title']) ? 'sub' : 'main';
        $variantsData = [
            'title'            => $validated['variant_title'],
            'sub_title'        => $validated['sub_variant_title'] ?? null,
            'price_dependency' => $priceDependency,
            'items'            => []
        ];

        $minPrice = null;
        $baseWeight = null;
        $totalStock = 0;

        if (!empty($validated['variant_names'])) {
            foreach ($validated['variant_names'] as $vId => $vName) {
                $item = [
                    'name'  => $vName,
                    'image' => null,
                    'price' => null,
                    'weight'=> null,
                    'stock' => null,
                    'subs'  => []
                ];

                if ($request->hasFile("variant_pictures.{$vId}")) {
                    $item['image'] = $request->file("variant_pictures.{$vId}")->store('variants', 'public');
                }

                if ($priceDependency === 'main') {
                    $item['price']  = (float) ($validated['variant_prices'][$vId] ?? 0);
                    $item['weight'] = (float) ($validated['variant_weights'][$vId] ?? 0);
                    $item['stock']  = (int) ($validated['variant_stocks'][$vId] ?? 0);

                    $totalStock += $item['stock'];
                    if (is_null($minPrice) || $item['price'] < $minPrice) $minPrice = $item['price'];
                    if (is_null($baseWeight)) $baseWeight = $item['weight'];
                }

                if (!empty($validated['sub_variant_names'][$vId])) {
                    foreach ($validated['sub_variant_names'][$vId] as $sId => $sName) {
                        $sub = [
                            'name'  => $sName,
                            'price' => null,
                            'weight'=> null,
                            'stock' => null
                        ];

                        if ($priceDependency === 'sub') {
                            $sub['price']  = (float) ($validated['sub_variant_prices'][$vId][$sId] ?? 0);
                            $sub['weight'] = (float) ($validated['sub_variant_weights'][$vId][$sId] ?? 0);
                            $sub['stock']  = (int) ($validated['sub_variant_stocks'][$vId][$sId] ?? 0);

                            $totalStock += $sub['stock'];
                            if (is_null($minPrice) || $sub['price'] < $minPrice) $minPrice = $sub['price'];
                            if (is_null($baseWeight)) $baseWeight = $sub['weight'];
                        }

                        $item['subs'][] = $sub;
                    }
                }

                $variantsData['items'][] = $item;
            }
        }

        Product::create([
            'name'                    => $validated['name'],
            'category'                => $validated['category'] ?? null,
            'subcategory'             => $validated['subcategory'],
            'price'                   => $minPrice ?? 0,
            'weight'                  => $baseWeight ?? '0',
            'discount'                => $validated['discount'] ?? 0,
            'stock_quantity'          => $totalStock,
            'description'             => $validated['description'] ?? null,
            'additional_descriptions' => $validated['additional_descriptions'] ?? null,
            'pictures'                => $picturePaths,
            'variants'                => count($variantsData['items']) > 0 ? $variantsData : null,
        ]);

        return redirect()->route('seller.products.index')->with('success', 'Product added successfully.');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $validated = $request->validated();

        $product->update([
            'name'        => $validated['name'],
            'discount'    => $validated['discount'] ?? 0,
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', 'Product updated successfully.');
    }

    public function archive(Product $product)
    {
        $product->delete();
        return back()->with('success', 'Product archived.');
    }

    public function unarchive(int $id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        return back()->with('success', 'Product unarchived.');
    }

    public function forceDelete(int $id)
    {
        $product = Product::withTrashed()->findOrFail($id);

        if ($product->pictures && is_array($product->pictures)) {
            foreach ($product->pictures as $pic) {
                Storage::disk('public')->delete($pic);
            }
        }

        if ($product->variants && isset($product->variants['items']) && is_array($product->variants['items'])) {
            foreach ($product->variants['items'] as $variant) {
                if (isset($variant['image']) && $variant['image']) {
                    Storage::disk('public')->delete($variant['image']);
                }
            }
        }

        $product->forceDelete();
        return back()->with('success', 'Product permanently deleted.');
    }
}