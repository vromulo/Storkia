<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductRequest;
use App\Http\Requests\Seller\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductApproval;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;

class ProductController extends Controller
{
    /**
     * Helper method to convert, resize, and store images in WebP format
     * Updated to use Intervention Image v4 syntax.
     */
    private function optimizeAndStoreImage($file, $folder)
    {
        $extension = $file->getClientOriginalExtension();
        
        // Skip conversion for vector graphics or animated gifs to preserve their nature
        if (in_array(strtolower($extension), ['svg', 'gif'])) {
            return $file->store($folder, 'public');
        }

        $filename = uniqid('img_') . '_' . time() . '.webp';
        $path = $folder . '/' . $filename;
        
        // Initialize Intervention Image Manager with the GD driver (v4 syntax)
        $manager = ImageManager::usingDriver(Driver::class);
        
        // Read (decode), resize (maintaining aspect ratio, only scaling down), and encode to WebP
        $image = $manager->decode($file->getRealPath());
        $image->scaleDown(width: 1200, height: 1200);
        $encoded = $image->encode(new WebpEncoder(quality: 80));
            
        // Store the encoded string
        Storage::disk('public')->put($path, $encoded->toString());
        
        return $path;
    }

    public function index()
    {
        $products = Product::with('approval')->where('user_id', auth()->id())->latest()->paginate(10);
        return view('seller.products.index', compact('products'));
    }

    public function create()
    {
        return view('seller.products.create');
    }

    public function inventory()
    {
        $products = Product::with('approval')->where('user_id', auth()->id())->latest()->get(['id', 'name', 'stock_quantity']);
        return view('seller.products.inventory', compact('products'));
    }

    public function archived()
    {
        $products = Product::with('approval')->onlyTrashed()->where('user_id', auth()->id())->latest()->get();
        return view('seller.products.archived', compact('products'));
    }

    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();

