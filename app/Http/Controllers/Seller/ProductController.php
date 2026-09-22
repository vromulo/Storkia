<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::latest()->paginate(10);
        return view('pages.seller.products.index', compact('products'));
    }

    public function create()
    {
        return view('pages.seller.products.create');
    }

    public function inventory()
    {
        $products = Product::latest()->get(['id', 'name', 'stock_quantity']);
        return view('pages.seller.products.inventory', compact('products'));
    }

    public function archived()
    {
        $products = Product::onlyTrashed()->latest()->get();
        return view('pages.seller.products.archived', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'nullable|string',
            'subcategory' => 'required|string',
            'discount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'additional_descriptions' => 'nullable|string',
            'pictures' => 'required|array',
            'pictures.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',

            // Variant Validations
            'variant_title' => 'required|string|max:50',
            'variant_names' => 'required|array',
            'variant_names.*' => 'required|string|max:50',
            'variant_pictures' => 'nullable|array',
            'variant_pictures.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120'
        ]);

        $picturePaths = [];
        if ($request->hasFile('pictures')) {
            foreach ($request->file('pictures') as $file) {
                $picturePaths[] = $file->store('products', 'public');
            }
        }

        $priceDependency = !empty($request->sub_variant_title) ? 'sub' : 'main';
        $variantsData = [
            'title' => $request->variant_title,
            'sub_title' => $request->sub_variant_title,
            'price_dependency' => $priceDependency,
            'items' => []
        ];

        $minPrice = null;
        $baseWeight = null;
        $totalStock = 0;

        if ($request->has('variant_names')) {
            foreach ($request->variant_names as $vId => $vName) {
                $item = [
                    'name' => $vName,
                    'image' => null,
                    'price' => null,
                    'weight' => null,
                    'stock' => null,
                    'subs' => []
                ];

                if ($request->hasFile("variant_pictures.$vId")) {
                    $item['image'] = $request->file("variant_pictures.$vId")->store('variants', 'public');
                }

                if ($variantsData['price_dependency'] === 'main') {
                    $item['price'] = $request->variant_prices[$vId] ?? 0;
                    $item['weight'] = $request->variant_weights[$vId] ?? '0';
                    $item['stock'] = (int) ($request->variant_stocks[$vId] ?? 0);

                    $totalStock += $item['stock'];
                    if (is_null($minPrice) || $item['price'] < $minPrice) $minPrice = $item['price'];
                    if (is_null($baseWeight)) $baseWeight = $item['weight'];
                }

                if (!empty($request->sub_variant_names[$vId])) {
                    foreach ($request->sub_variant_names[$vId] as $sId => $sName) {
                        $sub = [
                            'name' => $sName,
                            'price' => null,
                            'weight' => null,
                            'stock' => null
                        ];

                        if ($variantsData['price_dependency'] === 'sub') {
                            $sub['price'] = $request->sub_variant_prices[$vId][$sId] ?? 0;
                            $sub['weight'] = $request->sub_variant_weights[$vId][$sId] ?? '0';
                            $sub['stock'] = (int) ($request->sub_variant_stocks[$vId][$sId] ?? 0);

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
            'name' => $request->name,
            'category' => $request->category,
            'subcategory' => $request->subcategory,
            'price' => $minPrice ?? 0,
            'weight' => $baseWeight ?? '0',
            'discount' => $request->discount ?? 0,
            'stock_quantity' => $totalStock,
            'description' => $request->description,
            'additional_descriptions' => $request->additional_descriptions,
            'pictures' => $picturePaths,
            'variants' => count($variantsData['items']) > 0 ? $variantsData : null,
        ]);

        return redirect()->route('seller.products.index')->with('success', 'Product added successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'discount' => 'nullable|numeric|min:0|max:100',
            'stock_quantity' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $product->update([
            'name' => $request->name,
            'discount' => $request->discount ?? 0,
            'stock_quantity' => $request->stock_quantity,
            'description' => $request->description,
        ]);

        return back()->with('success', 'Product updated successfully.');
    }

    public function archive(Product $product)
    {
        $product->delete();
        return back()->with('success', 'Product archived.');
    }

    public function unarchive($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        return back()->with('success', 'Product unarchived.');
    }

    public function forceDelete($id)
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