<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - All Products</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style> 
        [x-cloak] { display: none !important; } 
        .custom-scrollbar::-webkit-scrollbar { width: 6px; } 
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } 
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; } 
        .custom-scrollbar:hover::-webkit-scrollbar-thumb { background: #9ca3af; }
        
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body class="m-0 p-0 h-screen w-screen font-sans antialiased text-text-main overflow-hidden bg-gradient-to-b from-surface via-surface to-brand-light/30">
    
    @if(session('success'))
        <div x-data="{ show: true }" 
             x-init="setTimeout(() => show = false, 2000)" 
             x-show="show" 
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 translate-y-[-1rem]"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             x-cloak
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

    <div x-data="{ sidebarOpen: localStorage.getItem('sellerSidebarOpen') !== 'false' }" 
         x-init="$watch('sidebarOpen', val => localStorage.setItem('sellerSidebarOpen', val))" 
         class="h-screen w-full relative flex">
        
        @include('components.seller.sidebar')

        <main class="flex-1 h-screen overflow-y-auto custom-scrollbar relative" x-data="{ showContent: false }" x-init="setTimeout(() => showContent = true, 50)">
            <div class="p-8 lg:p-12" x-show="showContent" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak>
                
                <div class="max-w-7xl mx-auto">
                    
                    <div class="flex justify-between items-center mb-8">
                        <h1 class="text-3xl font-bold text-primary-dark">All Products</h1>
                        <a href="{{ route('seller.products.create') }}" class="px-5 py-2.5 bg-primary text-white rounded-xl shadow-md hover:bg-primary-dark transition-colors font-bold text-sm flex items-center cursor-pointer">
                            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
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
                                    <th class="p-4 font-bold text-right w-24 rounded-tr-2xl">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                @php
                                    // Calculate Total Variant Stock
                                    $totalStock = 0;
                                    if (isset($product->variants['items']) && is_array($product->variants['items'])) {
                                        foreach ($product->variants['items'] as $item) {
                                            if (isset($item['subs']) && count($item['subs']) > 0) {
                                                foreach ($item['subs'] as $sub) {
                                                    $totalStock += (int)($sub['stock'] ?? 0);
                                                }
                                            } else {
                                                $totalStock += (int)($item['stock'] ?? 0);
                                            }
                                        }
                                    } else {
                                        $totalStock = (int)($product->stock_quantity ?? 0);
                                    }
                                @endphp
                                <tr x-data="{ 
                                        viewModal: false, 
                                        editModal: false, 
                                        menuOpen: false,
                                        selectedMain: null,
                                        selectedSub: null,
                                        basePrice: {{ $product->price ?? 0 }},
                                        discountPercent: {{ $product->discount ?? 0 }},
                                        totalStockCount: {{ $totalStock }},
                                        activeStock: {{ $totalStock }},
                                        defaultImage: '{{ (!empty($product->pictures) && is_array($product->pictures)) ? asset('storage/' . $product->pictures[0]) : '' }}',
                                        activeImage: '{{ (!empty($product->pictures) && is_array($product->pictures)) ? asset('storage/' . $product->pictures[0]) : '' }}',
                                        activePrice: {{ $product->price ?? 0 }},
                                        get editDiscountedPrice() {
                                            let p = this.basePrice - (this.basePrice * (this.discountPercent / 100));
                                            return p > 0 ? p.toFixed(2) : 0;
                                        }
                                    }" 
                                    @click="viewModal = true"
                                    class="border-b border-border-subtle hover:bg-brand-light/10 transition-colors text-sm cursor-pointer relative">
                                    
                                    <td class="p-4">
                                        @if($product->pictures && is_array($product->pictures) && count($product->pictures) > 0)
                                            <img src="{{ asset('storage/' . $product->pictures[0]) }}" alt="{{ $product->name }}" class="w-12 h-12 rounded-lg object-cover border border-border-subtle shadow-sm cursor-pointer">
                                        @else
                                            <div class="w-12 h-12 rounded-lg bg-gray-100 border border-border-subtle flex items-center justify-center text-gray-400">
                                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            </div>
                                        @endif
                                    </td>
                                    
                                    <td class="p-4 font-bold text-primary-dark">{{ $product->name }}</td>
                                    
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
                                    
                                    <td class="p-4 text-right">
                                        <div class="relative inline-block text-left" @click.stop @click.away="menuOpen = false">
                                            <button @click.stop="menuOpen = !menuOpen" class="p-2 text-text-muted hover:text-primary hover:bg-brand-light/30 rounded-full transition-colors focus:outline-none cursor-pointer">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                            </button>
                                            
                                            <div x-show="menuOpen" x-transition x-cloak class="absolute right-0 mt-2 w-40 bg-white rounded-xl shadow-xl border border-border-subtle z-[60] overflow-hidden">
                                                <button @click.stop="editModal = true; menuOpen = false" class="w-full text-left px-4 py-3 text-sm font-medium text-text-main hover:bg-brand-light/20 hover:text-primary transition-colors cursor-pointer flex items-center">
                                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                    Update
                                                </button>
                                                <form action="{{ route('seller.products.archive', $product) }}" method="POST" class="m-0">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" @click.stop class="w-full text-left px-4 py-3 text-sm font-medium text-red-500 hover:bg-red-50 transition-colors cursor-pointer flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                                                        Archive
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 1. VIEW MODAL -->
                                    <template x-teleport="body">
                                        <div x-show="viewModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center bg-black/60 backdrop-blur-md p-4 sm:p-8 cursor-default">
                                            <div @click.away="viewModal = false" class="bg-white rounded-3xl w-full max-w-5xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh] relative cursor-default">
                                                <button @click="viewModal = false" class="absolute top-4 right-4 z-10 p-2 bg-white/80 backdrop-blur border border-border-subtle text-text-muted hover:text-red-500 rounded-full hover:bg-red-50 transition-all shadow-sm cursor-pointer">
                                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>

                                                <div class="flex flex-col md:flex-row h-full overflow-y-auto custom-scrollbar bg-surface/30">
                                                    <div class="w-full md:w-1/2 p-6 md:p-8 border-b md:border-b-0 md:border-r border-border-subtle bg-white">
                                                        <div class="aspect-square bg-white rounded-2xl border border-border-subtle overflow-hidden flex items-center justify-center p-4 shadow-inner mb-4">
                                                            <template x-if="activeImage !== ''">
                                                                <img :src="activeImage" alt="Product" class="w-full h-full object-contain rounded-xl transition-all duration-300">
                                                            </template>
                                                            <template x-if="activeImage === ''">
                                                                <svg class="w-24 h-24 text-gray-200" fill="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                            </template>
                                                        </div>

                                                        @if($product->pictures && is_array($product->pictures))
                                                        <div class="flex gap-3 overflow-x-auto pb-2 custom-scrollbar">
                                                            @foreach($product->pictures as $pic)
                                                                <button @click.stop="activeImage = '{{ asset('storage/' . $pic) }}'" 
                                                                        :class="activeImage === '{{ asset('storage/' . $pic) }}' ? 'border-primary ring-2 ring-primary/20' : 'border-border-subtle hover:border-primary/50'"
                                                                        class="w-20 h-20 flex-shrink-0 rounded-xl border-2 transition-all p-1 bg-white cursor-pointer">
                                                                    <img src="{{ asset('storage/' . $pic) }}" class="w-full h-full object-cover rounded-lg">
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                        @endif
                                                    </div>

                                                    <div class="w-full md:w-1/2 p-6 md:p-8 space-y-6 flex flex-col">
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

                                                                <!-- Dynamic Stock Badge -->
                                                                <div class="text-right">
                                                                    <span class="px-3 py-1.5 rounded-full text-xs font-bold shadow-sm border block"
                                                                          :class="activeStock > 0 ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-100 text-red-700 border-red-200'">
                                                                        <span x-text="activeStock"></span> in stock
                                                                    </span>
                                                                    <span class="text-[10px] text-text-muted font-medium mt-1 block" x-text="selectedMain !== null ? 'Selected Variant Stock' : 'Total Stock'"></span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Dynamic Variants & Individual Stock Displays -->
                                                        @if($product->variants && isset($product->variants['items']) && is_array($product->variants['items']))
                                                            <div class="bg-white p-5 rounded-2xl border border-border-subtle shadow-sm space-y-4">
                                                                <div>
                                                                    <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-3">{{ $product->variants['title'] ?? 'Variants' }}</h3>
                                                                    <div class="flex flex-wrap gap-2">
                                                                        @foreach($product->variants['items'] as $vIndex => $item)
                                                                            @php
                                                                                $hasSubs = isset($item['subs']) && count($item['subs']) > 0;
                                                                                $itemStock = 0;
                                                                                if ($hasSubs) {
                                                                                    foreach ($item['subs'] as $sb) { $itemStock += (int)($sb['stock'] ?? 0); }
                                                                                } else {
                                                                                    $itemStock = (int)($item['stock'] ?? 0);
                                                                                }
                                                                            @endphp
                                                                            <button @click.stop="
                                                                                        if (selectedMain === {{ $vIndex }}) {
                                                                                            selectedMain = null;
                                                                                            selectedSub = null;
                                                                                            activePrice = basePrice;
                                                                                            activeStock = totalStockCount;
                                                                                            activeImage = defaultImage;
                                                                                        } else {
                                                                                            selectedMain = {{ $vIndex }};
                                                                                            selectedSub = null;
                                                                                            activePrice = {{ $item['price'] ?? $product->price }};
                                                                                            activeStock = {{ $itemStock }};
                                                                                            @if(isset($item['image']) && $item['image'])
                                                                                                activeImage = '{{ asset('storage/' . $item['image']) }}';
                                                                                            @endif
                                                                                        }
                                                                                    "
                                                                                    :class="selectedMain === {{ $vIndex }} ? 'bg-primary text-white border-primary shadow-md' : 'bg-surface text-text-main border-border-subtle hover:border-primary hover:bg-brand-light/10'"
                                                                                    class="px-4 py-2 text-sm font-bold border-2 rounded-xl transition-all cursor-pointer flex items-center gap-2">
                                                                                <span>{{ $item['name'] ?? 'Unnamed' }}</span>
                                                                                <span class="text-xs px-1.5 py-0.5 rounded-md font-semibold"
                                                                                      :class="selectedMain === {{ $vIndex }} ? 'bg-white/20 text-white' : 'bg-gray-100 text-text-muted'">
                                                                                    {{ $itemStock }} left
                                                                                </span>
                                                                            </button>
                                                                        @endforeach
                                                                    </div>
                                                                </div>

                                                                @foreach($product->variants['items'] as $vIndex => $item)
                                                                    @if(isset($item['subs']) && count($item['subs']) > 0)
                                                                    <div x-show="selectedMain === {{ $vIndex }}" x-collapse class="pt-4 border-t border-border-subtle">
                                                                        <h4 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-3">{{ $product->variants['sub_title'] ?? 'Sub Variants' }}</h4>
                                                                        <div class="flex flex-wrap gap-2">
                                                                            @foreach($item['subs'] as $sIndex => $sub)
                                                                                @php $subStock = (int)($sub['stock'] ?? 0); @endphp
                                                                                <button @click.stop="
                                                                                            if (selectedSub === {{ $sIndex }}) {
                                                                                                selectedSub = null;
                                                                                                activePrice = {{ $item['price'] ?? $product->price }};
                                                                                                activeStock = {{ $itemStock }};
                                                                                                @if(isset($item['image']) && $item['image'])
                                                                                                    activeImage = '{{ asset('storage/' . $item['image']) }}';
                                                                                                @else
                                                                                                    activeImage = defaultImage;
                                                                                                @endif
                                                                                            } else {
                                                                                                selectedSub = {{ $sIndex }}; 
                                                                                                activePrice = {{ $sub['price'] ?? ($item['price'] ?? $product->price) }};
                                                                                                activeStock = {{ $subStock }};
                                                                                                @if(isset($sub['image']) && $sub['image'])
                                                                                                    activeImage = '{{ asset('storage/' . $sub['image']) }}';
                                                                                                @endif
                                                                                            }
                                                                                        " 
                                                                                        :class="selectedSub === {{ $sIndex }} ? 'bg-primary text-white border-primary shadow-md' : 'bg-surface text-text-main border-border-subtle hover:border-primary hover:bg-brand-light/10'"
                                                                                        class="px-4 py-2 border-2 rounded-xl text-sm font-bold transition-all cursor-pointer flex items-center gap-2">
                                                                                    <span>{{ $sub['name'] ?? '' }}</span>
                                                                                    <span class="text-xs px-1.5 py-0.5 rounded-md font-semibold"
                                                                                          :class="selectedSub === {{ $sIndex }} ? 'bg-white/20 text-white' : 'bg-gray-100 text-text-muted'">
                                                                                        {{ $subStock }} left
                                                                                    </span>
                                                                                </button>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                    @endif
                                                                @endforeach
                                                            </div>

                                                            <!-- Itemized Variant & Subvariant Stock Overview Table -->
                                                            <div class="bg-white p-5 rounded-2xl border border-border-subtle shadow-sm space-y-3">
                                                                <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider">All Variant Stock Details</h3>
                                                                <div class="overflow-x-auto custom-scrollbar">
                                                                    <table class="w-full text-left text-xs border-collapse">
                                                                        <thead>
                                                                            <tr class="bg-gray-50 border-b border-border-subtle text-text-muted font-bold">
                                                                                <th class="p-2">Variant</th>
                                                                                <th class="p-2">Subvariant</th>
                                                                                <th class="p-2">Price</th>
                                                                                <th class="p-2 text-right">Stock</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody class="divide-y divide-border-subtle">
                                                                            @foreach($product->variants['items'] as $item)
                                                                                @if(isset($item['subs']) && count($item['subs']) > 0)
                                                                                    @foreach($item['subs'] as $sub)
                                                                                        <tr class="hover:bg-brand-light/10">
                                                                                            <td class="p-2 font-medium text-primary-dark">{{ $item['name'] ?? 'N/A' }}</td>
                                                                                            <td class="p-2 text-text-main font-semibold">{{ $sub['name'] ?? 'N/A' }}</td>
                                                                                            <td class="p-2">₱{{ number_format((float)($sub['price'] ?? ($item['price'] ?? $product->price)), 2) }}</td>
                                                                                            <td class="p-2 text-right">
                                                                                                <span class="px-2 py-0.5 rounded-full font-bold {{ ($sub['stock'] ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                                                                    {{ $sub['stock'] ?? 0 }} units
                                                                                                </span>
                                                                                            </td>
                                                                                        </tr>
                                                                                    @endforeach
                                                                                @else
                                                                                    <tr class="hover:bg-brand-light/10">
                                                                                        <td class="p-2 font-medium text-primary-dark">{{ $item['name'] ?? 'N/A' }}</td>
                                                                                        <td class="p-2 text-text-muted italic">None</td>
                                                                                        <td class="p-2">₱{{ number_format((float)($item['price'] ?? $product->price), 2) }}</td>
                                                                                        <td class="p-2 text-right">
                                                                                            <span class="px-2 py-0.5 rounded-full font-bold {{ ($item['stock'] ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                                                                {{ $item['stock'] ?? 0 }} units
                                                                                            </span>
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
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- 2. EDIT PRODUCT MODAL -->
                                    <template x-teleport="body">
                                        <div x-show="editModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center bg-black/60 backdrop-blur-md p-4 cursor-default">
                                            <div @click.away="editModal = false" class="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col cursor-default">
                                                
                                                <div class="flex justify-between items-center p-6 border-b border-border-subtle bg-surface">
                                                    <h2 class="text-xl font-bold text-primary-dark">Update Product</h2>
                                                    <button @click.stop="editModal = false" class="p-2 text-text-muted hover:text-red-500 rounded-full hover:bg-red-50 transition-colors cursor-pointer">
                                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </div>

                                                <form action="{{ route('seller.products.update', $product->id) }}" method="POST" @click.stop class="p-6 space-y-5 overflow-y-auto max-h-[75vh] custom-scrollbar">
                                                    @csrf @method('PUT')
                                                    
                                                    <div>
                                                        <label class="block text-sm font-bold text-text-muted mb-1">Product Name</label>
                                                        <input type="text" name="name" value="{{ $product->name }}" required class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none">
                                                    </div>

                                                    <div class="grid grid-cols-1 gap-4">
                                                        <div>
                                                            <label class="block text-sm font-bold text-text-muted mb-1">Set Discount (%)</label>
                                                            <div class="relative">
                                                                <input type="number" step="0.01" min="0" max="100" name="discount" x-model="discountPercent" class="w-full pr-8 p-3 rounded-xl border border-border-subtle focus:border-primary outline-none">
                                                                <span class="absolute right-3 top-3 text-text-muted font-bold">%</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="p-4 bg-brand-light/10 border border-brand-light/30 rounded-xl shadow-inner">
                                                        <div class="flex justify-between items-center text-sm mb-1">
                                                            <span class="text-text-muted">Base Price:</span>
                                                            <span class="font-bold">₱<span x-text="basePrice"></span></span>
                                                        </div>
                                                        <div class="flex justify-between items-center">
                                                            <span class="text-primary font-bold">New Discounted Price:</span>
                                                            <span class="text-xl font-bold text-red-500">₱<span x-text="editDiscountedPrice"></span></span>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-bold text-text-muted mb-1">Description</label>
                                                        <textarea name="description" rows="3" class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none custom-scrollbar">{{ $product->description }}</textarea>
                                                    </div>

                                                    <div class="pt-4 border-t border-border-subtle flex justify-end gap-3">
                                                        <button type="button" @click.stop="editModal = false" class="px-5 py-2.5 rounded-xl font-bold text-text-muted hover:bg-gray-100 transition-colors cursor-pointer">Cancel</button>
                                                        <button type="submit" class="px-5 py-2.5 bg-primary text-white rounded-xl shadow-md hover:bg-primary-dark transition-colors font-bold cursor-pointer">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="p-12 text-center text-text-muted">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
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
            </div>
        </main>
    </div>
</body>
</html>