        $picturePaths = [];
        if ($request->hasFile('pictures')) {
            foreach ($request->file('pictures') as $file) {
                $picturePaths[] = $this->optimizeAndStoreImage($file, 'products');
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
                    $item['image'] = $this->optimizeAndStoreImage($request->file("variant_pictures.{$vId}"), 'variants');
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

        $product = Product::create([
            'user_id'                 => auth()->id(),
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

        ProductApproval::create([
            'product_id' => $product->id,
            'user_id'    => auth()->id(),
            'status'     => 'Pending',
        ]);

        return redirect()->route('seller.products.index')->with('success', 'Product submitted and pending review.');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        if ($product->user_id !== auth()->id()) abort(403, 'Unauthorized action.');
        $validated = $request->validated();

        $dataToUpdate = [
            'name'                    => $validated['name'],
            'subcategory'             => $validated['subcategory'],
            'discount'                => $validated['discount'] ?? 0,
            'description'             => $validated['description'] ?? null,
            'additional_descriptions' => $validated['additional_descriptions'] ?? null,
        ];

        // Process main picture replacements
        if ($request->hasFile('pictures')) {
            if ($product->pictures && is_array($product->pictures)) {
                foreach ($product->pictures as $pic) {
                    Storage::disk('public')->delete($pic);
                }
            }
            $picturePaths = [];
            foreach ($request->file('pictures') as $file) {
                $picturePaths[] = $this->optimizeAndStoreImage($file, 'products');
            }
            $dataToUpdate['pictures'] = $picturePaths;
        }

        // Process Variant Updates
        if ($request->has('variant_names') && $product->variants) {
            $variantsData = $product->variants;
            $totalStock = 0;
            $minPrice = null;
            $baseWeight = null;

            foreach ($request->variant_names as $vIndex => $vName) {
                if (isset($variantsData['items'][$vIndex])) {
                    $variantsData['items'][$vIndex]['name'] = $vName;

                    // Replace variant image if uploaded
                    if ($request->hasFile("variant_pictures.{$vIndex}")) {
                        if (!empty($variantsData['items'][$vIndex]['image'])) {
                            Storage::disk('public')->delete($variantsData['items'][$vIndex]['image']);
                        }
                        $variantsData['items'][$vIndex]['image'] = $this->optimizeAndStoreImage($request->file("variant_pictures.{$vIndex}"), 'variants');
                    }

                    if ($variantsData['price_dependency'] === 'main') {
                        $variantsData['items'][$vIndex]['price'] = (float) ($request->variant_prices[$vIndex] ?? $variantsData['items'][$vIndex]['price']);
                        $variantsData['items'][$vIndex]['weight'] = (float) ($request->variant_weights[$vIndex] ?? $variantsData['items'][$vIndex]['weight']);
                        $variantsData['items'][$vIndex]['stock'] = (int) ($request->variant_stocks[$vIndex] ?? $variantsData['items'][$vIndex]['stock']);

                        $totalStock += $variantsData['items'][$vIndex]['stock'];
                        if (is_null($minPrice) || $variantsData['items'][$vIndex]['price'] < $minPrice) $minPrice = $variantsData['items'][$vIndex]['price'];
                        if (is_null($baseWeight)) $baseWeight = $variantsData['items'][$vIndex]['weight'];
                    }

                    if (isset($variantsData['items'][$vIndex]['subs'])) {
                        foreach ($variantsData['items'][$vIndex]['subs'] as $sIndex => $sub) {
                            if (isset($request->sub_variant_names[$vIndex][$sIndex])) {
                                $variantsData['items'][$vIndex]['subs'][$sIndex]['name'] = $request->sub_variant_names[$vIndex][$sIndex];

                                if ($variantsData['price_dependency'] === 'sub') {
                                    $variantsData['items'][$vIndex]['subs'][$sIndex]['price'] = (float) ($request->sub_variant_prices[$vIndex][$sIndex] ?? $sub['price']);
                                    $variantsData['items'][$vIndex]['subs'][$sIndex]['weight'] = (float) ($request->sub_variant_weights[$vIndex][$sIndex] ?? $sub['weight']);
                                    $variantsData['items'][$vIndex]['subs'][$sIndex]['stock'] = (int) ($request->sub_variant_stocks[$vIndex][$sIndex] ?? $sub['stock']);

                                    $totalStock += $variantsData['items'][$vIndex]['subs'][$sIndex]['stock'];
                                    if (is_null($minPrice) || $variantsData['items'][$vIndex]['subs'][$sIndex]['price'] < $minPrice) $minPrice = $variantsData['items'][$vIndex]['subs'][$sIndex]['price'];
                                    if (is_null($baseWeight)) $baseWeight = $variantsData['items'][$vIndex]['subs'][$sIndex]['weight'];
                                }
                            }
                        }
                    }
                }
            }

            $dataToUpdate['variants'] = $variantsData;
            $dataToUpdate['price'] = $minPrice ?? $product->price;
            $dataToUpdate['weight'] = $baseWeight ?? $product->weight;
            $dataToUpdate['stock_quantity'] = $totalStock;
        }

        $product->update($dataToUpdate);

        // Reset the product to pending approval if updated
        if($product->approval) {
            $product->approval->update([
                'status'           => 'Pending', 
                'disapproval_type' => null,
                'remarks'          => null
            ]);
        }

        return back()->with('success', 'Product updated and re-submitted for review.');
    }

    public function archive(Product $product)
    {
        if ($product->user_id !== auth()->id()) abort(403, 'Unauthorized action.');
        $product->delete();
        return back()->with('success', 'Product archived.');
    }

    public function unarchive(int $id)
    {
        $product = Product::withTrashed()->where('user_id', auth()->id())->findOrFail($id);
        $product->restore();
        return back()->with('success', 'Product unarchived.');
    }

    public function forceDelete(int $id)
    {
        $product = Product::withTrashed()->where('user_id', auth()->id())->findOrFail($id);
        if ($product->pictures && is_array($product->pictures)) {
            foreach ($product->pictures as $pic) Storage::disk('public')->delete($pic);
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