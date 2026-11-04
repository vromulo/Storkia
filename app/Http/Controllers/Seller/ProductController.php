<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductRequest;
use App\Http\Requests\Seller\UpdateProductRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductApproval;
use App\Models\ProductSpecification;
use App\Models\SellerCategory;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProductController extends Controller
{
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
            "Women's" => [
                ['name' => 'Dresses & Skirts'],
                ['name' => 'Tops & Blouses'],
                ['name' => 'Activewear & Yoga Pants'],
                ['name' => 'Lingerie & Sleepwear'],
                ['name' => 'Jackets & Coats'],
                ['name' => 'Shoes & Accessories'],
            ],
            "Men's" => [
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
                ['name' => "Men's Grooming"],
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
                ['name' => 'Rings & Engagement'],
                ['name' => 'Earrings & Studs'],
                ['name' => "Men's Watches"],
                ['name' => "Women's Watches"],
                ['name' => 'Fine Jewelry Sets'],
            ],
        ];
    }

    protected function encodeAndStoreWebp($uploadedFile, string $folder = 'products'): string
    {
        $filename = trim($folder, '/') . '/prod_' . uniqid('', true) . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($uploadedFile->getRealPath())
                ->scaleDown(1600, 1600)
                ->toWebp(84);

            Storage::disk('public')->put($filename, (string) $encoded);

            return $filename;
        } catch (\Throwable $e) {
            return $uploadedFile->store($folder, 'public');
        }
    }

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403, 'Approved Seller Profile required.');
        }

        $categoriesList = self::getCategoriesList();
        $primaryCategory = $profile->line_of_business ?: 'Pet';

        $otherCategories = $profile->otherCategories()
            ->orderBy('created_at', 'asc')
            ->get();

        $usedCategoryNames = collect([$primaryCategory])
            ->merge($otherCategories->pluck('category'))
            ->unique()
            ->values()
            ->all();

        $availableCategoriesToAdd = array_values(array_diff(array_keys($categoriesList), $usedCategoryNames));

        $categoryCounts = Product::where('user_id', $user->id)
            ->select('category', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('category')
            ->pluck('aggregate', 'category')
            ->all();

        $selectedCategory = $request->query('category');
        $activeCategoryRecord = null;
        $products = collect();

        if ($selectedCategory !== null) {
            if (! in_array($selectedCategory, $usedCategoryNames, true)) {
                return redirect()->route('seller.products.index');
            }

            $activeCategoryRecord = $otherCategories->firstWhere('category', $selectedCategory);

            $products = Product::with(['approval', 'specifications'])
                ->where('user_id', $user->id)
                ->where('category', $selectedCategory)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(15)
                ->withQueryString();
        }

        return view('seller.products.index', compact(
            'categoriesList',
            'primaryCategory',
            'otherCategories',
            'availableCategoriesToAdd',
            'categoryCounts',
            'selectedCategory',
            'activeCategoryRecord',
            'products'
        ));
    }

    public function storeCategory(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $categoriesList = self::getCategoriesList();
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:' . implode(',', array_keys($categoriesList))],
        ]);

        $catName = $validated['category'];

        if ($catName === $profile->line_of_business) {
            return back()->withErrors(['category' => 'This is already your primary Line of Business category.']);
        }

        $alreadyAdded = SellerCategory::where('seller_profile_id', $profile->id)
            ->where('category', $catName)
            ->exists();

        if ($alreadyAdded) {
            return back()->withErrors(['category' => 'You have already added this category.']);
        }

        SellerCategory::create([
            'seller_profile_id' => $profile->id,
            'category'          => $catName,
        ]);

        return redirect()
            ->route('seller.products.index', ['category' => $catName])
            ->with('success', "Category \"{$catName}\" added to your catalog!");
    }

    public function destroyCategory(Request $request, SellerCategory $sellerCategory)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile || (int) $sellerCategory->seller_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $catName = $sellerCategory->category;
        $productCount = Product::withTrashed()
            ->where('user_id', $user->id)
            ->where('category', $catName)
            ->count();

        if ($productCount > 0) {
            return back()->withErrors([
                'category' => "Cannot remove \"{$catName}\" while it still contains {$productCount} active or archived product(s).",
            ]);
        }

        $sellerCategory->delete();

        return redirect()
            ->route('seller.products.index')
            ->with('success', "Category \"{$catName}\" removed.");
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $categoriesList = self::getCategoriesList();
        $primaryCategory = $profile->line_of_business ?: 'Pet';
        $selectedCategory = $request->query('category', $primaryCategory);

        if (! array_key_exists($selectedCategory, $categoriesList)) {
            $selectedCategory = $primaryCategory;
        }

        return view('seller.products.create', compact(
            'categoriesList',
            'primaryCategory',
            'selectedCategory'
        ));
    }

    public function store(StoreProductRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $mainPicturePaths = [];
        $uploadedMainPictures = $request->file('pictures', []);

        if (count($uploadedMainPictures) < 1 || count($uploadedMainPictures) > 10) {
            throw ValidationException::withMessages([
                'pictures' => 'Please upload between 1 and 10 main product pictures.',
            ]);
        }

        foreach (array_slice($uploadedMainPictures, 0, 10) as $picFile) {
            $mainPicturePaths[] = $this->encodeAndStoreWebp($picFile, 'products/main');
        }

        $variantTitle = trim((string) ($validated['variant_title'] ?? 'Variants'));
        $subVariantTitle = trim((string) ($validated['sub_variant_title'] ?? ''));
        $hasSubVariants = $subVariantTitle !== '' && ! empty($validated['sub_variant_names']);
        $priceDependency = $hasSubVariants ? 'sub' : 'main';

        $variantNamesInput = $validated['variant_names'] ?? [];
        $variantPricesInput = $validated['variant_prices'] ?? [];
        $variantWeightsInput = $validated['variant_weights'] ?? [];
        $variantStocksInput = $validated['variant_stocks'] ?? [];
        $variantPicturesFiles = $request->file('variant_pictures', []);

        $subNamesInput = $validated['sub_variant_names'] ?? [];
        $subPricesInput = $validated['sub_variant_prices'] ?? [];
        $subWeightsInput = $validated['sub_variant_weights'] ?? [];
        $subStocksInput = $validated['sub_variant_stocks'] ?? [];

        $seenVariantNames = [];
        $variantItems = [];
        $totalStock = 0;
        $lowestPrice = null;
        $defaultWeight = null;

        foreach ($variantNamesInput as $vKey => $rawVName) {
            $vName = trim((string) $rawVName);
            $normVName = mb_strtolower($vName);

            if (in_array($normVName, $seenVariantNames, true)) {
                throw ValidationException::withMessages([
                    "variant_names.{$vKey}" => "Duplicate variant name \"{$vName}\" is not allowed.",
                ]);
            }
            $seenVariantNames[] = $normVName;

            $variantImgPath = null;
            if (isset($variantPicturesFiles[$vKey])) {
                $variantImgPath = $this->encodeAndStoreWebp($variantPicturesFiles[$vKey], 'products/variants');
            }

            if ($priceDependency === 'sub') {
                $subsForV = $subNamesInput[$vKey] ?? [];
                if (empty($subsForV)) {
                    throw ValidationException::withMessages([
                        "sub_variant_names.{$vKey}" => "Variant \"{$vName}\" must have at least one {$subVariantTitle}.",
                    ]);
                }

                $seenSubNames = [];
                $subItems = [];
                $variantMinPrice = null;
                $variantMinWeight = null;
                $variantSumStock = 0;

                foreach ($subsForV as $sKey => $rawSName) {
                    $sName = trim((string) $rawSName);
                    $normSName = mb_strtolower($sName);

                    if (in_array($normSName, $seenSubNames, true)) {
                        throw ValidationException::withMessages([
                            "sub_variant_names.{$vKey}.{$sKey}" => "Duplicate sub-variant \"{$sName}\" under \"{$vName}\".",
                        ]);
                    }
                    $seenSubNames[] = $normSName;

                    $sPrice = round((float) ($subPricesInput[$vKey][$sKey] ?? 20), 2);
                    $sWeight = round((float) ($subWeightsInput[$vKey][$sKey] ?? 0.1), 2);
                    $sStock = max(0, (int) ($subStocksInput[$vKey][$sKey] ?? 0));

                    $variantSumStock += $sStock;
                    $totalStock += $sStock;

                    $variantMinPrice = $variantMinPrice === null ? $sPrice : min($variantMinPrice, $sPrice);
                    $variantMinWeight = $variantMinWeight === null ? $sWeight : min($variantMinWeight, $sWeight);

                    $lowestPrice = $lowestPrice === null ? $sPrice : min($lowestPrice, $sPrice);
                    $defaultWeight = $defaultWeight === null ? $sWeight : min($defaultWeight, $sWeight);

                    $subItems[] = [
                        'name'        => $sName,
                        'price'       => $sPrice,
                        'weight'      => $sWeight,
                        'stock'       => $sStock,
                        'status'      => 'Approved',
                        'is_archived' => false,
                    ];
                }

                $variantItems[] = [
                    'name'        => $vName,
                    'image'       => $variantImgPath,
                    'price'       => $variantMinPrice ?? 20.00,
                    'weight'      => $variantMinWeight ?? 0.10,
                    'stock'       => $variantSumStock,
                    'status'      => 'Approved',
                    'is_archived' => false,
                    'subs'        => $subItems,
                ];
            } else {
                $vPrice = round((float) ($variantPricesInput[$vKey] ?? 20), 2);
                $vWeight = round((float) ($variantWeightsInput[$vKey] ?? 0.1), 2);
                $vStock = max(0, (int) ($variantStocksInput[$vKey] ?? 0));

                $totalStock += $vStock;
                $lowestPrice = $lowestPrice === null ? $vPrice : min($lowestPrice, $vPrice);
                $defaultWeight = $defaultWeight === null ? $vWeight : min($defaultWeight, $vWeight);

                $variantItems[] = [
                    'name'        => $vName,
                    'image'       => $variantImgPath,
                    'price'       => $vPrice,
                    'weight'      => $vWeight,
                    'stock'       => $vStock,
                    'status'      => 'Approved',
                    'is_archived' => false,
                    'subs'        => [],
                ];
            }
        }

        $variantsPayload = [
            'title'            => $variantTitle,
            'sub_title'        => $hasSubVariants ? $subVariantTitle : null,
            'price_dependency' => $priceDependency,
            'items'            => $variantItems,
        ];

        $product = DB::transaction(function () use (
            $user,
            $validated,
            $mainPicturePaths,
            $variantsPayload,
            $lowestPrice,
            $defaultWeight,
            $totalStock
        ) {
            $createAttributes = [
                'user_id'                 => $user->id,
                'name'                    => trim($validated['name']),
                'category'                => $validated['category'],
                'subcategory'             => $validated['subcategory'],
                'price'                   => $lowestPrice ?? 20.00,
                'discount'                => round((float) ($validated['discount'] ?? 0), 2),
                'weight'                  => $defaultWeight ?? 0.10,
                'stock_quantity'          => $totalStock,
                'pictures'                => $mainPicturePaths,
                'variants'                => $variantsPayload,
                'description'             => $validated['description'] ?? null,
                'additional_descriptions' => $validated['additional_descriptions'] ?? null,
            ];

            if (Schema::hasColumn('products', 'publish_status')) {
                $createAttributes['publish_status'] = 'active';
                $createAttributes['per_buyer_limit'] = ! empty($validated['per_buyer_limit']) ? (int) $validated['per_buyer_limit'] : null;
            }

            $createdProduct = Product::create($createAttributes);

            ProductApproval::create([
                'product_id' => $createdProduct->id,
                'user_id'    => $user->id,
                'status'     => 'Pending',
            ]);

            if (! empty($validated['specifications']) && is_array($validated['specifications'])) {
                foreach ($validated['specifications'] as $spec) {
                    $sTitle = trim((string) ($spec['title'] ?? ''));
                    $sDesc = trim((string) ($spec['description'] ?? ''));
                    if ($sTitle !== '' && $sDesc !== '') {
                        ProductSpecification::create([
                            'product_id'  => $createdProduct->id,
                            'title'       => $sTitle,
                            'description' => $sDesc,
                        ]);
                    }
                }
            }

            return $createdProduct;
        });

        return redirect()
            ->route('seller.products.index', ['category' => $product->category])
            ->with('success', "Product \"{$product->name}\" created with " . count($mainPicturePaths) . " main picture(s) and submitted for Admin approval!");
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $user = $request->user();
        if ((int) $product->user_id !== (int) $user->id) {
            abort(403);
        }

        $approvalStatus = $product->approval->status ?? 'Pending';
        if ($approvalStatus === 'Pending') {
            return back()->withErrors(['product' => 'Cannot update a product while it is currently Pending Admin review.']);
        }

        $isApprovedProduct = $approvalStatus === 'Approved';
        $validated = $request->validated();

        $currentPictures = is_array($product->pictures) ? $product->pictures : [];
        $updatedPictures = $currentPictures;

        if ($request->has('existing_pictures') || $request->hasFile('pictures')) {
            $retained = $request->has('existing_pictures')
                ? array_values(array_intersect((array) $request->input('existing_pictures', []), $currentPictures))
                : ($isApprovedProduct ? $currentPictures : []);

            $newUploads = $request->file('pictures', []);
            foreach ($newUploads as $newPic) {
                if (count($retained) >= 10) {
                    break;
                }
                $retained[] = $this->encodeAndStoreWebp($newPic, 'products/main');
            }

            if (empty($retained)) {
                throw ValidationException::withMessages([
                    'pictures' => 'A product must have at least 1 main picture (maximum 10).',
                ]);
            }

            $updatedPictures = array_slice($retained, 0, 10);
        }

        $rawVariants = $product->raw_variants ?? $product->variants ?? [];
        $priceDependency = $rawVariants['price_dependency'] ?? 'main';
        $variantTitle = $rawVariants['title'] ?? 'Variants';
        $subVariantTitle = $rawVariants['sub_title'] ?? null;
        $existingItems = isset($rawVariants['items']) && is_array($rawVariants['items']) ? $rawVariants['items'] : [];

        $variantNamesInput = $request->input('variant_names', []);
        $variantPricesInput = $request->input('variant_prices', []);
        $variantWeightsInput = $request->input('variant_weights', []);
        $variantStocksInput = $request->input('variant_stocks', []);
        $variantPicsFiles = $request->file('variant_pictures', []);
        $archiveVariantsInput = $request->input('archive_variants', []);

        $subNamesInput = $request->input('sub_variant_names', []);
        $subPricesInput = $request->input('sub_variant_prices', []);
        $subWeightsInput = $request->input('sub_variant_weights', []);
        $subStocksInput = $request->input('sub_variant_stocks', []);
        $archiveSubsInput = $request->input('archive_sub_variants', []);

        $newExistingSubNames = $request->input('new_existing_sub_variant_names', []);
        $newExistingSubPrices = $request->input('new_existing_sub_variant_prices', []);
        $newExistingSubWeights = $request->input('new_existing_sub_variant_weights', []);
        $newExistingSubStocks = $request->input('new_existing_sub_variant_stocks', []);

        $updatedItems = [];
        $totalActiveStock = 0;
        $lowestActivePrice = null;
        $defaultWeight = null;
        $hasPendingAdditions = false;

        foreach ($existingItems as $vIdx => $item) {
            $isArchived = ! empty($item['is_archived']) || ! empty($archiveVariantsInput[$vIdx]);
            $item['is_archived'] = $isArchived;

            if (! $isArchived && isset($variantNamesInput[$vIdx])) {
                $item['name'] = trim((string) $variantNamesInput[$vIdx]);
            }

            if (! $isArchived && isset($variantPicsFiles[$vIdx])) {
                $item['image'] = $this->encodeAndStoreWebp($variantPicsFiles[$vIdx], 'products/variants');
            }

            $itemStatus = $item['status'] ?? 'Approved';
            if (! $isApprovedProduct && $itemStatus === 'Disapproved') {
                $itemStatus = 'Pending';
                $item['status'] = 'Pending';
            }

            if ($priceDependency === 'sub') {
                $existingSubs = isset($item['subs']) && is_array($item['subs']) ? $item['subs'] : [];
                $updatedSubs = [];
                $vMinPrice = null;
                $vMinWeight = null;
                $vStockSum = 0;

                foreach ($existingSubs as $sIdx => $sub) {
                    $subArchived = ! empty($sub['is_archived']) || ! empty($archiveSubsInput[$vIdx][$sIdx]);
                    $sub['is_archived'] = $subArchived;

                    if (! $subArchived) {
                        if (isset($subNamesInput[$vIdx][$sIdx])) {
                            $sub['name'] = trim((string) $subNamesInput[$vIdx][$sIdx]);
                        }
                        if (isset($subPricesInput[$vIdx][$sIdx])) {
                            $sub['price'] = round((float) $subPricesInput[$vIdx][$sIdx], 2);
                        }
                        if (isset($subWeightsInput[$vIdx][$sIdx])) {
                            $sub['weight'] = round((float) $subWeightsInput[$vIdx][$sIdx], 2);
                        }
                        if (isset($subStocksInput[$vIdx][$sIdx])) {
                            $sub['stock'] = max(0, (int) $subStocksInput[$vIdx][$sIdx]);
                        }

                        $subStatus = $sub['status'] ?? 'Approved';
                        if ($subStatus === 'Pending') {
                            $hasPendingAdditions = true;
                        }

                        if (! $isArchived && ($subStatus === 'Approved' || ! $isApprovedProduct)) {
                            $sPrice = (float) ($sub['price'] ?? 20);
                            $sWeight = (float) ($sub['weight'] ?? 0.1);
                            $sStock = (int) ($sub['stock'] ?? 0);

                            $vStockSum += $sStock;
                            $totalActiveStock += $sStock;
                            $vMinPrice = $vMinPrice === null ? $sPrice : min($vMinPrice, $sPrice);
                            $vMinWeight = $vMinWeight === null ? $sWeight : min($vMinWeight, $sWeight);
                            $lowestActivePrice = $lowestActivePrice === null ? $sPrice : min($lowestActivePrice, $sPrice);
                            $defaultWeight = $defaultWeight === null ? $sWeight : min($defaultWeight, $sWeight);
                        }
                    }

                    $updatedSubs[] = $sub;
                }

                if (! $isArchived && ! empty($newExistingSubNames[$vIdx]) && is_array($newExistingSubNames[$vIdx])) {
                    foreach ($newExistingSubNames[$vIdx] as $nsKey => $rawNsName) {
                        $nsName = trim((string) $rawNsName);
                        if ($nsName === '') {
                            continue;
                        }

                        $nsPrice = round((float) ($newExistingSubPrices[$vIdx][$nsKey] ?? 20), 2);
                        $nsWeight = round((float) ($newExistingSubWeights[$vIdx][$nsKey] ?? 0.1), 2);
                        $nsStock = max(0, (int) ($newExistingSubStocks[$vIdx][$nsKey] ?? 0));
                        $nsStatus = $isApprovedProduct ? 'Pending' : 'Approved';

                        if ($nsStatus === 'Pending') {
                            $hasPendingAdditions = true;
                        } else {
                            $vStockSum += $nsStock;
                            $totalActiveStock += $nsStock;
                            $vMinPrice = $vMinPrice === null ? $nsPrice : min($vMinPrice, $nsPrice);
                            $lowestActivePrice = $lowestActivePrice === null ? $nsPrice : min($lowestActivePrice, $nsPrice);
                        }

                        $updatedSubs[] = [
                            'name'        => $nsName,
                            'price'       => $nsPrice,
                            'weight'      => $nsWeight,
                            'stock'       => $nsStock,
                            'status'      => $nsStatus,
                            'is_archived' => false,
                        ];
                    }
                }

                $item['subs'] = $updatedSubs;
                if ($vMinPrice !== null) {
                    $item['price'] = $vMinPrice;
                }
                if ($vMinWeight !== null) {
                    $item['weight'] = $vMinWeight;
                }
                $item['stock'] = $vStockSum;
            } else {
                if (! $isArchived) {
                    if (isset($variantPricesInput[$vIdx])) {
                        $item['price'] = round((float) $variantPricesInput[$vIdx], 2);
                    }
                    if (isset($variantWeightsInput[$vIdx])) {
                        $item['weight'] = round((float) $variantWeightsInput[$vIdx], 2);
                    }
                    if (isset($variantStocksInput[$vIdx])) {
                        $item['stock'] = max(0, (int) $variantStocksInput[$vIdx]);
                    }

                    if ($itemStatus === 'Pending') {
                        $hasPendingAdditions = true;
                    }

                    if ($itemStatus === 'Approved' || ! $isApprovedProduct) {
                        $vPrice = (float) ($item['price'] ?? 20);
                        $vWeight = (float) ($item['weight'] ?? 0.1);
                        $vStock = (int) ($item['stock'] ?? 0);

                        $totalActiveStock += $vStock;
                        $lowestActivePrice = $lowestActivePrice === null ? $vPrice : min($lowestActivePrice, $vPrice);
                        $defaultWeight = $defaultWeight === null ? $vWeight : min($defaultWeight, $vWeight);
                    }
                }
            }

            $updatedItems[] = $item;
        }

        $newVariantNames = $request->input('new_variant_names', []);
        $newVariantPrices = $request->input('new_variant_prices', []);
        $newVariantWeights = $request->input('new_variant_weights', []);
        $newVariantStocks = $request->input('new_variant_stocks', []);
        $newVariantPics = $request->file('new_variant_pictures', []);

        $newSubNames = $request->input('new_sub_variant_names', []);
        $newSubPrices = $request->input('new_sub_variant_prices', []);
        $newSubWeights = $request->input('new_sub_variant_weights', []);
        $newSubStocks = $request->input('new_sub_variant_stocks', []);

        foreach ($newVariantNames as $nvId => $rawNvName) {
            $nvName = trim((string) $rawNvName);
            if ($nvName === '') {
                continue;
            }

            $nvImgPath = isset($newVariantPics[$nvId])
                ? $this->encodeAndStoreWebp($newVariantPics[$nvId], 'products/variants')
                : null;

            $nvStatus = $isApprovedProduct ? 'Pending' : 'Approved';
            if ($nvStatus === 'Pending') {
                $hasPendingAdditions = true;
            }

            if ($priceDependency === 'sub') {
                $subsForNv = $newSubNames[$nvId] ?? [];
                $nvSubs = [];
                $nvMinPrice = null;
                $nvMinWeight = null;
                $nvStockSum = 0;

                foreach ($subsForNv as $nsId => $rawNsName) {
                    $nsName = trim((string) $rawNsName);
                    if ($nsName === '') {
                        continue;
                    }

                    $nsPrice = round((float) ($newSubPrices[$nvId][$nsId] ?? 20), 2);
                    $nsWeight = round((float) ($newSubWeights[$nvId][$nsId] ?? 0.1), 2);
                    $nsStock = max(0, (int) ($newSubStocks[$nvId][$nsId] ?? 0));

                    $nvStockSum += $nsStock;
                    $nvMinPrice = $nvMinPrice === null ? $nsPrice : min($nvMinPrice, $nsPrice);
                    $nvMinWeight = $nvMinWeight === null ? $nsWeight : min($nvMinWeight, $nsWeight);

                    if (! $isApprovedProduct) {
                        $totalActiveStock += $nsStock;
                        $lowestActivePrice = $lowestActivePrice === null ? $nsPrice : min($lowestActivePrice, $nsPrice);
                    }

                    $nvSubs[] = [
                        'name'        => $nsName,
                        'price'       => $nsPrice,
                        'weight'      => $nsWeight,
                        'stock'       => $nsStock,
                        'status'      => $nvStatus,
                        'is_archived' => false,
                    ];
                }

                $updatedItems[] = [
                    'name'        => $nvName,
                    'image'       => $nvImgPath,
                    'price'       => $nvMinPrice ?? 20.00,
                    'weight'      => $nvMinWeight ?? 0.10,
                    'stock'       => $nvStockSum,
                    'status'      => $nvStatus,
                    'is_archived' => false,
                    'subs'        => $nvSubs,
                ];
            } else {
                $nvPrice = round((float) ($newVariantPrices[$nvId] ?? 20), 2);
                $nvWeight = round((float) ($newVariantWeights[$nvId] ?? 0.1), 2);
                $nvStock = max(0, (int) ($newVariantStocks[$nvId] ?? 0));

                if (! $isApprovedProduct) {
                    $totalActiveStock += $nvStock;
                    $lowestActivePrice = $lowestActivePrice === null ? $nvPrice : min($lowestActivePrice, $nvPrice);
                }

                $updatedItems[] = [
                    'name'        => $nvName,
                    'image'       => $nvImgPath,
                    'price'       => $nvPrice,
                    'weight'      => $nvWeight,
                    'stock'       => $nvStock,
                    'status'      => $nvStatus,
                    'is_archived' => false,
                    'subs'        => [],
                ];
            }
        }

        $updatedVariantsPayload = [
            'title'            => $variantTitle,
            'sub_title'        => $subVariantTitle,
            'price_dependency' => $priceDependency,
            'items'            => $updatedItems,
        ];

        DB::transaction(function () use (
            $product,
            $validated,
            $updatedPictures,
            $updatedVariantsPayload,
            $lowestActivePrice,
            $defaultWeight,
            $totalActiveStock,
            $isApprovedProduct,
            $approvalStatus,
            $hasPendingAdditions
        ) {
            $updateData = [
                'name'                    => $isApprovedProduct ? $product->name : trim($validated['name']),
                'subcategory'             => $isApprovedProduct ? $product->subcategory : $validated['subcategory'],
                'price'                   => $lowestActivePrice ?? $product->price,
                'discount'                => round((float) ($validated['discount'] ?? 0), 2),
                'weight'                  => $defaultWeight ?? $product->weight,
                'stock_quantity'          => $totalActiveStock,
                'pictures'                => $updatedPictures,
                'variants'                => $updatedVariantsPayload,
                'description'             => $validated['description'] ?? null,
                'additional_descriptions' => $validated['additional_descriptions'] ?? null,
            ];

            if (Schema::hasColumn('products', 'has_pending_variants')) {
                $updateData['has_pending_variants'] = $hasPendingAdditions;
            }

            $product->update($updateData);

            if ($approvalStatus === 'Disapproved' && $product->approval) {
                $product->approval->update([
                    'status'           => 'Pending',
                    'disapproval_type' => null,
                    'remarks'          => null,
                ]);
            }

            $product->specifications()->delete();
            if (! empty($validated['specifications']) && is_array($validated['specifications'])) {
                foreach ($validated['specifications'] as $spec) {
                    $sTitle = trim((string) ($spec['title'] ?? ''));
                    $sDesc = trim((string) ($spec['description'] ?? ''));
                    if ($sTitle !== '' && $sDesc !== '') {
                        ProductSpecification::create([
                            'product_id'  => $product->id,
                            'title'       => $sTitle,
                            'description' => $sDesc,
                        ]);
                    }
                }
            }
        });

        return back()->with(
            'success',
            $hasPendingAdditions
                ? 'Product updated! Newly added variants/subvariants have been queued for Admin approval.'
                : 'Product updated successfully!'
        );
    }

    public function bulkAction(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'action'        => ['required', 'in:activate,deactivate,archive'],
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
        ]);

        $products = Product::where('user_id', $user->id)
            ->whereIn('id', $validated['product_ids'])
            ->get();

        foreach ($products as $prod) {
            if ($validated['action'] === 'archive') {
                $prod->delete();
            } elseif (Schema::hasColumn('products', 'publish_status')) {
                $prod->update([
                    'publish_status' => $validated['action'] === 'activate' ? 'active' : 'inactive',
                ]);
            }
        }

        return back()->with('success', 'Bulk action applied to ' . $products->count() . ' product(s).');
    }

    public function duplicate(Request $request, Product $product)
    {
        $user = $request->user();
        if ((int) $product->user_id !== (int) $user->id) {
            abort(403);
        }

        $copy = $product->replicate();
        $copy->name = substr($product->name . ' (Copy)', 0, 100);
        if (Schema::hasColumn('products', 'publish_status')) {
            $copy->publish_status = 'draft';
        }
        $copy->save();

        ProductApproval::create([
            'product_id' => $copy->id,
            'user_id'    => $user->id,
            'status'     => 'Pending',
        ]);

        return back()->with('success', "Product duplicated as \"{$copy->name}\" (Pending Admin review).");
    }

    public function togglePublishStatus(Request $request, Product $product)
    {
        $user = $request->user();
        if ((int) $product->user_id !== (int) $user->id) {
            abort(403);
        }

        if (! Schema::hasColumn('products', 'publish_status')) {
            return back();
        }

        $next = ($product->publish_status ?? 'active') === 'active' ? 'inactive' : 'active';
        $product->update(['publish_status' => $next]);

        return back()->with('success', "Product status set to " . ucfirst($next) . '.');
    }

    public function archive(Request $request, Product $product)
    {
        $user = $request->user();
        if ((int) $product->user_id !== (int) $user->id) {
            abort(403);
        }

        $hasActiveOrders = OrderItem::where('product_id', $product->id)
            ->whereHas('sellerOrder', function ($q) {
                $q->whereNotIn('status', ['completed', 'cancelled', 'rejected', 'returned_to_seller']);
            })
            ->exists();

        if ($hasActiveOrders) {
            return back()->withErrors([
                'archive' => 'Cannot archive this product while it has active, unfinished customer orders.',
            ]);
        }

        $category = $product->category;
        $product->delete();

        return redirect()
            ->route('seller.products.index', ['category' => $category])
            ->with('success', "Product \"{$product->name}\" moved to Archived Products.");
    }

    public function inventory(Request $request)
    {
        $products = Product::with('approval')
            ->where('user_id', $request->user()->id)
            ->orderBy('stock_quantity', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return view('seller.products.inventory', compact('products'));
    }

    public function archived(Request $request)
    {
        $products = Product::onlyTrashed()
            ->with('approval')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('deleted_at')
            ->get();

        return view('seller.products.archived', compact('products'));
    }

    public function unarchive(Request $request, int $id)
    {
        $product = Product::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $product->restore();

        return back()->with('success', "Product \"{$product->name}\" restored to your active catalog.");
    }

    public function forceDelete(Request $request, int $id)
    {
        $product = Product::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $hasOrderHistory = OrderItem::where('product_id', $product->id)->exists();
        if ($hasOrderHistory) {
            return back()->withErrors([
                'delete' => 'Cannot permanently delete a product that is linked to past customer order records.',
            ]);
        }

        $product->specifications()->delete();
        $product->approval()?->delete();
        $product->forceDelete();

        return back()->with('success', 'Archived product permanently deleted.');
    }
}