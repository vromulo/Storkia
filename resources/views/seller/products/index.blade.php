@extends('layouts.seller', ['title' => 'Storkia - All Products'])

@section('content')
    @php
        $categoriesList = [
            'Pet' => [ ['name' => 'Dog Food & Treats'], ['name' => 'Cat Litter & Accessories'], ['name' => 'Aquariums & Fish Supplies'], ['name' => 'Bird Feeders & Food'], ['name' => 'Pet Grooming Products'], ['name' => 'Pet Health & Wellness'] ],
            'Kids' => [ ['name' => 'Baby Clothes & Accessories'], ['name' => 'Toys & Games'], ['name' => 'Educational Materials'], ['name' => 'Strollers & Gear'], ['name' => 'Nursery Furniture'], ['name' => 'Safety and Health'] ],
            'Electronics' => [ ['name' => 'Mobile Phones & Accessories'], ['name' => 'Laptops, Desktops & Monitors'], ['name' => 'Audio & Video Equipment'], ['name' => 'Smart Home Devices'], ['name' => 'Cameras & Photography'], ['name' => 'Wearable Technology'] ],
            'Home & Garden' => [ ['name' => 'Kitchen Appliances'], ['name' => 'Furniture & Decor'], ['name' => 'Gardening Tools'], ['name' => 'Outdoor Living'], ['name' => 'Home Improvement Tools'], ['name' => 'Bedding & Bath'] ],
            'Women\'s' => [ ['name' => 'Dresses & Skirts'], ['name' => 'Tops & Blouses'], ['name' => 'Activewear & Yoga Pants'], ['name' => 'Lingerie & Sleepwear'], ['name' => 'Jackets & Coats'], ['name' => 'Shoes & Accessories'] ],
            'Men\'s' => [ ['name' => 'Suits & Blazers'], ['name' => 'Casual Shirts & Pants'], ['name' => 'Outerwear & Jackets'], ['name' => 'Activewear & Fitness Gear'], ['name' => 'Shoes & Accessories'], ['name' => 'Grooming Products'] ],
            'Health & Beauty' => [ ['name' => 'Skincare Products'], ['name' => 'Haircare Solutions'], ['name' => 'Makeup & Cosmetics'], ['name' => 'Personal Care Appliances'], ['name' => 'Men\'s Grooming'], ['name' => 'Health Supplements'] ],
            'Books & Media' => [ ['name' => 'Fiction & Non-Fiction Books'], ['name' => 'Magazines & Periodicals'], ['name' => 'Music CDs & Vinyl Records'], ['name' => 'Movie DVDs & Blu-ray'], ['name' => 'Video Games & Consoles'], ['name' => 'Educational DVDs'] ],
            'Sports & Outdoors' => [ ['name' => 'Fitness Equipment'], ['name' => 'Camping & Hiking Gear'], ['name' => 'Sports Apparel'], ['name' => 'Cycling & Bikes'], ['name' => 'Water Sports'], ['name' => 'Team Sports Equipment'] ],
            'Food & Gourmet' => [ ['name' => 'Baking Supplies & Ingredients'], ['name' => 'Coffee, Tea & Beverages'], ['name' => 'Snacks & Candy'], ['name' => 'Specialty Foods'], ['name' => 'Organic and Health Foods'], ['name' => 'Meal Kits & Prepped Foods'] ],
            'Furniture & Office' => [ ['name' => 'Office Desks & Chairs'], ['name' => 'Storage Cabinets & Shelving'], ['name' => 'Conference & Meeting Furniture'], ['name' => 'Computer Tables & Workstations'], ['name' => 'Ergonomic Accessories'], ['name' => 'Office Lighting & Fixtures'] ],
            'Jewelry & Watches' => [ ['name' => 'Necklaces & Pendants'], ['name' => 'Rings & Earrings'], ['name' => 'Bracelets & Bangles'], ['name' => 'Watches for Men & Women'], ['name' => 'Fashion Jewelry'], ['name' => 'Jewelry Storage & Care'] ]
        ];
        $sellerCategory = auth()->user()->sellerProfile->line_of_business ?? 'Pet';
        $subcategories = $categoriesList[$sellerCategory] ?? $categoriesList['Pet'];
    @endphp

    <script>
        window.storkiaProductTableRow = function(data) {
            return {
                basePrice: Number(data.basePrice || 0),
                discountPercent: Number(data.discountPercent || 0),
                totalStockCount: Number(data.totalStock || 0),
                defaultImage: data.defaultImage || '',
                menuOpen: false,
                viewModal: false,
                editModal: false,
                activePrice: Number(data.basePrice || 0),
                activeStock: Number(data.totalStock || 0),
                activeImage: data.defaultImage || '',
                selectedMain: null,
                selectedSub: null,
                get editDiscountedPrice() {
                    return (this.basePrice - (this.basePrice * (this.discountPercent / 100))).toFixed(2);
                }
            };
        };
        window.productTableRow = window.storkiaProductTableRow;

        window.storkiaEditVariantManager = function(config) {
            let rawExisting = (config && config.existingVariants && typeof config.existingVariants === 'object') ? config.existingVariants : {};
            let normalizedExisting = {};
            let initialNewSubs = {};

            Object.keys(rawExisting).forEach(k => {
                let v = rawExisting[k] || {};
                let normalizedSubs = {};
                if (v.subs && typeof v.subs === 'object') {
                    Object.keys(v.subs).forEach(sk => {
                        let s = v.subs[sk] || {};
                        normalizedSubs[String(sk)] = {
                            name: String(s.name ?? ''),
                            price: s.price ?? '',
                            weight: s.weight ?? '',
                            stock: s.stock ?? '',
                            archived: false
                        };
                    });
                }
                normalizedExisting[String(k)] = {
                    name: String(v.name ?? ''),
                    price: v.price ?? '',
                    weight: v.weight ?? '',
                    stock: v.stock ?? '',
                    archived: false,
                    subs: normalizedSubs
                };
                initialNewSubs[String(k)] = [];
            });

            return {
                confirmModal: { open: false, message: '', onConfirm: () => {} },
                existingVariants: normalizedExisting,
                newVariants: [],
                newExistingSubs: initialNewSubs,
                hasSubvariants: Boolean(config && config.hasSubvariants),
                priceDependsOn: (config && config.priceDependency) ? config.priceDependency : 'main',
                uidCounter: 0,

                nextUid() {
                    this.uidCounter++;
                    return Date.now() + '_' + this.uidCounter;
                },
                requestConfirm(msg, callback) {
                    this.confirmModal.message = msg;
                    this.confirmModal.onConfirm = callback;
                    this.confirmModal.open = true;
                },
                isVariantArchived(vKey) {
                    let k = String(vKey);
                    return this.existingVariants[k] ? Boolean(this.existingVariants[k].archived) : false;
                },
                isSubArchived(vKey, sKey) {
                    let k = String(vKey), sk = String(sKey);
                    return (this.existingVariants[k] && this.existingVariants[k].subs && this.existingVariants[k].subs[sk])
                        ? Boolean(this.existingVariants[k].subs[sk].archived)
                        : false;
                },
                archiveExistingVariant(vKey) {
                    let k = String(vKey);
                    if (this.existingVariants[k]) this.existingVariants[k].archived = true;
                    if (this.newExistingSubs[k]) this.newExistingSubs[k] = [];
                },
                archiveExistingSubVariant(vKey, sKey) {
                    let k = String(vKey), sk = String(sKey);
                    if (this.existingVariants[k] && this.existingVariants[k].subs && this.existingVariants[k].subs[sk]) {
                        this.existingVariants[k].subs[sk].archived = true;
                    }
                },
                addVariant() {
                    let subs = this.hasSubvariants
                        ? [{ id: this.nextUid(), name: '', price: '', weight: '', stock: '', isCopied: false }]
                        : undefined;
                    this.newVariants.push({ id: this.nextUid(), name: '', price: '', weight: '', stock: '', subs: subs });
                },
                addSubToNewVariant(nv) {
                    if (!nv.subs) nv.subs = [];
                    nv.subs.push({ id: this.nextUid(), name: '', price: '', weight: '', stock: '', isCopied: false });
                },
                copyFirstSubInNewVariant(nv, subIndex) {
                    if (!nv || !nv.subs || !nv.subs[0] || !nv.subs[subIndex]) return;
                    nv.subs[subIndex].price = nv.subs[0].price;
                    nv.subs[subIndex].weight = nv.subs[0].weight;
                    nv.subs[subIndex].stock = nv.subs[0].stock;
                    nv.subs[subIndex].isCopied = true;
                },
                getNewSubsForVariant(vKey) {
                    return this.newExistingSubs[String(vKey)] || [];
                },
                addNewSubToVariant(vKey) {
                    let k = String(vKey);
                    if (!this.newExistingSubs[k]) this.newExistingSubs[k] = [];
                    this.newExistingSubs[k].push({ id: this.nextUid(), name: '', price: '', weight: '', stock: '', isCopied: false });
                },
                removeNewSubFromVariant(vKey, sId) {
                    let k = String(vKey);
                    if (this.newExistingSubs[k]) {
                        this.newExistingSubs[k] = this.newExistingSubs[k].filter(s => s.id !== sId);
                    }
                },
                getFirstSubTemplateForExisting(vKey) {
                    let k = String(vKey);
                    let v = this.existingVariants[k];
                    if (v && v.subs) {
                        let activeExisting = Object.values(v.subs).find(s => s && !s.archived && (s.price !== '' || s.weight !== '' || s.stock !== ''));
                        if (activeExisting) return activeExisting;
                    }
                    if (this.newExistingSubs[k] && this.newExistingSubs[k].length > 1) {
                        let firstNew = this.newExistingSubs[k][0];
                        if (firstNew && (firstNew.price !== '' || firstNew.weight !== '' || firstNew.stock !== '')) return firstNew;
                    }
                    return null;
                },
                copyFirstSubToExistingNew(vKey, nsIndex) {
                    let k = String(vKey);
                    let tpl = this.getFirstSubTemplateForExisting(k);
                    if (!tpl || !this.newExistingSubs[k] || !this.newExistingSubs[k][nsIndex]) return;
                    this.newExistingSubs[k][nsIndex].price = tpl.price;
                    this.newExistingSubs[k][nsIndex].weight = tpl.weight;
                    this.newExistingSubs[k][nsIndex].stock = tpl.stock;
                    this.newExistingSubs[k][nsIndex].isCopied = true;
                },
                get duplicateVariants() {
                    let names = [];
                    Object.values(this.existingVariants).forEach(v => {
                        if (v && !v.archived) {
                            let n = String(v.name || '').trim().toLowerCase();
                            if (n !== '') names.push(n);
                        }
                    });
                    this.newVariants.forEach(nv => {
                        if (nv) {
                            let n = String(nv.name || '').trim().toLowerCase();
                            if (n !== '') names.push(n);
                        }
                    });
                    return names.filter((item, index) => names.indexOf(item) !== index);
                },
                isVariantDuplicate(name) {
                    let n = String(name || '').trim().toLowerCase();
                    return n !== '' && this.duplicateVariants.includes(n);
                },
                isExistingVariantSubDuplicate(vKey, subName) {
                    let n = String(subName || '').trim().toLowerCase();
                    if (n === '') return false;
                    let k = String(vKey), count = 0, v = this.existingVariants[k];
                    if (v && !v.archived && v.subs) {
                        Object.values(v.subs).forEach(s => {
                            if (s && !s.archived && String(s.name || '').trim().toLowerCase() === n) count++;
                        });
                    }
                    if (this.newExistingSubs[k]) {
                        this.newExistingSubs[k].forEach(ns => {
                            if (ns && String(ns.name || '').trim().toLowerCase() === n) count++;
                        });
                    }
                    return count > 1;
                },
                isNewVariantSubDuplicate(nv, subName) {
                    let n = String(subName || '').trim().toLowerCase();
                    if (n === '' || !nv || !Array.isArray(nv.subs)) return false;
                    let count = 0;
                    nv.subs.forEach(s => {
                        if (s && String(s.name || '').trim().toLowerCase() === n) count++;
                    });
                    return count > 1;
                },
                hasValidationErrors() {
                    if (this.duplicateVariants.length > 0) return true;
                    for (let vKey of Object.keys(this.existingVariants)) {
                        let v = this.existingVariants[vKey];
                        if (!v || v.archived) continue;
                        if (String(v.name || '').trim() === '') return true;
                        if (v.subs) {
                            for (let s of Object.values(v.subs)) {
                                if (!s || s.archived) continue;
                                if (String(s.name || '').trim() === '') return true;
                                if (this.isExistingVariantSubDuplicate(vKey, s.name)) return true;
                            }
                        }
                        let newSubs = this.newExistingSubs[vKey] || [];
                        for (let ns of newSubs) {
                            if (!ns) continue;
                            if (String(ns.name || '').trim() === '') return true;
                            if (this.isExistingVariantSubDuplicate(vKey, ns.name)) return true;
                        }
                    }
                    for (let nv of this.newVariants) {
                        if (!nv) continue;
                        if (String(nv.name || '').trim() === '') return true;
                        if (Array.isArray(nv.subs)) {
                            for (let s of nv.subs) {
                                if (!s) continue;
                                if (String(s.name || '').trim() === '') return true;
                                if (this.isNewVariantSubDuplicate(nv, s.name)) return true;
                            }
                        }
                    }
                    return false;
                }
            };
        };
        window.editVariantManager = window.storkiaEditVariantManager;
    </script>

    @if($errors->any())
        <div x-data="{ showToast: true }" x-show="showToast" x-init="setTimeout(() => showToast = false, 7000)" x-transition x-cloak
             class="fixed top-6 right-6 z-[300] max-w-md w-full bg-white rounded-2xl shadow-2xl border-l-8 border-red-500 p-4 flex items-start gap-3">
            <div class="p-2 bg-red-100 text-red-600 rounded-xl shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div class="flex-1 pr-2">
                <h4 class="font-bold text-gray-900 text-sm">Form Validation Alert</h4>
                <ul class="mt-1 list-disc list-inside text-xs text-red-600 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" @click="showToast = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    @endif

    @if(session('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition x-cloak
             class="fixed top-8 right-8 z-[250] bg-white border border-border-subtle shadow-2xl rounded-2xl p-4 flex items-center min-w-[280px]">
            <div class="flex-shrink-0 bg-green-100 rounded-full p-2 mr-3 border border-green-200">
                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </div>
            <div>
                <h4 class="text-sm font-bold text-gray-900">Success</h4>
                <p class="text-sm text-gray-600">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-primary-dark">All Products</h1>
            <a href="{{ route('seller.products.create') }}" class="px-5 py-2.5 bg-primary text-white rounded-xl shadow-md hover:bg-primary-dark transition-colors font-bold text-sm flex items-center cursor-pointer">
                <svg class="w-5 h-5 mr-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                Add Product
            </a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-border-subtle mb-6">
            <table class="w-full text-left border-collapse relative">
                <thead>
                    <tr class="bg-brand-light/30 text-text-muted text-sm border-b border-border-subtle">
                        <th class="p-4 font-bold w-16 rounded-tl-2xl">Image</th>
                        <th class="p-4 font-bold">Product Name</th>
                        <th class="p-4 font-bold">Subcategory</th>
                        <th class="p-4 font-bold">Price</th>
                        <th class="p-4 font-bold">Total Stock</th>
                        <th class="p-4 font-bold">Approval Status</th>
                        <th class="p-4 font-bold text-right w-24 rounded-tr-2xl">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php
                            $approvalStatus = $product->approval->status ?? 'Pending';
                            $isApprovedProduct = $approvalStatus === 'Approved';
                            $productVariants = $product->raw_variants ?? $product->variants;
                            $priceDependency = $productVariants['price_dependency'] ?? 'main';

                            $totalStock = 0;
                            $pendingVariantCount = 0;
                            $pendingSubVariantCount = 0;
                            $existingVariantsData = [];

                            if (isset($productVariants['items']) && is_array($productVariants['items'])) {
                                foreach ($productVariants['items'] as $idx => $item) {
                                    if (!empty($item['is_archived'])) continue;

                                    $rawItemStatus = $item['status'] ?? 'Approved';
                                    $effectiveItemStatus = !$isApprovedProduct ? $approvalStatus : $rawItemStatus;

                                    if ($isApprovedProduct && $rawItemStatus === 'Pending') {
                                        $pendingVariantCount++;
                                    }

                                    $subData = [];
                                    $hasSubs = isset($item['subs']) && is_array($item['subs']) && count($item['subs']) > 0;

                                    if ($hasSubs) {
                                        foreach ($item['subs'] as $sIdx => $sub) {
                                            if (!empty($sub['is_archived'])) continue;

                                            $rawSubStatus = $sub['status'] ?? 'Approved';
                                            $effectiveSubStatus = !$isApprovedProduct
                                                ? $approvalStatus
                                                : ($effectiveItemStatus === 'Pending' ? 'Pending' : ($effectiveItemStatus === 'Disapproved' ? 'Disapproved' : $rawSubStatus));

                                            if ($isApprovedProduct && $rawItemStatus !== 'Disapproved' && $rawSubStatus === 'Pending') {
                                                $pendingSubVariantCount++;
                                            }

                                            if ($effectiveSubStatus !== 'Disapproved') {
                                                $subData['s_' . $sIdx] = [
                                                    'name'   => trim($sub['name'] ?? ''),
                                                    'price'  => $sub['price'] ?? '',
                                                    'weight' => $sub['weight'] ?? '',
                                                    'stock'  => $sub['stock'] ?? '',
                                                ];
                                            }

                                            $countInActiveStock = !$isApprovedProduct
                                                ? ($rawSubStatus !== 'Disapproved')
                                                : ($effectiveItemStatus === 'Approved' && $effectiveSubStatus === 'Approved');

                                            if ($countInActiveStock) {
                                                $totalStock += (int) ($sub['stock'] ?? 0);
                                            }
                                        }
                                    } else {
                                        $countInActiveStock = !$isApprovedProduct
                                            ? ($rawItemStatus !== 'Disapproved')
                                            : ($effectiveItemStatus === 'Approved');

                                        if ($countInActiveStock) {
                                            $totalStock += (int) ($item['stock'] ?? 0);
                                        }
                                    }

                                    if ($effectiveItemStatus !== 'Disapproved') {
                                        $existingVariantsData['v_' . $idx] = [
                                            'name'   => trim($item['name'] ?? ''),
                                            'price'  => $item['price'] ?? '',
                                            'weight' => $item['weight'] ?? '',
                                            'stock'  => $item['stock'] ?? '',
                                            'subs'   => $subData,
                                        ];
                                    }
                                }
                            } else {
                                $totalStock = (int) ($product->stock_quantity ?? 0);
                            }

                            $firstPic = (!empty($product->pictures) && is_array($product->pictures)) ? asset('storage/' . $product->pictures[0]) : '';
                            $hasPendingAdditions = $isApprovedProduct && ($product->has_pending_variants || $pendingVariantCount > 0 || $pendingSubVariantCount > 0);
                            $hasSubvariantsArchitecture = !empty($productVariants['sub_title']) || ($priceDependency === 'sub') || (isset($productVariants['items'][0]['subs']) && count($productVariants['items'][0]['subs']) > 0);

                            $editManagerConfig = [
                                'existingVariants' => $existingVariantsData,
                                'hasSubvariants'   => $hasSubvariantsArchitecture,
                                'priceDependency'  => $priceDependency,
                            ];
                        @endphp
                        <tr x-data="storkiaProductTableRow({
                                basePrice: {{ $product->price ?? 0 }},
                                discountPercent: {{ $product->discount ?? 0 }},
                                totalStock: {{ $totalStock }},
                                defaultImage: '{{ $firstPic }}'
                            })"
                            @click="viewModal = true"
                            class="border-b border-border-subtle hover:bg-brand-light/10 transition-colors text-sm cursor-pointer relative">
                            
                            <td class="p-4">
                                @if($firstPic)
                                    <img src="{{ $firstPic }}" alt="{{ $product->name }}" class="w-12 h-12 rounded-lg object-cover border border-border-subtle shadow-sm cursor-pointer">
                                @else
                                    <div class="w-12 h-12 rounded-lg bg-gray-100 border border-border-subtle flex items-center justify-center text-gray-400">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                @endif
                            </td>

                            <td class="p-4 font-bold text-primary-dark">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span>{{ $product->name }}</span>
                                    @if($hasPendingAdditions)
                                        <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 border border-yellow-300 text-[10px] font-bold rounded-full">
                                            @if($pendingVariantCount > 0 && $pendingSubVariantCount > 0)
                                                {{ $pendingVariantCount }} Variant(s) & {{ $pendingSubVariantCount }} Subvariant(s) Pending
                                            @elseif($pendingVariantCount > 0)
                                                {{ $pendingVariantCount }} Variant(s) Pending
                                            @else
                                                {{ $pendingSubVariantCount }} Subvariant(s) Pending
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 text-text-muted font-medium">{{ $product->subcategory ?? 'Uncategorized' }}</td>
                            <td class="p-4">
                                @if($product->discount > 0)
                                    <span class="text-xs line-through text-text-muted mr-1">₱{{ number_format($product->price, 2) }}</span>
                                    <span class="font-bold text-red-500">₱{{ number_format($product->price - ($product->price * ($product->discount / 100)), 2) }}</span>
                                @else
                                    <span class="font-bold">₱{{ number_format($product->price, 2) }}</span>
                                @endif
                            </td>
                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold {{ $totalStock > 0 ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' }}">
                                    {{ $totalStock }} in stock
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $approvalStatus === 'Approved' ? 'bg-green-100 text-green-700' : ($approvalStatus === 'Disapproved' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">
                                        {{ $approvalStatus }}
                                    </span>
                                    @if($hasPendingAdditions)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-800 border border-yellow-300">
                                            Additions Under Review
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 text-right">
                                <div class="relative inline-block text-left" @click.stop @click.away="menuOpen = false">
                                    <button type="button" @click.stop="menuOpen = !menuOpen" class="p-2 text-text-muted hover:text-primary hover:bg-brand-light/30 rounded-full transition-colors focus:outline-none cursor-pointer">
                                        <svg class="w-5 h-5 pointer-events-none" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                    </button>
                                    <div x-show="menuOpen" x-transition x-cloak class="absolute right-0 mt-2 w-40 bg-white rounded-xl shadow-xl border border-border-subtle z-[60] overflow-hidden">
                                        <button type="button" @click.stop="editModal = true; menuOpen = false" class="w-full text-left px-4 py-3 text-sm font-medium text-text-main hover:bg-brand-light/20 hover:text-primary transition-colors cursor-pointer flex items-center">
                                            <svg class="w-4 h-4 mr-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            Update
                                        </button>
                                        <form action="{{ route('seller.products.archive', $product) }}" method="POST" class="m-0">
                                            @csrf @method('DELETE')
                                            <button type="submit" @click.stop class="w-full text-left px-4 py-3 text-sm font-medium text-red-500 hover:bg-red-50 transition-colors cursor-pointer flex items-center">
                                                <svg class="w-4 h-4 mr-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                                                Archive
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>

                            <!-- View Modal -->
                            <template x-teleport="body">
                                <div x-show="viewModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-8">
                                    <div @click="viewModal = false" class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity cursor-pointer"></div>
                                    <div class="bg-white rounded-3xl w-full max-w-5xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh] relative z-10 text-left">
                                        <button type="button" @click="viewModal = false" class="absolute top-4 right-4 z-20 p-2 bg-white/80 backdrop-blur border border-border-subtle text-text-muted hover:text-red-500 rounded-full hover:bg-red-50 transition-all shadow-sm cursor-pointer">
                                            <svg class="w-6 h-6 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                        <div class="flex flex-col md:flex-row h-full overflow-y-auto custom-scrollbar bg-surface/30 relative z-10">
                                            <div class="w-full md:w-1/2 p-6 md:p-8 border-b md:border-b-0 md:border-r border-border-subtle bg-white">
                                                <div class="aspect-square bg-white rounded-2xl border border-border-subtle overflow-hidden flex items-center justify-center p-4 shadow-inner mb-4">
                                                    <template x-if="activeImage !== ''">
                                                        <img :src="activeImage" alt="Product" class="w-full h-full object-contain rounded-xl transition-all duration-300">
                                                    </template>
                                                    <template x-if="activeImage === ''">
                                                        <svg class="w-24 h-24 text-gray-200 pointer-events-none" fill="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                    </template>
                                                </div>
                                                @if($product->pictures && is_array($product->pictures))
                                                    <div class="flex gap-3 overflow-x-auto pb-2 custom-scrollbar">
                                                        @foreach($product->pictures as $pic)
                                                            <button type="button" @click.stop="activeImage = '{{ asset('storage/' . $pic) }}'"
                                                                    :class="activeImage === '{{ asset('storage/' . $pic) }}' ? 'border-primary ring-2 ring-primary/20' : 'border-border-subtle hover:border-primary/50'"
                                                                    class="w-20 h-20 flex-shrink-0 rounded-xl border-2 transition-all p-1 bg-white cursor-pointer">
                                                                <img src="{{ asset('storage/' . $pic) }}" class="w-full h-full object-cover rounded-lg pointer-events-none">
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="w-full md:w-1/2 p-6 md:p-8 space-y-6 flex flex-col">
                                                @if($approvalStatus === 'Disapproved')
                                                    <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-r-xl shadow-sm">
                                                        <div class="flex items-start gap-3">
                                                            <svg class="w-5 h-5 text-red-500 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                            <div>
                                                                <h4 class="text-sm font-bold text-red-900 uppercase tracking-wider">Product Disapproved</h4>
                                                                <p class="text-xs text-red-800 mt-1 font-medium italic">Reason: "{{ $product->approval->remarks }}"</p>
                                                                <button type="button" @click="viewModal = false; editModal = true" class="mt-2 text-xs font-bold text-red-700 underline cursor-pointer hover:text-red-900">Update Product & Resubmit</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @elseif($approvalStatus === 'Pending')
                                                    <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r-xl shadow-sm text-xs text-yellow-800 font-medium">
                                                        This product is currently <strong>Pending Review</strong> and is not yet visible to the public.
                                                    </div>
                                                @elseif($hasPendingAdditions)
                                                    <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r-xl shadow-sm text-xs text-yellow-800 font-medium">
                                                        Approved variants are live on the store. Newly added variants or subvariants marked as <strong>Pending</strong> are hidden from public view until approved by an admin.
                                                    </div>
                                                @endif

                                                <div>
                                                    <h1 class="text-3xl font-extrabold text-primary-dark mb-1 leading-tight">{{ $product->name }}</h1>
                                                    <p class="text-sm text-text-muted font-bold mb-3">{{ $product->category }} &bull; {{ $product->subcategory ?? 'Uncategorized' }}</p>
                                                    <div class="flex items-center justify-between bg-brand-light/10 p-5 rounded-2xl border border-brand-light/30 shadow-sm">
                                                        <div class="flex items-end gap-3">
                                                            <template x-if="discountPercent > 0">
                                                                <div class="flex flex-col">
                                                                    <div class="flex items-center gap-2 mb-1">
                                                                        <span class="text-lg line-through text-text-muted" x-text="'₱' + activePrice.toFixed(2)"></span>
                                                                        <span class="px-2 py-0.5 bg-red-100 text-red-600 text-[10px] font-bold rounded-md tracking-wider uppercase" x-text="discountPercent + '% OFF'"></span>
                                                                    </div>
                                                                    <span class="text-4xl font-extrabold text-red-500" x-text="'₱' + (activePrice - (activePrice * (discountPercent / 100))).toFixed(2)"></span>
                                                                </div>
                                                            </template>
                                                            <template x-if="discountPercent <= 0">
                                                                <span class="text-4xl font-extrabold text-red-500" x-text="'₱' + activePrice.toFixed(2)"></span>
                                                            </template>
                                                        </div>
                                                        <div class="text-right">
                                                            <span class="px-3 py-1.5 rounded-full text-xs font-bold shadow-sm border block"
                                                                  :class="activeStock > 0 ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-100 text-red-700 border-red-200'">
                                                                <span x-text="activeStock"></span> in stock
                                                            </span>
                                                            <span class="text-[10px] text-text-muted font-medium mt-1 block" x-text="selectedMain !== null ? 'Selected Variant Stock' : 'Active Public Stock'"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                @if($productVariants && isset($productVariants['items']) && is_array($productVariants['items']))
                                                    <div class="bg-white p-5 rounded-2xl border border-border-subtle shadow-sm space-y-4">
                                                        <div>
                                                            <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-3">{{ $productVariants['title'] ?? 'Variants' }}</h3>
                                                            <div class="flex flex-wrap gap-2">
                                                                @foreach($productVariants['items'] as $vIndex => $item)
                                                                    @if(!empty($item['is_archived'])) @continue @endif
                                                                    @php
                                                                        $vEffStatus = !$isApprovedProduct ? $approvalStatus : ($item['status'] ?? 'Approved');
                                                                        $hasSubs = isset($item['subs']) && count($item['subs']) > 0;
                                                                        $itemStock = 0;
                                                                        if ($hasSubs) {
                                                                            foreach ($item['subs'] as $sb) {
                                                                                if (!empty($sb['is_archived']) || ($sb['status'] ?? 'Approved') === 'Disapproved') continue;
                                                                                if ($isApprovedProduct && $vEffStatus === 'Approved' && ($sb['status'] ?? 'Approved') !== 'Approved') continue;
                                                                                $itemStock += (int)($sb['stock'] ?? 0);
                                                                            }
                                                                        } else {
                                                                            $itemStock = (int)($item['stock'] ?? 0);
                                                                        }
                                                                    @endphp
                                                                    <button type="button" @click.stop="
                                                                        if (selectedMain === '{{ $vIndex }}') {
                                                                            selectedMain = null; selectedSub = null;
                                                                            activePrice = basePrice; activeStock = totalStockCount; activeImage = defaultImage;
                                                                        } else {
                                                                            selectedMain = '{{ $vIndex }}'; selectedSub = null;
                                                                            activePrice = {{ (float)($item['price'] ?? $product->price) }}; activeStock = {{ $itemStock }};
                                                                            @if(isset($item['image']) && $item['image'])
                                                                                activeImage = '{{ asset('storage/' . $item['image']) }}';
                                                                            @endif
                                                                        }"
                                                                        :class="selectedMain === '{{ $vIndex }}' ? 'bg-primary text-white border-primary shadow-md' : 'bg-surface text-text-main border-border-subtle hover:border-primary hover:bg-brand-light/10'"
                                                                        class="px-4 py-2 text-sm font-bold border-2 rounded-xl transition-all cursor-pointer flex items-center gap-2">
                                                                        <span class="pointer-events-none">{{ $item['name'] ?? 'Unnamed' }}</span>
                                                                        @if($vEffStatus === 'Pending')
                                                                            <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-yellow-100 text-yellow-800 font-bold pointer-events-none">Pending</span>
                                                                        @elseif($vEffStatus === 'Disapproved')
                                                                            <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-red-100 text-red-700 font-bold pointer-events-none">Disapproved</span>
                                                                        @else
                                                                            <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-green-100 text-green-700 font-bold pointer-events-none">Approved</span>
                                                                        @endif
                                                                        <span class="text-xs px-1.5 py-0.5 rounded-md font-semibold pointer-events-none" :class="selectedMain === '{{ $vIndex }}' ? 'bg-white/20 text-white' : 'bg-gray-100 text-text-muted'">{{ $itemStock }} left</span>
                                                                    </button>
                                                                @endforeach
                                                            </div>
                                                        </div>

                                                        @foreach($productVariants['items'] as $vIndex => $item)
                                                            @if(!empty($item['is_archived'])) @continue @endif
                                                            @php $vEffStatus = !$isApprovedProduct ? $approvalStatus : ($item['status'] ?? 'Approved'); @endphp
                                                            @if(isset($item['subs']) && count($item['subs']) > 0)
                                                                <div x-show="selectedMain === '{{ $vIndex }}'" x-collapse class="pt-4 border-t border-border-subtle">
                                                                    <h4 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-3">{{ $productVariants['sub_title'] ?? 'Sub Variants' }}</h4>
                                                                    <div class="flex flex-wrap gap-2">
                                                                        @foreach($item['subs'] as $sIndex => $sub)
                                                                            @if(!empty($sub['is_archived'])) @continue @endif
                                                                            @php
                                                                                $subStock = (int)($sub['stock'] ?? 0);
                                                                                $sEffStatus = !$isApprovedProduct ? $approvalStatus : ($vEffStatus !== 'Approved' ? $vEffStatus : ($sub['status'] ?? 'Approved'));
                                                                            @endphp
                                                                            <button type="button" @click.stop="
                                                                                if (selectedSub === '{{ $sIndex }}') {
                                                                                    selectedSub = null; activePrice = {{ (float)($item['price'] ?? $product->price) }}; activeStock = {{ $totalStock }};
                                                                                    activeImage = {{ (isset($item['image']) && $item['image']) ? '\'' . asset('storage/' . $item['image']) . '\'' : 'defaultImage' }};
                                                                                } else {
                                                                                    selectedSub = '{{ $sIndex }}'; activePrice = {{ (float)($sub['price'] ?? ($item['price'] ?? $product->price)) }}; activeStock = {{ $subStock }};
                                                                                    @if(isset($sub['image']) && $sub['image'])
                                                                                        activeImage = '{{ asset('storage/' . $sub['image']) }}';
                                                                                    @endif
                                                                                }"
                                                                                :class="selectedSub === '{{ $sIndex }}' ? 'bg-primary text-white border-primary shadow-md' : 'bg-surface text-text-main border-border-subtle hover:border-primary hover:bg-brand-light/10'"
                                                                                class="px-4 py-2 border-2 rounded-xl text-sm font-bold transition-all cursor-pointer flex items-center gap-2">
                                                                                <span class="pointer-events-none">{{ $sub['name'] ?? '' }}</span>
                                                                                @if($sEffStatus === 'Pending')
                                                                                    <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-yellow-100 text-yellow-800 font-bold pointer-events-none">Pending</span>
                                                                                @elseif($sEffStatus === 'Disapproved')
                                                                                    <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-red-100 text-red-700 font-bold pointer-events-none">Disapproved</span>
                                                                                @else
                                                                                    <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-green-100 text-green-700 font-bold pointer-events-none">Approved</span>
                                                                                @endif
                                                                                <span class="text-xs px-1.5 py-0.5 rounded-md font-semibold pointer-events-none" :class="selectedSub === '{{ $sIndex }}' ? 'bg-white/20 text-white' : 'bg-gray-100 text-text-muted'">{{ $subStock }} left</span>
                                                                            </button>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>

                                                    <div class="bg-white p-5 rounded-2xl border border-border-subtle shadow-sm space-y-3">
                                                        <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider">All Variant Stock & Compliance Details</h3>
                                                        <div class="overflow-x-auto custom-scrollbar">
                                                            <table class="w-full text-left text-xs border-collapse">
                                                                <thead>
                                                                    <tr class="bg-gray-50 border-b border-border-subtle text-text-muted font-bold">
                                                                        <th class="p-2">Variant</th>
                                                                        <th class="p-2">Subvariant</th>
                                                                        <th class="p-2">Status</th>
                                                                        <th class="p-2">Price</th>
                                                                        <th class="p-2 text-right">Stock</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-border-subtle">
                                                                    @foreach($productVariants['items'] as $item)
                                                                        @if(!empty($item['is_archived'])) @continue @endif
                                                                        @php $vEffStatus = !$isApprovedProduct ? $approvalStatus : ($item['status'] ?? 'Approved'); @endphp
                                                                        @if(isset($item['subs']) && count($item['subs']) > 0)
                                                                            @foreach($item['subs'] as $sub)
                                                                                @if(!empty($sub['is_archived'])) @continue @endif
                                                                                @php $sEffStatus = !$isApprovedProduct ? $approvalStatus : ($vEffStatus !== 'Approved' ? $vEffStatus : ($sub['status'] ?? 'Approved')); @endphp
                                                                                <tr class="hover:bg-brand-light/10">
                                                                                    <td class="p-2 font-medium text-primary-dark">{{ $item['name'] ?? 'N/A' }}</td>
                                                                                    <td class="p-2 text-text-main font-semibold">{{ $sub['name'] ?? 'N/A' }}</td>
                                                                                    <td class="p-2">
                                                                                        @if($sEffStatus === 'Approved')
                                                                                            <span class="text-[10px] px-2 py-0.5 bg-green-100 text-green-700 font-bold rounded-full">Approved (Live)</span>
                                                                                        @elseif($sEffStatus === 'Disapproved')
                                                                                            <span class="text-[10px] px-2 py-0.5 bg-red-100 text-red-700 font-bold rounded-full">Disapproved (Hidden)</span>
                                                                                        @else
                                                                                            <span class="text-[10px] px-2 py-0.5 bg-yellow-100 text-yellow-800 font-bold rounded-full">Pending (Hidden)</span>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td class="p-2">₱{{ number_format((float)($sub['price'] ?? ($item['price'] ?? $product->price)), 2) }}</td>
                                                                                    <td class="p-2 text-right">
                                                                                        <span class="px-2 py-0.5 rounded-full font-bold {{ ($sub['stock'] ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $sub['stock'] ?? 0 }} units</span>
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                        @else
                                                                            <tr class="hover:bg-brand-light/10">
                                                                                <td class="p-2 font-medium text-primary-dark">{{ $item['name'] ?? 'N/A' }}</td>
                                                                                <td class="p-2 text-text-muted italic">None</td>
                                                                                <td class="p-2">
                                                                                    @if($vEffStatus === 'Approved')
                                                                                        <span class="text-[10px] px-2 py-0.5 bg-green-100 text-green-700 font-bold rounded-full">Approved (Live)</span>
                                                                                    @elseif($vEffStatus === 'Disapproved')
                                                                                        <span class="text-[10px] px-2 py-0.5 bg-red-100 text-red-700 font-bold rounded-full">Disapproved (Hidden)</span>
                                                                                    @else
                                                                                        <span class="text-[10px] px-2 py-0.5 bg-yellow-100 text-yellow-800 font-bold rounded-full">Pending (Hidden)</span>
                                                                                    @endif
                                                                                </td>
                                                                                <td class="p-2">₱{{ number_format((float)($item['price'] ?? $product->price), 2) }}</td>
                                                                                <td class="p-2 text-right">
                                                                                    <span class="px-2 py-0.5 rounded-full font-bold {{ ($item['stock'] ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $item['stock'] ?? 0 }} units</span>
                                                                                </td>
                                                                            </tr>
                                                                        @endif
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="bg-white p-5 rounded-2xl border border-border-subtle shadow-sm flex-1">
                                                    <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-3">Product Description</h3>
                                                    <p class="text-sm text-text-main leading-relaxed whitespace-pre-line">{{ $product->description ?: 'No description provided.' }}</p>
                                                </div>

                                                <div class="h-6 md:h-8 w-full shrink-0"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Edit Modal -->
                            <template x-teleport="body">
                                <div x-show="editModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-8"
                                     x-data="storkiaEditVariantManager(@js($editManagerConfig))">
                                    
                                    <div @click.stop="editModal = false" class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity cursor-pointer"></div>
                                     
                                    <div class="bg-white rounded-3xl w-full max-w-4xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] text-left relative z-10 pointer-events-auto">
                                        
                                        <div class="flex justify-between items-center p-6 border-b border-border-subtle bg-surface shrink-0 relative z-20">
                                            <div>
                                                <h2 class="text-xl font-bold text-primary-dark">Update Product</h2>
                                                @if($isApprovedProduct)
                                                    <p class="text-xs text-text-muted mt-0.5">Any newly added variants or subvariants on an approved product will require admin approval before becoming public.</p>
                                                @endif
                                            </div>
                                            <button type="button" @click.stop="editModal = false" class="p-2 text-gray-500 hover:text-red-500 rounded-full hover:bg-red-50 transition-colors cursor-pointer">
                                                <svg class="w-6 h-6 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                        
                                        <form action="{{ route('seller.products.update', $product->id) }}" method="POST" enctype="multipart/form-data"
                                              @submit="if(hasValidationErrors()) { $event.preventDefault(); }"
                                              class="flex flex-col flex-1 overflow-hidden relative z-10">
                                            @csrf @method('PUT')
                                            
                                            <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar bg-surface/30 flex-1 relative z-10">
                                                
                                                <div x-show="hasValidationErrors()" x-cloak class="p-4 bg-red-50 border-l-4 border-red-500 rounded-r-xl shadow-sm flex items-start gap-3">
                                                    <svg class="w-6 h-6 text-red-500 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    <div>
                                                        <h4 class="text-sm font-bold text-red-900 uppercase tracking-wider">Validation Error</h4>
                                                        <p class="text-xs text-red-800 mt-1 font-medium italic">Please ensure all variant and subvariant names are filled and unique to proceed.</p>
                                                    </div>
                                                </div>

                                                @if($approvalStatus === 'Disapproved')
                                                    <div class="p-4 bg-red-50 border-l-4 border-red-500 rounded-r-xl shadow-sm bg-white">
                                                        <div class="flex items-start gap-3">
                                                            <svg class="w-6 h-6 text-red-500 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                            <div>
                                                                <h4 class="text-sm font-bold text-red-900 uppercase tracking-wider">Disapproved ({{ $product->approval->disapproval_type }})</h4>
                                                                <p class="text-xs text-red-800 mt-1 font-medium italic">Admin Feedback: "{{ $product->approval->remarks }}"</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-5">
                                                    <h3 class="text-lg font-bold text-primary-dark">Basic Information</h3>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <label class="block text-sm font-bold text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                                                            <input type="text" name="name" maxlength="100" value="{{ $product->name }}" {{ $isApprovedProduct ? 'readonly' : 'required' }} class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none text-sm cursor-text {{ $isApprovedProduct ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : '' }}">
                                                        </div>
                                                        
                                                        <div x-data="{ subDropdownOpen: false, selectedSub: '{{ addslashes($product->subcategory ?? '') }}' }" class="relative">
                                                            <label class="block text-sm font-bold text-gray-700 mb-1">Subcategory <span class="text-red-500">*</span></label>
                                                            <input type="hidden" name="subcategory" :value="selectedSub" required>
                                                            
                                                            <button type="button" {{ $isApprovedProduct ? 'disabled' : '' }}
                                                                    @click.stop="if(!{{ $isApprovedProduct ? 'true' : 'false' }}) subDropdownOpen = !subDropdownOpen"
                                                                    @click.away="subDropdownOpen = false"
                                                                    class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none bg-surface text-sm flex justify-between items-center transition-colors shadow-sm {{ $isApprovedProduct ? 'bg-gray-100 cursor-not-allowed' : 'cursor-pointer hover:border-primary/50' }}">
                                                                <span x-text="selectedSub || 'Select a Subcategory'" :class="selectedSub ? 'text-gray-800 font-medium' : 'text-gray-400'" class="pointer-events-none"></span>
                                                                @if(!$isApprovedProduct)
                                                                    <svg class="w-4 h-4 text-gray-500 transition-transform duration-200 pointer-events-none" :class="subDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                                @endif
                                                            </button>
                                                            
                                                            @if(!$isApprovedProduct)
                                                                <div x-show="subDropdownOpen" x-cloak class="absolute z-[150] w-full mt-2 bg-white border border-border-subtle rounded-xl shadow-xl max-h-60 overflow-y-auto custom-scrollbar">
                                                                    @foreach($subcategories as $sub)
                                                                        <div @click.stop="selectedSub = '{{ addslashes($sub['name']) }}'; subDropdownOpen = false"
                                                                             class="px-4 py-3 text-sm cursor-pointer transition-colors flex items-center justify-between"
                                                                             :class="selectedSub === '{{ addslashes($sub['name']) }}' ? 'bg-primary/10 text-primary font-bold' : 'text-gray-800 hover:bg-brand-light/30'">
                                                                            <span class="pointer-events-none">{{ $sub['name'] }}</span>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Main Pictures</label>
                                                        @if($isApprovedProduct)
                                                            <div class="flex gap-3 overflow-x-auto custom-scrollbar bg-gray-50 p-3 rounded-xl border border-gray-200">
                                                                @if($product->pictures && is_array($product->pictures))
                                                                    @foreach($product->pictures as $pic)
                                                                        <img src="{{ asset('storage/' . $pic) }}" class="h-20 w-20 object-cover rounded-lg border border-gray-200 shadow-sm shrink-0 pointer-events-none">
                                                                    @endforeach
                                                                @else
                                                                    <span class="text-xs text-gray-500 italic pointer-events-none">No images available</span>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <input type="file" name="pictures[]" multiple accept="image/*" class="w-full p-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm cursor-pointer file:cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                                                        @endif
                                                    </div>

                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <label class="block text-sm font-bold text-gray-700 mb-1">Set Discount (%)</label>
                                                            <div class="relative">
                                                                <input type="number" step="0.01" min="0" max="100" name="discount" x-model="discountPercent"
                                                                       onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                       class="w-full pr-8 p-3 rounded-xl border border-border-subtle focus:border-primary outline-none text-sm cursor-text">
                                                                <span class="absolute right-3 top-3 text-gray-400 font-bold pointer-events-none">%</span>
                                                            </div>
                                                        </div>
                                                        <div class="p-3 bg-brand-light/10 border border-brand-light/30 rounded-xl shadow-inner flex flex-col justify-center">
                                                            <div class="flex justify-between items-center text-xs mb-1">
                                                                <span class="text-gray-600">Base Price:</span>
                                                                <span class="font-bold text-gray-800">₱<span x-text="basePrice"></span></span>
                                                            </div>
                                                            <div class="flex justify-between items-center">
                                                                <span class="text-primary font-bold text-sm">Discounted Price:</span>
                                                                <span class="text-lg font-bold text-red-500">₱<span x-text="editDiscountedPrice"></span></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                @if($productVariants && isset($productVariants['items']))
                                                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-5 relative">
                                                        <h3 class="text-lg font-bold text-primary-dark">Edit Product Variants</h3>
                                                        <div class="space-y-4">
                                                            @foreach($productVariants['items'] as $vIndex => $item)
                                                                @php
                                                                    $vKey = 'v_' . $vIndex;
                                                                    $vEffStatus = !$isApprovedProduct ? $approvalStatus : ($item['status'] ?? 'Approved');
                                                                @endphp
                                                                @if(!empty($item['is_archived']) || $vEffStatus === 'Disapproved') @continue @endif
                                                                <div x-show="!isVariantArchived('{{ $vKey }}')" class="variant-container p-5 bg-white rounded-2xl border border-gray-200 shadow-sm relative">
                                                                    
                                                                    @if($vEffStatus === 'Pending')
                                                                        <span class="absolute top-3 right-3 px-2.5 py-0.5 bg-yellow-100 text-yellow-800 text-[10px] font-bold rounded-md border border-yellow-300 z-10 pointer-events-none">Pending Review</span>
                                                                    @elseif($vEffStatus === 'Approved')
                                                                        <span class="absolute top-3 right-3 px-2.5 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded-md border border-green-200 z-10 pointer-events-none">Approved</span>
                                                                    @endif

                                                                    <div class="flex flex-col sm:flex-row sm:items-start gap-6 px-2 pt-2">
                                                                        <div class="flex-1">
                                                                            <label class="block text-xs font-bold text-gray-700 mb-1">{{ $productVariants['title'] ?? 'Color Name' }}</label>
                                                                            <input type="text" name="variant_names[{{ $vIndex }}]"
                                                                                   x-model="existingVariants['{{ $vKey }}'].name"
                                                                                   :required="!isVariantArchived('{{ $vKey }}')"
                                                                                   placeholder="{{ ($productVariants['title'] ?? 'Variant') . ' Name' }}"
                                                                                   :class="isVariantDuplicate(existingVariants['{{ $vKey }}']?.name) ? 'border-red-500 focus:border-red-500 text-red-600' : 'border-border-subtle focus:border-primary text-gray-900'"
                                                                                   class="w-full p-2.5 text-sm rounded-lg border outline-none transition-colors bg-white cursor-text">
                                                                            <span x-show="isVariantDuplicate(existingVariants['{{ $vKey }}']?.name)" class="text-red-500 text-[10px] font-bold block mt-1 pointer-events-none">Variant name must be unique.</span>
                                                                        </div>
                                                                        <div>
                                                                            <label class="block text-xs font-bold text-gray-700 mb-1">Image</label>
                                                                            <div class="flex items-center gap-2">
                                                                                @if(!empty($item['image']))
                                                                                    <img src="{{ asset('storage/'.$item['image']) }}" class="h-10 w-10 object-cover rounded-lg border border-gray-200 shadow-sm shrink-0 pointer-events-none">
                                                                                @endif
                                                                                @if(!$isApprovedProduct || $vEffStatus !== 'Approved')
                                                                                    <input type="file" name="variant_pictures[{{ $vIndex }}]" accept="image/*" class="w-full p-1.5 text-xs rounded-lg border border-gray-200 cursor-pointer file:cursor-pointer file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-gray-100 file:text-gray-600 file:text-[10px] file:font-bold bg-white">
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <hr class="border-gray-100 my-4">

                                                                    @if($priceDependency === 'main')
                                                                        <div class="flex flex-wrap gap-3 px-2">
                                                                            <div class="relative w-32">
                                                                                <span class="absolute left-2.5 top-2.5 text-gray-400 text-xs font-bold pointer-events-none">₱</span>
                                                                                <input type="number" step="0.01" min="20" name="variant_prices[{{ $vIndex }}]" x-model="existingVariants['{{ $vKey }}'].price" :required="!isVariantArchived('{{ $vKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" class="w-full pl-6 p-2 text-sm rounded-lg border border-border-subtle focus:border-primary outline-none no-spinners text-gray-800 cursor-text" placeholder="Price">
                                                                            </div>
                                                                            <div class="relative w-28">
                                                                                <input type="number" step="0.01" min="0" name="variant_weights[{{ $vIndex }}]" x-model="existingVariants['{{ $vKey }}'].weight" :required="!isVariantArchived('{{ $vKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" class="w-full pr-8 p-2 text-sm rounded-lg border border-border-subtle focus:border-primary outline-none no-spinners text-gray-800 cursor-text" placeholder="Weight">
                                                                                <span class="absolute right-2.5 top-2.5 text-gray-400 text-xs font-bold pointer-events-none">KG</span>
                                                                            </div>
                                                                            <div class="relative w-24">
                                                                                <input type="number" min="0" name="variant_stocks[{{ $vIndex }}]" x-model="existingVariants['{{ $vKey }}'].stock" :required="!isVariantArchived('{{ $vKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57)" class="w-full p-2 text-sm rounded-lg border border-border-subtle focus:border-primary outline-none no-spinners text-center text-gray-800 cursor-text" placeholder="Stock">
                                                                            </div>
                                                                        </div>
                                                                        <hr class="border-gray-100 my-4">
                                                                    @endif

                                                                    @if($hasSubvariantsArchitecture || isset($item['subs']))
                                                                        <div class="px-2">
                                                                            <div class="flex items-center justify-between mb-3">
                                                                                <label class="block text-[10px] font-bold text-gray-700 uppercase tracking-widest pointer-events-none">{{ $productVariants['sub_title'] ?? 'SIZES' }}</label>
                                                                                <span x-show="!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === ''" class="text-[10px] text-red-400 font-medium italic">(Fill main variant name first)</span>
                                                                            </div>
                                                                            
                                                                            @if(isset($item['subs']) && is_array($item['subs']))
                                                                                @foreach($item['subs'] as $sIndex => $sub)
                                                                                    @php
                                                                                        $sKey = 's_' . $sIndex;
                                                                                        $sEffStatus = !$isApprovedProduct ? $approvalStatus : ($vEffStatus !== 'Approved' ? $vEffStatus : ($sub['status'] ?? 'Approved'));
                                                                                    @endphp
                                                                                    @if(!empty($sub['is_archived']) || $sEffStatus === 'Disapproved') @continue @endif
                                                                                    <div x-show="!isSubArchived('{{ $vKey }}', '{{ $sKey }}')" class="flex flex-wrap sm:flex-nowrap items-start gap-3 bg-brand-light/10 p-3 rounded-xl border border-brand-light/30 shadow-sm mb-3 relative">
                                                                                        
                                                                                        @if($sEffStatus === 'Pending')
                                                                                            <span class="absolute -top-2.5 left-3 px-2 py-0.5 bg-yellow-100 border border-yellow-300 text-yellow-800 text-[9px] font-bold rounded-md z-10 pointer-events-none">Pending</span>
                                                                                        @elseif($sEffStatus === 'Approved')
                                                                                            <span class="absolute -top-2.5 left-3 px-2 py-0.5 bg-green-100 border border-green-200 text-green-700 text-[9px] font-bold rounded-md z-10 pointer-events-none">Approved</span>
                                                                                        @endif
                                                                                        
                                                                                        <div class="flex-1 min-w-[160px] pt-1">
                                                                                            <input type="text" name="sub_variant_names[{{ $vIndex }}][{{ $sIndex }}]"
                                                                                                   x-model="existingVariants['{{ $vKey }}'].subs['{{ $sKey }}'].name"
                                                                                                   :required="!isVariantArchived('{{ $vKey }}') && !isSubArchived('{{ $vKey }}', '{{ $sKey }}')"
                                                                                                   placeholder="{{ ($productVariants['sub_title'] ?? 'Subvariant') . ' Name' }}"
                                                                                                   :class="isExistingVariantSubDuplicate('{{ $vKey }}', existingVariants['{{ $vKey }}']?.subs?.['{{ $sKey }}']?.name) ? 'border-red-500 focus:border-red-500 text-red-600 bg-white' : 'border-border-subtle focus:border-primary bg-white text-gray-800'"
                                                                                                   class="w-full p-2 text-xs rounded-md border outline-none font-medium transition-colors cursor-text">
                                                                                            <span x-show="isExistingVariantSubDuplicate('{{ $vKey }}', existingVariants['{{ $vKey }}']?.subs?.['{{ $sKey }}']?.name)" class="text-red-500 text-[10px] font-bold block mt-1 pointer-events-none">Sub-variant must be unique.</span>
                                                                                        </div>
                                                                                        
                                                                                        @if($priceDependency === 'sub')
                                                                                            <div class="flex gap-2 pt-1">
                                                                                                <div class="relative w-28">
                                                                                                    <span class="absolute left-2.5 top-2 text-text-muted text-xs font-bold pointer-events-none">₱</span>
                                                                                                    <input type="number" step="0.01" min="20" name="sub_variant_prices[{{ $vIndex }}][{{ $sIndex }}]" x-model="existingVariants['{{ $vKey }}'].subs['{{ $sKey }}'].price" :required="!isVariantArchived('{{ $vKey }}') && !isSubArchived('{{ $vKey }}', '{{ $sKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" class="w-full pl-6 p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners bg-white text-gray-800 cursor-text" placeholder="Price">
                                                                                                </div>
                                                                                                <div class="relative w-24">
                                                                                                    <input type="number" step="0.01" min="0" name="sub_variant_weights[{{ $vIndex }}][{{ $sIndex }}]" x-model="existingVariants['{{ $vKey }}'].subs['{{ $sKey }}'].weight" :required="!isVariantArchived('{{ $vKey }}') && !isSubArchived('{{ $vKey }}', '{{ $sKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" class="w-full pr-7 p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners bg-white text-gray-800 cursor-text" placeholder="Weight">
                                                                                                    <span class="absolute right-2 top-2 text-text-muted text-xs font-bold pointer-events-none">KG</span>
                                                                                                </div>
                                                                                                <div class="relative w-20">
                                                                                                    <input type="number" min="0" name="sub_variant_stocks[{{ $vIndex }}][{{ $sIndex }}]" x-model="existingVariants['{{ $vKey }}'].subs['{{ $sKey }}'].stock" :required="!isVariantArchived('{{ $vKey }}') && !isSubArchived('{{ $vKey }}', '{{ $sKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57)" class="w-full p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners bg-white text-center text-gray-800 cursor-text" placeholder="Stock">
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
                                                                                        
                                                                                        <button type="button" @click.stop="requestConfirm('Are you sure you want to remove this subvariant?', () => { archiveExistingSubVariant('{{ $vKey }}', '{{ $sKey }}'); })" title="Remove Subvariant" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors mt-0.5 cursor-pointer">
                                                                                            <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                                        </button>
                                                                                        <input type="hidden" name="archive_sub_variants[{{ $vIndex }}][{{ $sIndex }}]" :value="isSubArchived('{{ $vKey }}', '{{ $sKey }}') ? 1 : 0">
                                                                                    </div>
                                                                                @endforeach
                                                                            @endif

                                                                            <div class="mt-4 space-y-3">
                                                                                <template x-for="(ns, nsIndex) in getNewSubsForVariant('{{ $vKey }}')" :key="ns.id">
                                                                                    <div class="space-y-2 bg-brand-light/10 p-3 rounded-xl border border-primary/40 shadow-sm relative mt-3">
                                                                                        <span class="absolute -top-2.5 left-3 px-2 py-0.5 bg-yellow-100 border border-yellow-300 text-yellow-800 text-[9px] font-bold rounded-md z-10 shadow-sm pointer-events-none">
                                                                                            {{ $isApprovedProduct ? 'New Subvariant • Pending Approval' : 'New Subvariant' }}
                                                                                        </span>

                                                                                        <div x-show="!ns.isCopied && getFirstSubTemplateForExisting('{{ $vKey }}')" x-transition class="p-2 bg-blue-50 border border-blue-200 rounded-lg flex flex-wrap items-center justify-between gap-2 text-xs text-blue-900 shadow-sm mt-1">
                                                                                            <div class="flex items-center gap-2">
                                                                                                <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                                                <span>Copy Price, Weight, and Stock from 1st subvariant (<strong x-text="getFirstSubTemplateForExisting('{{ $vKey }}')?.name || '1st Subvariant'"></strong>)?</span>
                                                                                            </div>
                                                                                            <button type="button" @click.stop="copyFirstSubToExistingNew('{{ $vKey }}', nsIndex)" class="px-3 py-1 bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 transition-colors cursor-pointer text-[11px] shadow-sm">Copy</button>
                                                                                        </div>

                                                                                        <div class="flex flex-wrap sm:flex-nowrap items-start gap-3 pt-1">
                                                                                            <div class="flex-1 min-w-[160px]">
                                                                                                <input type="text" :name="`new_existing_sub_variant_names[{{ $vIndex }}][${ns.id}]`" x-model="ns.name" required
                                                                                                       :disabled="!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === ''"
                                                                                                       placeholder="{{ ($productVariants['sub_title'] ?? 'Subvariant') . ' Name (e.g. 128GB)' }}"
                                                                                                       :class="(isExistingVariantSubDuplicate('{{ $vKey }}', ns.name) ? 'border-red-500 focus:border-red-500 text-red-600 ' : 'border-border-subtle focus:border-primary ') + ((!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === '') ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-800')"
                                                                                                       class="w-full p-2 text-xs rounded-md border outline-none font-medium transition-colors">
                                                                                                <span x-show="isExistingVariantSubDuplicate('{{ $vKey }}', ns.name)" class="text-red-500 text-[10px] font-bold block mt-1 pointer-events-none">Sub-variant must be unique.</span>
                                                                                            </div>
                                                                                            @if($priceDependency === 'sub')
                                                                                                <div class="flex gap-2">
                                                                                                    <div class="relative w-28">
                                                                                                        <span class="absolute left-2.5 top-2 text-text-muted text-xs font-bold pointer-events-none">₱</span>
                                                                                                        <input type="number" step="0.01" min="20" :name="`new_existing_sub_variant_prices[{{ $vIndex }}][${ns.id}]`" x-model="ns.price" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" placeholder="Price" class="w-full pl-6 p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners bg-white text-gray-800 cursor-text">
                                                                                                    </div>
                                                                                                    <div class="relative w-24">
                                                                                                        <input type="number" step="0.01" min="0" :name="`new_existing_sub_variant_weights[{{ $vIndex }}][${ns.id}]`" x-model="ns.weight" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" placeholder="Weight" class="w-full pr-7 p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners bg-white text-gray-800 cursor-text">
                                                                                                        <span class="absolute right-2 top-2 text-text-muted text-xs font-bold pointer-events-none">KG</span>
                                                                                                    </div>
                                                                                                    <div class="relative w-20">
                                                                                                        <input type="number" min="0" :name="`new_existing_sub_variant_stocks[{{ $vIndex }}][${ns.id}]`" x-model="ns.stock" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57)" placeholder="Stock" class="w-full p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners bg-white text-center text-gray-800 cursor-text">
                                                                                                    </div>
                                                                                                </div>
                                                                                            @endif
                                                                                            <button type="button" @click.stop="removeNewSubFromVariant('{{ $vKey }}', ns.id)" title="Remove Subvariant" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors cursor-pointer">
                                                                                                <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                                                            </button>
                                                                                        </div>
                                                                                    </div>
                                                                                </template>
                                                                                <button type="button"
                                                                                        @click.stop="existingVariants['{{ $vKey }}']?.name?.trim() !== '' ? addNewSubToVariant('{{ $vKey }}') : null"
                                                                                        :disabled="!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === ''"
                                                                                        :class="(!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === '') ? 'text-gray-400 cursor-not-allowed opacity-60' : 'text-primary hover:underline cursor-pointer'"
                                                                                        class="text-sm font-bold tracking-wide mt-1 inline-block">
                                                                                    + Add New Subvariant
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    @endif

                                                                    <hr class="border-gray-100 mt-5 mb-4">
                                                                    <div class="flex justify-end px-2">
                                                                        <button type="button" @click.stop="requestConfirm('Are you sure you want to remove this variant?', () => { archiveExistingVariant('{{ $vKey }}'); })" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-red-600 font-bold transition-colors cursor-pointer">
                                                                            <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                            Remove Variant
                                                                        </button>
                                                                        <input type="hidden" name="archive_variants[{{ $vIndex }}]" :value="isVariantArchived('{{ $vKey }}') ? 1 : 0">
                                                                    </div>
                                                                </div>
                                                            @endforeach

                                                            <div class="mt-6">
                                                                <template x-for="(nv, nvIndex) in newVariants" :key="nv.id">
                                                                    <div class="p-5 bg-white rounded-2xl border border-primary/40 shadow-sm mb-4 relative mt-4">
                                                                        <span class="absolute -top-3 left-5 px-2.5 py-0.5 bg-yellow-100 border border-yellow-300 text-yellow-800 text-[10px] font-bold rounded-lg shadow-sm z-10 pointer-events-none">
                                                                            {{ $isApprovedProduct ? 'New Variant • Pending Approval' : 'New Variant Block' }}
                                                                        </span>
                                                                        
                                                                        <div class="flex flex-col sm:flex-row sm:items-start gap-6 px-2 mt-2">
                                                                            <div class="flex-1">
                                                                                <label class="block text-xs font-bold text-gray-700 mb-1">New {{ $productVariants['title'] ?? 'Variant' }} Name <span class="text-red-500">*</span></label>
                                                                                <input type="text" :name="`new_variant_names[${nv.id}]`" x-model="nv.name" required
                                                                                       placeholder="{{ ($productVariants['title'] ?? 'Variant') . ' Name (e.g. Red)' }}"
                                                                                       :class="isVariantDuplicate(nv.name) ? 'border-red-500 focus:border-red-500 text-red-600' : 'border-border-subtle focus:border-primary'"
                                                                                       class="w-full p-2.5 text-sm rounded-lg border outline-none transition-colors bg-white text-gray-900">
                                                                                <span x-show="isVariantDuplicate(nv.name)" class="text-red-500 text-[10px] font-bold block mt-1 pointer-events-none">Variant name must be unique.</span>
                                                                            </div>
                                                                            <div>
                                                                                <label class="block text-xs font-bold text-gray-700 mb-1">Image <span class="text-red-500">*</span></label>
                                                                                <input type="file" :name="`new_variant_pictures[${nv.id}]`" accept="image/*" required class="w-full p-1.5 text-xs rounded-lg border border-gray-200 cursor-pointer file:cursor-pointer file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-gray-100 file:text-gray-600 file:text-[10px] file:font-bold bg-white focus:outline-none">
                                                                            </div>
                                                                        </div>

                                                                        <hr class="border-gray-100 my-4">

                                                                        @if($priceDependency === 'main')
                                                                            <div class="flex flex-wrap gap-3 px-2">
                                                                                <div class="relative w-32">
                                                                                    <span class="absolute left-2.5 top-2.5 text-gray-400 text-xs font-bold pointer-events-none">₱</span>
                                                                                    <input type="number" step="0.01" min="20" :name="`new_variant_prices[${nv.id}]`" x-model="nv.price" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" class="w-full pl-6 p-2 text-sm rounded-lg border border-border-subtle focus:border-primary outline-none no-spinners text-gray-800 cursor-text" placeholder="Price">
                                                                                </div>
                                                                                <div class="relative w-28">
                                                                                    <input type="number" step="0.01" min="0" :name="`new_variant_weights[${nv.id}]`" x-model="nv.weight" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" class="w-full pr-8 p-2 text-sm rounded-lg border border-border-subtle focus:border-primary outline-none no-spinners text-gray-800 cursor-text" placeholder="Weight">
                                                                                    <span class="absolute right-2.5 top-2.5 text-gray-400 text-xs font-bold pointer-events-none">KG</span>
                                                                                </div>
                                                                                <div class="relative w-24">
                                                                                    <input type="number" min="0" :name="`new_variant_stocks[${nv.id}]`" x-model="nv.stock" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57)" class="w-full p-2 text-sm rounded-lg border border-border-subtle focus:border-primary outline-none no-spinners text-center text-gray-800 cursor-text" placeholder="Stock">
                                                                                </div>
                                                                            </div>
                                                                            <hr class="border-gray-100 my-4">
                                                                        @endif

                                                                        <template x-if="nv.subs !== undefined">
                                                                            <div class="px-2 space-y-3">
                                                                                <div class="flex justify-between items-center mb-2">
                                                                                    <div class="flex items-center gap-2">
                                                                                        <label class="block text-[10px] font-bold text-gray-700 uppercase tracking-widest pointer-events-none">{{ $productVariants['sub_title'] ?? 'Subvariants' }}</label>
                                                                                        <span x-show="nv.name.trim() === ''" class="text-[10px] text-red-400 font-medium italic">(Fill main variant name first)</span>
                                                                                    </div>
                                                                                    <button type="button" @click.stop="nv.name.trim() !== '' ? addSubToNewVariant(nv) : null" :disabled="nv.name.trim() === ''" :class="nv.name.trim() === '' ? 'text-gray-400 cursor-not-allowed opacity-60' : 'text-primary hover:text-primary-dark cursor-pointer'" class="text-xs font-bold transition-colors">+ Add {{ $productVariants['sub_title'] ?? 'Subvariant' }}</button>
                                                                                </div>

                                                                                <template x-for="(sub, subIndex) in nv.subs" :key="sub.id">
                                                                                    <div class="space-y-2 bg-brand-light/10 p-3 rounded-xl border border-brand-light/30 relative">
                                                                                        <div x-show="subIndex > 0 && !sub.isCopied && nv.subs[0] && (nv.subs[0].price || nv.subs[0].weight || nv.subs[0].stock)" x-transition class="p-2 bg-blue-50 border border-blue-200 rounded-lg flex flex-wrap items-center justify-between gap-2 text-xs text-blue-900 shadow-sm">
                                                                                            <div class="flex items-center gap-2">
                                                                                                <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                                                <span>Copy Price, Weight, and Stock from 1st subvariant (<strong x-text="nv.subs[0].name || '1st Subvariant'"></strong>)?</span>
                                                                                            </div>
                                                                                            <button type="button" @click.stop="copyFirstSubInNewVariant(nv, subIndex)" class="px-3 py-1 bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 transition-colors cursor-pointer text-[11px] shadow-sm">Copy</button>
                                                                                        </div>

                                                                                        <div class="flex flex-wrap sm:flex-nowrap items-start gap-3">
                                                                                            <div class="flex-1 min-w-[160px]">
                                                                                                <input type="text" :name="`new_sub_variant_names[${nv.id}][${sub.id}]`" x-model="sub.name" required :disabled="nv.name.trim() === ''"
                                                                                                       placeholder="{{ ($productVariants['sub_title'] ?? 'Subvariant') . ' Name (e.g. 128GB)' }}"
                                                                                                       :class="(isNewVariantSubDuplicate(nv, sub.name) ? 'border-red-500 focus:border-red-500 text-red-600 ' : 'border-border-subtle focus:border-primary ') + (nv.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-800')"
                                                                                                       class="w-full p-2 text-xs rounded-md border outline-none font-medium transition-colors">
                                                                                                <span x-show="isNewVariantSubDuplicate(nv, sub.name)" class="text-red-500 text-[10px] font-bold block mt-1 pointer-events-none">Sub-variant must be unique.</span>
                                                                                            </div>
                                                                                            @if($priceDependency === 'sub')
                                                                                                <div class="flex gap-2">
                                                                                                    <div class="relative w-28">
                                                                                                        <span class="absolute left-2.5 top-2 text-text-muted text-xs font-bold pointer-events-none" :class="nv.name.trim() === '' ? 'opacity-50' : ''">₱</span>
                                                                                                        <input type="number" step="0.01" min="20" :name="`new_sub_variant_prices[${nv.id}][${sub.id}]`" x-model="sub.price" required :disabled="nv.name.trim() === ''" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" :class="nv.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white cursor-text'" class="w-full pl-6 p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners" placeholder="Price">
                                                                                                    </div>
                                                                                                    <div class="relative w-24">
                                                                                                        <input type="number" step="0.01" min="0" :name="`new_sub_variant_weights[${nv.id}][${sub.id}]`" x-model="sub.weight" required :disabled="nv.name.trim() === ''" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" :class="nv.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white cursor-text'" class="w-full pr-7 p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners" placeholder="Weight">
                                                                                                        <span class="absolute right-2 top-2 text-text-muted text-xs font-bold pointer-events-none" :class="nv.name.trim() === '' ? 'opacity-50' : ''">KG</span>
                                                                                                    </div>
                                                                                                    <div class="relative w-20">
                                                                                                        <input type="number" min="0" :name="`new_sub_variant_stocks[${nv.id}][${sub.id}]`" x-model="sub.stock" required :disabled="nv.name.trim() === ''" onkeypress="return (event.charCode >= 48 && event.charCode <= 57)" :class="nv.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white cursor-text'" class="w-full p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners text-center" placeholder="Stock">
                                                                                                    </div>
                                                                                                </div>
                                                                                            @endif
                                                                                            <button type="button" @click.stop="nv.subs.length > 1 ? nv.subs.splice(subIndex, 1) : alert('This product architecture requires variants to have at least one subvariant.')" title="Remove Subvariant" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors cursor-pointer">
                                                                                                <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                                                            </button>
                                                                                        </div>
                                                                                    </div>
                                                                                </template>
                                                                            </div>
                                                                        </template>

                                                                        <hr class="border-gray-100 mt-5 mb-4">
                                                                        <div class="flex justify-end px-2">
                                                                            <button type="button" @click.stop="newVariants.splice(nvIndex, 1)" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-red-600 font-bold transition-colors cursor-pointer">
                                                                                <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                                Drop Entire Variant
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                                <button type="button" @click.stop="addVariant()" class="w-full py-4 bg-primary/5 hover:bg-primary/10 text-primary font-bold rounded-2xl border-2 border-primary/30 border-dashed transition-colors cursor-pointer tracking-wide shadow-sm">
                                                                    + Add New Variant
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                                                    <div>
                                                        <label class="block text-sm font-bold text-gray-700 mb-1">Standard Description</label>
                                                        <textarea name="description" rows="4" class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none custom-scrollbar text-sm cursor-text">{{ $product->description }}</textarea>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-bold text-gray-700 mb-1">Additional Descriptions</label>
                                                        <textarea name="additional_descriptions" rows="3" class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none custom-scrollbar text-sm cursor-text">{{ $product->additional_descriptions }}</textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="p-6 border-t border-border-subtle flex justify-end gap-3 bg-white shrink-0 relative z-[110]">
                                                <button type="button" @click.stop="editModal = false" class="px-6 py-3 rounded-xl font-bold text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer text-sm">Cancel</button>
                                                <button type="submit" :disabled="hasValidationErrors()" :class="hasValidationErrors() ? '!bg-gray-400 opacity-50 cursor-not-allowed' : 'hover:bg-primary-dark cursor-pointer'" class="px-6 py-3 bg-primary text-white rounded-xl shadow-md transition-colors font-bold text-sm">
                                                    @if($approvalStatus === 'Disapproved')
                                                        Save & Resubmit
                                                    @else
                                                        Save Changes
                                                    @endif
                                                </button>
                                            </div>

                                            <div x-show="confirmModal && confirmModal.open" x-cloak
                                                 :class="(confirmModal && confirmModal.open) ? 'pointer-events-auto' : 'pointer-events-none hidden'"
                                                 class="absolute inset-0 z-[150] flex items-center justify-center rounded-3xl overflow-hidden">
                                                <div @click.stop="confirmModal.open = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity cursor-pointer"></div>
                                                <div @click.away="confirmModal.open = false" class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl text-center relative z-10 mx-4 pointer-events-auto cursor-auto">
                                                    <div class="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4 border border-red-100">
                                                        <svg class="w-7 h-7 text-red-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    </div>
                                                    <h3 class="text-lg font-bold text-gray-900 mb-2">Confirm Action</h3>
                                                    <p class="text-sm text-gray-600 mb-8 leading-relaxed font-medium" x-text="confirmModal.message"></p>
                                                    <div class="flex gap-3 justify-center">
                                                        <button type="button" @click.stop="confirmModal.open = false" class="px-5 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition-colors text-sm w-full cursor-pointer">Cancel</button>
                                                        <button type="button" @click.stop="confirmModal.onConfirm(); confirmModal.open = false" class="px-5 py-2.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition-colors text-sm shadow-md w-full cursor-pointer">Proceed</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </template>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center text-text-muted">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                    <p>No products found. Add some to get started.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mb-8 cursor-pointer">
            {{ $products->links() }}
        </div>
    </div>
@endsection