<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductRequest;
use App\Http\Requests\Seller\UpdateProductRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductApproval;
use App\Models\SellerCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ProductController extends Controller
{
    public const MAX_SAFE_PRICE = 9999999.99;
    public const MAX_SAFE_WEIGHT_KG = 99999.99;
    public const MAX_SAFE_STOCK = 9999999;

    public static function getCategoriesList(): array
    {
        return [
            'Pet' => [
                ['name' => 'Dog Food & Treats'],
                ['name' => 'Cat Litter & Accessories'],
                ['name' => 'Aquariums & Fish Supplies'],
                ['name' => 'Bird Feeders & Food'],
                ['name' => 'Pet Grooming Products'],
                ['name' => 'Pet Health & Wellness'],
            ],
            'Kids' => [
                ['name' => 'Baby Clothes & Accessories'],
                ['name' => 'Toys & Games'],
                ['name' => 'Educational Materials'],
                ['name' => 'Strollers & Gear'],
                ['name' => 'Nursery Furniture'],
                ['name' => 'Safety and Health'],
            ],
            'Electronics' => [
                ['name' => 'Mobile Phones & Accessories'],
                ['name' => 'Laptops, Desktops & Monitors'],
                ['name' => 'Audio & Video Equipment'],
                ['name' => 'Smart Home Devices'],
                ['name' => 'Cameras & Photography'],
                ['name' => 'Wearable Technology'],
            ],
            'Home & Garden' => [
                ['name' => 'Kitchen Appliances'],
                ['name' => 'Furniture & Decor'],
                ['name' => 'Gardening Tools'],
                ['name' => 'Outdoor Living'],
                ['name' => 'Home Improvement Tools'],
                ['name' => 'Bedding & Bath'],
            ],
            'Women\'s' => [
                ['name' => 'Dresses & Skirts'],
                ['name' => 'Tops & Blouses'],
                ['name' => 'Activewear & Yoga Pants'],
                ['name' => 'Lingerie & Sleepwear'],
                ['name' => 'Jackets & Coats'],
                ['name' => 'Shoes & Accessories'],
            ],
            'Men\'s' => [
                ['name' => 'Suits & Blazers'],
                ['name' => 'Casual Shirts & Pants'],
                ['name' => 'Outerwear & Jackets'],
                ['name' => 'Activewear & Fitness Gear'],
                ['name' => 'Shoes & Accessories'],
                ['name' => 'Grooming Products'],
            ],
            'Health & Beauty' => [
                ['name' => 'Skincare Products'],
                ['name' => 'Haircare Solutions'],
                ['name' => 'Makeup & Cosmetics'],
                ['name' => 'Personal Care Appliances'],
                ['name' => 'Men\'s Grooming'],
                ['name' => 'Health Supplements'],
            ],
            'Books & Media' => [
                ['name' => 'Fiction & Non-Fiction Books'],
                ['name' => 'Magazines & Periodicals'],
                ['name' => 'Music CDs & Vinyl Records'],
                ['name' => 'Movie DVDs & Blu-ray'],
                ['name' => 'Video Games & Consoles'],
                ['name' => 'Educational DVDs'],
            ],
            'Sports & Outdoors' => [
                ['name' => 'Fitness Equipment'],
                ['name' => 'Camping & Hiking Gear'],
                ['name' => 'Sports Apparel'],
                ['name' => 'Cycling & Bikes'],
                ['name' => 'Water Sports'],
                ['name' => 'Team Sports Equipment'],
            ],
            'Food & Gourmet' => [
                ['name' => 'Baking Supplies & Ingredients'],
                ['name' => 'Coffee, Tea & Beverages'],
                ['name' => 'Snacks & Candy'],
                ['name' => 'Specialty Foods'],
                ['name' => 'Organic and Health Foods'],
                ['name' => 'Meal Kits & Prepped Foods'],
            ],
            'Furniture & Office' => [
                ['name' => 'Office Desks & Chairs'],
                ['name' => 'Storage Cabinets & Shelving'],
                ['name' => 'Conference & Meeting Furniture'],
                ['name' => 'Computer Tables & Workstations'],
                ['name' => 'Ergonomic Accessories'],
                ['name' => 'Office Lighting & Fixtures'],
            ],
            'Jewelry & Watches' => [
                ['name' => 'Necklaces & Pendants'],
                ['name' => 'Rings & Earrings'],
                ['name' => 'Bracelets & Bangles'],
                ['name' => 'Watches for Men & Women'],
                ['name' => 'Fashion Jewelry'],
                ['name' => 'Jewelry Storage & Care'],
            ],
        ];
    }

    private function getSellerAllowedCategories(): array
    {
        $profile = auth()->user()->sellerProfile;
        $primary = $profile->line_of_business ?? 'Pet';
        $others = $profile ? $profile->otherCategories()->orderBy('created_at', 'asc')->pluck('category')->toArray() : [];

        return array_values(array_unique(array_merge([$primary], $others)));
    }

    public function hasUnfinishedOrders(int $productId, ?string $variantName = null): bool
    {
        $unfinishedStatuses = [
            'pending_seller_acceptance',
            'pending_buyer_response',
            'accepted',
            'preparing',
            'ready_for_pickup',
            'pickup_assigned',
            'picked_up',
            'at_logistics_hub',
            'out_for_delivery',
            'delivered',
            'delivery_failed',
            'disputed',
        ];

        return OrderItem::where('product_id', $productId)
            ->when($variantName !== null && $variantName !== '', function ($q) use ($variantName) {
                $q->where(function ($vq) use ($variantName) {
                    $vq->where('variant_name', $variantName)
                       ->orWhere('sub_variant_name', $variantName);
                });
            })
            ->whereHas('sellerOrder', fn ($sq) => $sq->whereIn('status', $unfinishedStatuses))
            ->exists();
    }

    private function sanitizeNumericPrice(mixed $rawPrice, string $fieldLabel = 'Price'): float
    {
        if (! is_numeric($rawPrice)) {
            throw ValidationException::withMessages([
                'price' => "{$fieldLabel} must be a valid number.",
            ]);
        }

        $value = (float) $rawPrice;
        if (! is_finite($value) || $value < 0 || $value > self::MAX_SAFE_PRICE) {
            throw ValidationException::withMessages([
                'price' => "{$fieldLabel} cannot exceed ₱" . number_format(self::MAX_SAFE_PRICE, 2) . '.',
            ]);
        }

        return round($value, 2);
    }

    private function sanitizeNumericWeight(mixed $rawWeight, string $fieldLabel = 'Weight'): float
    {
        if (! is_numeric($rawWeight)) {
            throw ValidationException::withMessages([
                'weight' => "{$fieldLabel} must be a valid number in kilograms.",
            ]);
        }

        $value = (float) $rawWeight;
        if (! is_finite($value) || $value < 0 || $value > self::MAX_SAFE_WEIGHT_KG) {
            throw ValidationException::withMessages([
                'weight' => "{$fieldLabel} cannot exceed " . number_format(self::MAX_SAFE_WEIGHT_KG, 2) . ' kg.',
            ]);
        }

        return round($value, 3);
    }

    private function sanitizeIntegerStock(mixed $rawStock, string $fieldLabel = 'Stock'): int
    {
        if (! is_numeric($rawStock)) {
            throw ValidationException::withMessages([
                'stock' => "{$fieldLabel} must be a valid whole number.",
            ]);
        }

        $value = (int) $rawStock;
        if ($value < 0 || $value > self::MAX_SAFE_STOCK) {
            throw ValidationException::withMessages([
                'stock' => "{$fieldLabel} cannot exceed " . number_format(self::MAX_SAFE_STOCK) . ' units.',
            ]);
        }

        return $value;
    }

    private function optimizeAndStoreImage($file, $folder)
    {
        $extension = $file->getClientOriginalExtension();
        if (in_array(strtolower($extension), ['svg', 'gif'])) {
            return $file->store($folder, 'public');
        }

        $filename = uniqid('img_') . '_' . time() . '.webp';
        $path = $folder . '/' . $filename;

        $manager = ImageManager::usingDriver(Driver::class);
        $image = $manager->decode($file->getRealPath());
        $image->scaleDown(width: 1200, height: 1200);
        $encoded = $image->encode(new WebpEncoder(quality: 80));

        Storage::disk('public')->put($path, $encoded->toString());

        return $path;
    }

    public function index(Request $request)
    {
        $categoriesList = self::getCategoriesList();
        $sellerProfile = auth()->user()->sellerProfile;
        $primaryCategory = $sellerProfile->line_of_business ?? 'Pet';
        $otherCategories = $sellerProfile ? $sellerProfile->otherCategories()->orderBy('created_at', 'asc')->get() : collect();
        $allowedCategories = $this->getSellerAllowedCategories();

        $selectedCategory = $request->query('category');
        if ($selectedCategory !== null && ! in_array($selectedCategory, $allowedCategories, true)) {
            return redirect()->route('seller.products.index');
        }

        $categoryCounts = [];
        foreach ($allowedCategories as $catName) {
            if ($catName === $primaryCategory) {
                $categoryCounts[$catName] = Product::where('user_id', auth()->id())
                    ->where(function ($q) use ($primaryCategory) {
                        $q->where('category', $primaryCategory)->orWhereNull('category');
                    })
                    ->count();
            } else {
                $categoryCounts[$catName] = Product::where('user_id', auth()->id())
                    ->where('category', $catName)
                    ->count();
            }
        }

        $availableCategoriesToAdd = array_values(array_diff(array_keys($categoriesList), $allowedCategories));

        $productsQuery = Product::with(['approval', 'specifications'])->where('user_id', auth()->id());

        if ($selectedCategory !== null) {
            if ($selectedCategory === $primaryCategory) {
                $productsQuery->where(function ($q) use ($primaryCategory) {
                    $q->where('category', $primaryCategory)->orWhereNull('category');
                });
            } else {
                $productsQuery->where('category', $selectedCategory);
            }
        }

        $products = $productsQuery
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('seller.products.index', compact(
            'products',
            'categoriesList',
            'primaryCategory',
            'otherCategories',
            'categoryCounts',
            'availableCategoriesToAdd',
            'selectedCategory'
        ));
    }

    public function storeCategory(Request $request)
    {
        $categoriesList = self::getCategoriesList();
        $allowedCategories = $this->getSellerAllowedCategories();

        $validated = $request->validate([
            'category' => [
                'required',
                'string',
                Rule::in(array_keys($categoriesList)),
                Rule::notIn($allowedCategories),
            ],
        ], [
            'category.not_in' => 'You have already added this category.',
        ]);

        $sellerProfile = auth()->user()->sellerProfile;

        SellerCategory::create([
            'seller_profile_id' => $sellerProfile->id,
            'category'          => $validated['category'],
        ]);

        return redirect()->route('seller.products.index')->with('success', "Category \"{$validated['category']}\" added successfully.");
    }

    public function destroyCategory(SellerCategory $sellerCategory)
    {
        $sellerProfile = auth()->user()->sellerProfile;

        if (! $sellerProfile || $sellerCategory->seller_profile_id !== $sellerProfile->id) {
            abort(403, 'Unauthorized action.');
        }

        // Move any products in the deleted secondary category to 'Uncategorized' or primary category (SEL-SCAT-009)
        $primaryCategory = $sellerProfile->line_of_business ?? 'Pet';
        Product::where('user_id', auth()->id())
            ->where('category', $sellerCategory->category)
            ->update([
                'category'    => $primaryCategory,
                'subcategory' => 'Uncategorized',
            ]);

        $categoryName = $sellerCategory->category;
        $sellerCategory->delete();

        return redirect()->route('seller.products.index')->with('success', "Category \"{$categoryName}\" removed. Any products inside it were moved to Uncategorized.");
    }

    public function create(Request $request)
    {
        $categoriesList = self::getCategoriesList();
        $primaryCategory = auth()->user()->sellerProfile->line_of_business ?? 'Pet';
        $allowedCategories = $this->getSellerAllowedCategories();

        $selectedCategory = $request->query('category', $primaryCategory);
        if (! in_array($selectedCategory, $allowedCategories, true)) {
            $selectedCategory = $primaryCategory;
        }

        return view('seller.products.create', compact('categoriesList', 'selectedCategory'));
    }

    public function inventory()
    {
        $products = Product::with('approval')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'name', 'stock_quantity']);

        return view('seller.products.inventory', compact('products'));
    }

    public function archived()
    {
        $products = Product::with('approval')
            ->onlyTrashed()
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return view('seller.products.archived', compact('products'));
    }

    public function store(StoreProductRequest $request)
    {
        $isDraft = $request->boolean('save_as_draft');
        $validated = $request->validated();
        $categoriesList = self::getCategoriesList();
        $allowedCategories = $this->getSellerAllowedCategories();
        $primaryCategory = auth()->user()->sellerProfile->line_of_business ?? 'Pet';

        $targetCategory = $validated['category'] ?? $primaryCategory;
        if (! in_array($targetCategory, $allowedCategories, true)) {
            return back()->withErrors(['category' => 'You are not authorized to add products to this category.'])->withInput();
        }

        $validSubcategories = array_column($categoriesList[$targetCategory] ?? [], 'name');
        if (! $isDraft && ! in_array($validated['subcategory'], $validSubcategories, true)) {
            return back()->withErrors(['subcategory' => 'The selected subcategory does not belong to ' . $targetCategory . '.'])->withInput();
        }

        $picturePaths = [];
        if ($request->hasFile('pictures')) {
            foreach ($request->file('pictures') as $file) {
                $picturePaths[] = $this->optimizeAndStoreImage($file, 'products');
            }
        }

        $priceDependency = ! empty($validated['sub_variant_title']) ? 'sub' : 'main';
        $variantsData = [
            'title'            => $validated['variant_title'] ?? null,
            'sub_title'        => $validated['sub_variant_title'] ?? null,
            'price_dependency' => $priceDependency,
            'items'            => [],
        ];

        $minPrice = null;
        $baseWeight = null;
        $totalStock = 0;
        $uploadedVariantFiles = $request->file('variant_pictures') ?? [];

        if (! empty($validated['variant_names'])) {
            $seenVariants = [];

            foreach ($validated['variant_names'] as $vId => $vName) {
                $normalizedVName = strtolower(trim($vName));
                if (in_array($normalizedVName, $seenVariants, true)) {
                    return back()->withErrors(['variant_names' => "Duplicate variant name \"{$vName}\" is not allowed."])->withInput();
                }
                $seenVariants[] = $normalizedVName;

                $item = [
                    'id'          => uniqid(),
                    'name'        => trim($vName),
                    'status'      => 'Pending',
                    'is_archived' => false,
                    'image'       => null,
                    'price'       => null,
                    'weight'      => null,
                    'stock'       => null,
                    'subs'        => [],
                ];

                if (isset($uploadedVariantFiles[$vId])) {
                    $item['image'] = $this->optimizeAndStoreImage($uploadedVariantFiles[$vId], 'variants');
                } elseif ($request->hasFile("variant_pictures.{$vId}")) {
                    $item['image'] = $this->optimizeAndStoreImage($request->file("variant_pictures.{$vId}"), 'variants');
                }

                if ($priceDependency === 'main') {
                    $item['price']  = $this->sanitizeNumericPrice($validated['variant_prices'][$vId] ?? 0, "Variant \"{$vName}\" price");
                    $item['weight'] = $this->sanitizeNumericWeight($validated['variant_weights'][$vId] ?? 0, "Variant \"{$vName}\" weight");
                    $item['stock']  = $this->sanitizeIntegerStock($validated['variant_stocks'][$vId] ?? 0, "Variant \"{$vName}\" stock");

                    $totalStock = min(self::MAX_SAFE_STOCK, $totalStock + $item['stock']);
                    if (is_null($minPrice) || $item['price'] < $minPrice) {
                        $minPrice = $item['price'];
                    }
                    if (is_null($baseWeight)) {
                        $baseWeight = $item['weight'];
                    }
                }

                if (! empty($validated['sub_variant_names'][$vId])) {
                    $seenSubs = [];
                    foreach ($validated['sub_variant_names'][$vId] as $sId => $sName) {
                        $normalizedSName = strtolower(trim($sName));
                        if (in_array($normalizedSName, $seenSubs, true)) {
                            return back()->withErrors(['sub_variant_names' => "Duplicate subvariant name \"{$sName}\" under \"{$vName}\" is not allowed."])->withInput();
                        }
                        $seenSubs[] = $normalizedSName;

                        $sub = [
                            'id'          => uniqid(),
                            'name'        => trim($sName),
                            'status'      => 'Pending',
                            'is_archived' => false,
                            'price'       => null,
                            'weight'      => null,
                            'stock'       => null,
                        ];

                        if ($priceDependency === 'sub') {
                            $sub['price']  = $this->sanitizeNumericPrice($validated['sub_variant_prices'][$vId][$sId] ?? 0, "Subvariant \"{$sName}\" price");
                            $sub['weight'] = $this->sanitizeNumericWeight($validated['sub_variant_weights'][$vId][$sId] ?? 0, "Subvariant \"{$sName}\" weight");
                            $sub['stock']  = $this->sanitizeIntegerStock($validated['sub_variant_stocks'][$vId][$sId] ?? 0, "Subvariant \"{$sName}\" stock");

                            $totalStock = min(self::MAX_SAFE_STOCK, $totalStock + $sub['stock']);
                            if (is_null($minPrice) || $sub['price'] < $minPrice) {
                                $minPrice = $sub['price'];
                            }
                            if (is_null($baseWeight)) {
                                $baseWeight = $sub['weight'];
                            }
                        }

                        $item['subs'][] = $sub;
                    }
                }

                $variantsData['items'][] = $item;
            }
        }

        $safeBasePrice = $this->sanitizeNumericPrice($minPrice ?? 0, 'Product price');
        $safeBaseWeight = (string) $this->sanitizeNumericWeight($baseWeight ?? 0, 'Product weight');
        $safeDiscount = min(99.0, max(0.0, (float) ($validated['discount'] ?? 0)));

        $productPayload = [
            'user_id'                 => auth()->id(),
            'name'                    => $validated['name'],
            'category'                => $targetCategory,
            'subcategory'             => $validated['subcategory'] ?? 'Uncategorized',
            'price'                   => $safeBasePrice,
            'weight'                  => $safeBaseWeight,
            'discount'                => $safeDiscount,
            'stock_quantity'          => min(self::MAX_SAFE_STOCK, $totalStock),
            'description'             => $validated['description'] ?? null,
            'additional_descriptions' => $validated['additional_descriptions'] ?? null,
            'pictures'                => $picturePaths,
            'variants'                => count($variantsData['items']) > 0 ? $variantsData : null,
            'has_pending_variants'    => false,
        ];

        if (Schema::hasColumn('products', 'publish_status')) {
            $productPayload['publish_status'] = $isDraft ? 'draft' : 'active';
            $productPayload['per_buyer_limit'] = $request->filled('per_buyer_limit')
                ? max(1, (int) $request->input('per_buyer_limit'))
                : null;
        }

        $product = Product::create($productPayload);

        $specifications = $request->input('specifications');
        if (is_array($specifications)) {
            $validSpecs = array_filter($specifications, function ($spec) {
                return ! empty($spec['title']) && ! empty($spec['description']);
            });
            if (count($validSpecs) > 0) {
                $product->specifications()->createMany($validSpecs);
            }
        }

        ProductApproval::create([
            'product_id' => $product->id,
            'user_id'    => auth()->id(),
            'status'     => $isDraft ? 'Draft' : 'Pending',
        ]);

        return redirect()
            ->route('seller.products.index', ['category' => $targetCategory])
            ->with('success', $isDraft ? 'Product saved as Draft.' : 'Product submitted and pending review.');
    }

    public function duplicate(Product $product)
    {
        if ($product->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $newProduct = DB::transaction(function () use ($product) {
            $copyData = [
                'user_id'                 => auth()->id(),
                'name'                    => substr($product->name . ' (Copy)', 0, 120),
                'category'                => $product->category,
                'subcategory'             => $product->subcategory,
                'price'                   => $product->price,
                'weight'                  => $product->weight,
                'discount'                => $product->discount,
                'stock_quantity'          => $product->stock_quantity,
                'description'             => $product->description,
                'additional_descriptions' => $product->additional_descriptions,
                'pictures'                => $product->pictures,
                'variants'                => $product->raw_variants ?? $product->variants,
                'has_pending_variants'    => false,
            ];

            if (Schema::hasColumn('products', 'publish_status')) {
                $copyData['publish_status'] = 'draft';
                $copyData['per_buyer_limit'] = $product->per_buyer_limit ?? null;
            }

            $duplicated = Product::create($copyData);

            foreach ($product->specifications as $spec) {
                $duplicated->specifications()->create([
                    'title'       => $spec->title,
                    'description' => $spec->description,
                ]);
            }

            ProductApproval::create([
                'product_id' => $duplicated->id,
                'user_id'    => auth()->id(),
                'status'     => 'Draft',
            ]);

            return $duplicated;
        });

        return back()->with('success', "Product duplicated as Draft: \"{$newProduct->name}\".");
    }

    public function togglePublishStatus(Product $product)
    {
        if ($product->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        if (! Schema::hasColumn('products', 'publish_status')) {
            return back()->withErrors(['error' => 'Publish status column is not available.']);
        }

        $current = $product->publish_status ?? 'active';
        if ($current === 'draft') {
            if (empty($product->name) || empty($product->pictures) || (float) $product->price <= 0) {
                return back()->withErrors([
                    'publish' => 'Cannot publish a draft with missing name, price, or product images.',
                ]);
            }
            $product->update(['publish_status' => 'active']);
            $product->approval?->update(['status' => 'Pending']);

            return back()->with('success', "Draft \"{$product->name}\" submitted for publication.");
        }

        $nextStatus = $current === 'active' ? 'inactive' : 'active';
        $product->update(['publish_status' => $nextStatus]);

        return back()->with('success', "Product \"{$product->name}\" is now " . ucfirst($nextStatus) . '.');
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action'        => ['required', 'in:deactivate,activate,delete'],
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer'],
        ]);

        $products = Product::where('user_id', auth()->id())
            ->whereIn('id', $validated['product_ids'])
            ->get();

        $processed = 0;
        $skippedNames = [];

        foreach ($products as $prod) {
            if ($validated['action'] === 'deactivate') {
                if (Schema::hasColumn('products', 'publish_status')) {
                    $prod->update(['publish_status' => 'inactive']);
                }
                $processed++;
            } elseif ($validated['action'] === 'activate') {
                if (Schema::hasColumn('products', 'publish_status')) {
                    $prod->update(['publish_status' => 'active']);
                }
                $processed++;
            } elseif ($validated['action'] === 'delete') {
                if ($this->hasUnfinishedOrders((int) $prod->id)) {
                    $skippedNames[] = $prod->name;
                    continue;
                }
                $prod->delete();
                $processed++;
            }
        }

        $msg = "Bulk {$validated['action']} completed for {$processed} product(s).";
        if (! empty($skippedNames)) {
            $msg .= ' Skipped ' . implode(', ', $skippedNames) . ' because they have unfinished orders.';
        }

        return back()->with('success', $msg);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        if ($product->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $currentApprovalStatus = $product->approval->status ?? 'Pending';
        if ($currentApprovalStatus === 'Pending') {
            return back()->withErrors(['error' => 'You cannot update a product while it is pending administrative review.'])->withInput();
        }

        $validated = $request->validated();
        $isApprovedProduct = $currentApprovalStatus === 'Approved';

        $dataToUpdate = [
            'discount'                => min(99.0, max(0.0, (float) ($validated['discount'] ?? 0))),
            'description'             => $validated['description'] ?? null,
            'additional_descriptions' => $validated['additional_descriptions'] ?? null,
        ];

        if (Schema::hasColumn('products', 'per_buyer_limit') && $request->has('per_buyer_limit')) {
            $dataToUpdate['per_buyer_limit'] = $request->filled('per_buyer_limit')
                ? max(1, (int) $request->input('per_buyer_limit'))
                : null;
        }

        if (! $isApprovedProduct) {
            $categoriesList = self::getCategoriesList();
            $primaryCategory = auth()->user()->sellerProfile->line_of_business ?? 'Pet';
            $productCategory = $product->category ?? $primaryCategory;
            $validSubcategories = array_column($categoriesList[$productCategory] ?? [], 'name');

            if (! empty($validSubcategories) && ! in_array($validated['subcategory'], $validSubcategories, true)) {
                return back()->withErrors(['subcategory' => 'The selected subcategory does not belong to ' . $productCategory . '.'])->withInput();
            }

            $dataToUpdate['name'] = $validated['name'];
            $dataToUpdate['subcategory'] = $validated['subcategory'];

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
        }

        $rawVariants = $product->raw_variants ?? $product->variants;
        if ($rawVariants && isset($rawVariants['items']) && is_array($rawVariants['items'])) {
            $variantsData = $rawVariants;
            $priceDependency = $variantsData['price_dependency'] ?? 'main';

            if ($request->has('variant_names')) {
                foreach ($request->variant_names as $vIndex => $vName) {
                    if (! isset($variantsData['items'][$vIndex])) {
                        continue;
                    }

                    $isArchivedVariant = isset($request->archive_variants[$vIndex]) && (int) $request->archive_variants[$vIndex] === 1;
                    if ($isArchivedVariant) {
                        $existingVarLabel = $variantsData['items'][$vIndex]['name'] ?? '';
                        if ($this->hasUnfinishedOrders((int) $product->id, $existingVarLabel)) {
                            return back()->withErrors([
                                'variant_names' => "Cannot remove or archive variant \"{$existingVarLabel}\" because it has unfinished orders.",
                            ])->withInput();
                        }
                        $variantsData['items'][$vIndex]['is_archived'] = true;
                        continue;
                    }

                    $existingVStatus = $variantsData['items'][$vIndex]['status'] ?? 'Approved';
                    $variantsData['items'][$vIndex]['name'] = trim((string) $vName);

                    if (! $isApprovedProduct || $existingVStatus === 'Disapproved') {
                        $variantsData['items'][$vIndex]['status'] = 'Pending';
                    }

                    if ($request->hasFile("variant_pictures.{$vIndex}")) {
                        if (! empty($variantsData['items'][$vIndex]['image'])) {
                            Storage::disk('public')->delete($variantsData['items'][$vIndex]['image']);
                        }
                        $variantsData['items'][$vIndex]['image'] = $this->optimizeAndStoreImage($request->file("variant_pictures.{$vIndex}"), 'variants');
                    }

                    if ($priceDependency === 'main') {
                        $variantsData['items'][$vIndex]['price']  = $this->sanitizeNumericPrice($request->variant_prices[$vIndex] ?? $variantsData['items'][$vIndex]['price'], "Variant \"{$vName}\" price");
                        $variantsData['items'][$vIndex]['weight'] = $this->sanitizeNumericWeight($request->variant_weights[$vIndex] ?? $variantsData['items'][$vIndex]['weight'], "Variant \"{$vName}\" weight");
                        $variantsData['items'][$vIndex]['stock']  = $this->sanitizeIntegerStock($request->variant_stocks[$vIndex] ?? $variantsData['items'][$vIndex]['stock'], "Variant \"{$vName}\" stock");
                    }

                    if (isset($variantsData['items'][$vIndex]['subs']) && is_array($variantsData['items'][$vIndex]['subs'])) {
                        foreach ($variantsData['items'][$vIndex]['subs'] as $sIndex => $sub) {
                            $isArchivedSub = isset($request->archive_sub_variants[$vIndex][$sIndex]) && (int) $request->archive_sub_variants[$vIndex][$sIndex] === 1;
                            if ($isArchivedSub) {
                                $existingSubLabel = $sub['name'] ?? '';
                                if ($this->hasUnfinishedOrders((int) $product->id, $existingSubLabel)) {
                                    return back()->withErrors([
                                        'sub_variant_names' => "Cannot remove or archive sub-variant \"{$existingSubLabel}\" because it has unfinished orders.",
                                    ])->withInput();
                                }
                                $variantsData['items'][$vIndex]['subs'][$sIndex]['is_archived'] = true;
                                continue;
                            }

                            if (isset($request->sub_variant_names[$vIndex][$sIndex])) {
                                $existingSubStatus = $variantsData['items'][$vIndex]['subs'][$sIndex]['status'] ?? 'Approved';
                                $sLabel = trim((string) $request->sub_variant_names[$vIndex][$sIndex]);
                                $variantsData['items'][$vIndex]['subs'][$sIndex]['name'] = $sLabel;

                                if (! $isApprovedProduct || $existingSubStatus === 'Disapproved') {
                                    $variantsData['items'][$vIndex]['subs'][$sIndex]['status'] = 'Pending';
                                }

                                if ($priceDependency === 'sub') {
                                    $variantsData['items'][$vIndex]['subs'][$sIndex]['price']  = $this->sanitizeNumericPrice($request->sub_variant_prices[$vIndex][$sIndex] ?? $sub['price'], "Subvariant \"{$sLabel}\" price");
                                    $variantsData['items'][$vIndex]['subs'][$sIndex]['weight'] = $this->sanitizeNumericWeight($request->sub_variant_weights[$vIndex][$sIndex] ?? $sub['weight'], "Subvariant \"{$sLabel}\" weight");
                                    $variantsData['items'][$vIndex]['subs'][$sIndex]['stock']  = $this->sanitizeIntegerStock($request->sub_variant_stocks[$vIndex][$sIndex] ?? $sub['stock'], "Subvariant \"{$sLabel}\" stock");
                                }
                            }
                        }
                    }

                    if (isset($request->new_existing_sub_variant_names[$vIndex]) && is_array($request->new_existing_sub_variant_names[$vIndex])) {
                        if (! isset($variantsData['items'][$vIndex]['subs']) || ! is_array($variantsData['items'][$vIndex]['subs'])) {
                            $variantsData['items'][$vIndex]['subs'] = [];
                        }

                        foreach ($request->new_existing_sub_variant_names[$vIndex] as $nsIndex => $nsName) {
                            $trimmedNsName = trim((string) $nsName);
                            if ($trimmedNsName === '') {
                                continue;
                            }

                            $variantsData['items'][$vIndex]['subs'][] = [
                                'id'          => uniqid(),
                                'name'        => $trimmedNsName,
                                'status'      => 'Pending',
                                'is_archived' => false,
                                'price'       => $this->sanitizeNumericPrice($request->new_existing_sub_variant_prices[$vIndex][$nsIndex] ?? 0, "Subvariant \"{$trimmedNsName}\" price"),
                                'weight'      => $this->sanitizeNumericWeight($request->new_existing_sub_variant_weights[$vIndex][$nsIndex] ?? 0, "Subvariant \"{$trimmedNsName}\" weight"),
                                'stock'       => $this->sanitizeIntegerStock($request->new_existing_sub_variant_stocks[$vIndex][$nsIndex] ?? 0, "Subvariant \"{$trimmedNsName}\" stock"),
                            ];
                        }
                    }
                }
            }

            if ($request->has('new_variant_names') && is_array($request->new_variant_names)) {
                foreach ($request->new_variant_names as $nIndex => $nName) {
                    $trimmedNName = trim((string) $nName);
                    if ($trimmedNName === '') {
                        continue;
                    }

                    $item = [
                        'id'          => uniqid(),
                        'name'        => $trimmedNName,
                        'status'      => 'Pending',
                        'is_archived' => false,
                        'price'       => $priceDependency === 'main' ? $this->sanitizeNumericPrice($request->new_variant_prices[$nIndex] ?? 0, "Variant \"{$trimmedNName}\" price") : null,
                        'weight'      => $priceDependency === 'main' ? $this->sanitizeNumericWeight($request->new_variant_weights[$nIndex] ?? 0, "Variant \"{$trimmedNName}\" weight") : null,
                        'stock'       => $priceDependency === 'main' ? $this->sanitizeIntegerStock($request->new_variant_stocks[$nIndex] ?? 0, "Variant \"{$trimmedNName}\" stock") : null,
                        'image'       => null,
                        'subs'        => [],
                    ];

                    if ($request->hasFile("new_variant_pictures.{$nIndex}")) {
                        $item['image'] = $this->optimizeAndStoreImage($request->file("new_variant_pictures.{$nIndex}"), 'variants');
                    }

                    if (isset($request->new_sub_variant_names[$nIndex]) && is_array($request->new_sub_variant_names[$nIndex])) {
                        foreach ($request->new_sub_variant_names[$nIndex] as $nsIndex => $nsName) {
                            $trimmedSubName = trim((string) $nsName);
                            if ($trimmedSubName === '') {
                                continue;
                            }

                            $item['subs'][] = [
                                'id'          => uniqid(),
                                'name'        => $trimmedSubName,
                                'status'      => 'Pending',
                                'is_archived' => false,
                                'price'       => $this->sanitizeNumericPrice($request->new_sub_variant_prices[$nIndex][$nsIndex] ?? 0, "Subvariant \"{$trimmedSubName}\" price"),
                                'weight'      => $this->sanitizeNumericWeight($request->new_sub_variant_weights[$nIndex][$nsIndex] ?? 0, "Subvariant \"{$trimmedSubName}\" weight"),
                                'stock'       => $this->sanitizeIntegerStock($request->new_sub_variant_stocks[$nIndex][$nsIndex] ?? 0, "Subvariant \"{$trimmedSubName}\" stock"),
                            ];
                        }
                    }

                    $variantsData['items'][] = $item;
                }
            }

            $seenVariantNames = [];
            foreach ($variantsData['items'] as $vItem) {
                if (! empty($vItem['is_archived']) || ($vItem['status'] ?? '') === 'Disapproved') {
                    continue;
                }

                $normVName = strtolower(trim($vItem['name'] ?? ''));
                if ($normVName !== '') {
                    if (in_array($normVName, $seenVariantNames, true)) {
                        return back()->withErrors(['variant_names' => "Variant name \"{$vItem['name']}\" must be unique."])->withInput();
                    }
                    $seenVariantNames[] = $normVName;
                }

                if (! empty($vItem['subs']) && is_array($vItem['subs'])) {
                    $seenSubNames = [];
                    foreach ($vItem['subs'] as $sItem) {
                        if (! empty($sItem['is_archived']) || ($sItem['status'] ?? '') === 'Disapproved') {
                            continue;
                        }
                        $normSName = strtolower(trim($sItem['name'] ?? ''));
                        if ($normSName !== '') {
                            if (in_array($normSName, $seenSubNames, true)) {
                                return back()->withErrors(['sub_variant_names' => "Sub-variant name \"{$sItem['name']}\" under \"{$vItem['name']}\" must be unique."])->withInput();
                            }
                            $seenSubNames[] = $normSName;
                        }
                    }
                }
            }

            $totalStock = 0;
            $minPrice = null;
            $baseWeight = null;
            $hasAnyPendingAdditions = false;

            foreach ($variantsData['items'] as $item) {
                if (! empty($item['is_archived'])) {
                    continue;
                }

                $itemStatus = $item['status'] ?? 'Approved';
                if ($itemStatus === 'Pending') {
                    $hasAnyPendingAdditions = true;
                }

                if ($itemStatus === 'Disapproved') {
                    continue;
                }

                $countItemInTotals = ! $isApprovedProduct || $itemStatus === 'Approved';

                if ($priceDependency === 'main' && $countItemInTotals) {
                    $totalStock = min(self::MAX_SAFE_STOCK, $totalStock + (int) ($item['stock'] ?? 0));
                    if (is_null($minPrice) || (float) $item['price'] < $minPrice) {
                        $minPrice = (float) $item['price'];
                    }
                    if (is_null($baseWeight)) {
                        $baseWeight = $item['weight'];
                    }
                }

                if (isset($item['subs']) && count($item['subs']) > 0) {
                    foreach ($item['subs'] as $sub) {
                        if (! empty($sub['is_archived'])) {
                            continue;
                        }

                        $subStatus = $sub['status'] ?? 'Approved';
                        if ($subStatus === 'Pending') {
                            $hasAnyPendingAdditions = true;
                        }

                        if ($subStatus === 'Disapproved') {
                            continue;
                        }

                        $countSubInTotals = ! $isApprovedProduct || ($itemStatus === 'Approved' && $subStatus === 'Approved');

                        if ($priceDependency === 'sub' && $countSubInTotals) {
                            $totalStock = min(self::MAX_SAFE_STOCK, $totalStock + (int) ($sub['stock'] ?? 0));
                            if (is_null($minPrice) || (float) $sub['price'] < $minPrice) {
                                $minPrice = (float) $sub['price'];
                            }
                            if (is_null($baseWeight)) {
                                $baseWeight = $sub['weight'];
                            }
                        }
                    }
                }
            }

            $dataToUpdate['variants'] = $variantsData;
            $dataToUpdate['price'] = $this->sanitizeNumericPrice($minPrice ?? $product->price, 'Product price');
            $dataToUpdate['weight'] = (string) $this->sanitizeNumericWeight($baseWeight ?? $product->weight, 'Product weight');
            $dataToUpdate['stock_quantity'] = min(self::MAX_SAFE_STOCK, $totalStock);
            $dataToUpdate['has_pending_variants'] = $isApprovedProduct ? $hasAnyPendingAdditions : false;

            if (Schema::hasColumn('products', 'low_stock_alerted') && $totalStock > 5) {
                $dataToUpdate['low_stock_alerted'] = false;
            }
        }

        $product->update($dataToUpdate);

        $product->specifications()->delete();
        $specifications = $request->input('specifications');
        if (is_array($specifications)) {
            $validSpecs = array_filter($specifications, function ($spec) {
                return ! empty($spec['title']) && ! empty($spec['description']);
            });
            if (count($validSpecs) > 0) {
                $product->specifications()->createMany($validSpecs);
            }
        }

        if ($isApprovedProduct && ! empty($dataToUpdate['has_pending_variants'])) {
            $product->approval?->touch();
            return back()->with('success', 'Changes saved. New variants/subvariants have been submitted for admin review.');
        }

        if (! $isApprovedProduct && $product->approval) {
            $product->approval->update([
                'status'           => 'Pending',
                'disapproval_type' => null,
                'remarks'          => null,
            ]);
        }

        return back()->with('success', 'Product updated successfully.');
    }

    public function archive(Product $product)
    {
        if ($product->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        if ($this->hasUnfinishedOrders((int) $product->id)) {
            return back()->withErrors([
                'product' => "Cannot delete or archive \"{$product->name}\" because it currently has unfinished orders.",
            ]);
        }

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

        if ($this->hasUnfinishedOrders((int) $product->id)) {
            return back()->withErrors([
                'product' => "Cannot permanently delete \"{$product->name}\" because it has unfinished orders.",
            ]);
        }

        if ($product->pictures && is_array($product->pictures)) {
            foreach ($product->pictures as $pic) {
                Storage::disk('public')->delete($pic);
            }
        }
        $rawVariants = $product->raw_variants ?? $product->variants;
        if ($rawVariants && isset($rawVariants['items']) && is_array($rawVariants['items'])) {
            foreach ($rawVariants['items'] as $variant) {
                if (isset($variant['image']) && $variant['image']) {
                    Storage::disk('public')->delete($variant['image']);
                }
            }
        }
        $product->forceDelete();
        return back()->with('success', 'Product permanently deleted.');
    }
}