@extends('layouts.admin', ['title' => 'Product Reviews'])

@section('content')
<div class="max-w-7xl mx-auto p-6 font-sans">
    <h1 class="text-3xl font-extrabold text-primary-dark mb-6 tracking-tight font-serif">Pending Product Reviews</h1>
    
    <div class="bg-surface rounded-2xl shadow-sm border border-border-subtle mb-10 overflow-visible">
        <table class="w-full text-left border-collapse relative">
            <thead>
                <tr class="bg-surface-subtle text-text-muted text-[11px] font-bold border-b border-border-subtle uppercase tracking-wider">
                    <th class="p-4 rounded-tl-2xl">Product</th>
                    <th class="p-4">Seller</th>
                    <th class="p-4">Date Submitted</th>
                    <th class="p-4 text-right rounded-tr-2xl w-24">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse($approvals as $approval)
                    @php
                        // Calculate total stock including variants
                        $totalStock = 0;
                        if (isset($approval->product->variants['items']) && is_array($approval->product->variants['items'])) {
                            foreach ($approval->product->variants['items'] as $item) {
                                if (isset($item['subs']) && count($item['subs']) > 0) {
                                    foreach ($item['subs'] as $sub) {
                                        $totalStock += (int)($sub['stock'] ?? 0);
                                    }
                                } else {
                                    $totalStock += (int)($item['stock'] ?? 0);
                                }
                            }
                        } else {
                            $totalStock = (int)($approval->product->stock_quantity ?? 0);
                        }

                        $firstPic = (!empty($approval->product->pictures) && is_array($approval->product->pictures)) 
                            ? asset('storage/' . $approval->product->pictures[0]) 
                            : '';
                    @endphp

                    <tr x-data="{ 
                            viewModal: false, 
                            menuOpen: false, 
                            disapproveModal: false, 
                            activeImage: '{{ $firstPic }}',
                            basePrice: {{ $approval->product->price ?? 0 }},
                            discountPercent: {{ $approval->product->discount ?? 0 }},
                            totalStockCount: {{ $totalStock }},
                            defaultImage: '{{ $firstPic }}',
                            activePrice: {{ $approval->product->price ?? 0 }},
                            activeStock: {{ $totalStock }},
                            selectedMain: null,
                            selectedSub: null
                        }"
                        @click="viewModal = true"
                        class="hover:bg-brand-light/10 text-sm transition-colors cursor-pointer relative group">
                        
                        <td class="p-4 align-middle">
                            <div class="flex items-center gap-3">
                                @if($firstPic)
                                    <img src="{{ $firstPic }}" class="w-12 h-12 rounded-xl object-cover border border-border-subtle shrink-0 shadow-sm group-hover:shadow-md transition-shadow">
                                @else
                                    <div class="w-12 h-12 rounded-xl bg-surface-subtle border border-border-subtle flex items-center justify-center text-text-muted shrink-0">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                @endif
                                <div>
                                    <span class="font-bold text-primary-dark block text-[13px] leading-tight">{{ $approval->product->name ?? 'N/A' }}</span>
                                    <span class="text-[11px] text-text-muted font-medium mt-1 inline-block">{{ $approval->product->category ?? 'No Category' }} &bull; {{ $approval->product->subcategory ?? 'No Subcategory' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 align-middle font-medium text-text-main text-[13px]">{{ $approval->seller->sellerProfile->business_name ?? 'Unknown' }}</td>
                        <td class="p-4 align-middle text-text-muted font-medium text-[13px]">{{ $approval->created_at->setTimezone('Asia/Manila')->format('M d, Y h:i A \P\H\T') }}</td>
                        <td class="p-4 align-middle text-right relative">
                            
                            <!-- 3-Dot Action Menu -->
                            <div class="relative inline-block text-left" @click.stop @click.away="menuOpen = false">
                                <button @click.stop="menuOpen = !menuOpen" class="p-2 text-text-muted hover:text-primary-dark bg-white hover:bg-brand-light/30 border border-transparent hover:border-brand-light/50 rounded-full transition-all shadow-sm focus:outline-none cursor-pointer">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                </button>
                                <div x-show="menuOpen" x-transition.opacity.duration.200ms x-cloak class="absolute right-0 mt-2 w-48 bg-surface rounded-2xl shadow-xl border border-border-subtle z-[60] overflow-hidden">
                                    <button @click.stop="viewModal = true; menuOpen = false" class="w-full text-left px-4 py-3 text-xs font-bold text-text-main hover:bg-brand-light/20 hover:text-primary-dark transition-colors cursor-pointer flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        Inspect Details
                                    </button>
                                    <form action="{{ route('admin.compliance.approve', $approval->id) }}" method="POST" class="m-0">
                                        @csrf @method('PATCH')
                                        <button type="submit" @click.stop class="w-full text-left px-4 py-3 text-xs font-bold text-success hover:bg-success/10 transition-colors cursor-pointer flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                            Approve Product
                                        </button>
                                    </form>
                                    <button @click.stop="disapproveModal = true; menuOpen = false" class="w-full text-left px-4 py-3 text-xs font-bold text-danger hover:bg-danger/10 transition-colors cursor-pointer flex items-center border-t border-border-subtle">
                                        <svg class="w-4 h-4 mr-2 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                                        Disapprove
                                    </button>
                                </div>
                            </div>

                            <!-- View Details Modal (Compact & Eye-Catchy) -->
                            <template x-teleport="body">
                                <div x-show="viewModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 sm:p-6 cursor-default" @click.stop>
                                    <div @click.away="viewModal = false" class="bg-surface rounded-3xl w-full max-w-5xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] relative cursor-default text-left border border-border-subtle">
                                        
                                        <button @click="viewModal = false" class="absolute top-4 right-4 z-30 p-2 bg-surface/90 backdrop-blur border border-border-subtle text-text-muted hover:text-danger rounded-full hover:bg-danger/10 transition-all shadow-sm cursor-pointer">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>

                                        <div class="flex flex-col md:flex-row h-full overflow-y-auto custom-scrollbar">
                                            <!-- Left: Image Section -->
                                            <div class="w-full md:w-5/12 p-6 md:p-8 border-b md:border-b-0 md:border-r border-border-subtle bg-surface">
                                                <div class="md:sticky md:top-0">
                                                    <div class="aspect-square bg-surface-subtle rounded-2xl border border-border-subtle overflow-hidden flex items-center justify-center p-4 shadow-sm mb-4">
                                                        <template x-if="activeImage !== ''">
                                                            <img :src="activeImage" class="w-full h-full object-contain rounded-xl transition-all duration-300">
                                                        </template>
                                                        <template x-if="activeImage === ''">
                                                            <svg class="w-20 h-20 text-border-subtle" fill="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                        </template>
                                                    </div>
                                                    @if(!empty($approval->product->pictures) && is_array($approval->product->pictures))
                                                        <div class="flex gap-3 overflow-x-auto pb-2 custom-scrollbar">
                                                            @foreach($approval->product->pictures as $pic)
                                                                <button @click.stop="activeImage = '{{ asset('storage/' . $pic) }}'"
                                                                        :class="activeImage === '{{ asset('storage/' . $pic) }}' ? 'border-primary ring-2 ring-primary/30 shadow-md' : 'border-border-subtle hover:border-primary/50 opacity-80 hover:opacity-100'"
                                                                        class="w-16 h-16 flex-shrink-0 rounded-xl border-2 transition-all p-1 bg-surface cursor-pointer">
                                                                    <img src="{{ asset('storage/' . $pic) }}" class="w-full h-full object-cover rounded-lg">
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Right: Details Section -->
                                            <div class="w-full md:w-7/12 p-6 md:p-8 flex flex-col bg-surface">
                                                <div class="space-y-6 flex-1 pb-12">

                                                    <!-- Dynamic Price & Title Section -->
                                                    <div>
                                                        <h1 class="text-2xl font-extrabold text-primary-dark mb-1 leading-tight">{{ $approval->product->name ?? 'N/A' }}</h1>
                                                        <p class="text-[11px] text-text-muted font-bold mb-4">{{ $approval->product->category ?? 'No Category' }} &bull; {{ $approval->product->subcategory ?? 'No Subcategory' }}</p>
                                                        
                                                        <!-- Dynamic Price Block -->
                                                        <div class="p-4 bg-surface-subtle border border-border-subtle rounded-2xl shadow-sm">
                                                            <div class="text-[10px] text-text-muted font-bold mb-2 uppercase tracking-widest" x-text="selectedMain !== null ? 'Selected Variant Price & Stock' : 'Base Price & Total Stock'">Base Price & Total Stock</div>
                                                            <div class="flex justify-between items-center">
                                                                <div class="flex items-end gap-3">
                                                                    <template x-if="discountPercent > 0">
                                                                        <div class="flex flex-col">
                                                                            <div class="flex items-center gap-2 mb-1">
                                                                                <span class="text-sm line-through text-text-muted font-medium" x-text="'₱' + activePrice.toFixed(2)"></span>
                                                                                <span class="px-2 py-0.5 bg-primary/10 text-primary border border-primary/20 text-[10px] font-bold rounded-md tracking-wider uppercase" x-text="discountPercent + '% OFF'"></span>
                                                                            </div>
                                                                            <span class="text-3xl font-extrabold text-primary-dark tracking-tight" x-text="'₱' + (activePrice - (activePrice * (discountPercent / 100))).toFixed(2)"></span>
                                                                        </div>
                                                                    </template>
                                                                    <template x-if="discountPercent <= 0">
                                                                        <span class="text-3xl font-extrabold text-primary-dark tracking-tight" x-text="'₱' + activePrice.toFixed(2)"></span>
                                                                    </template>
                                                                </div>
                                                                <div class="text-right">
                                                                    <span class="px-3 py-1.5 rounded-full text-xs font-bold shadow-sm border"
                                                                          :class="activeStock > 0 ? 'bg-success/10 text-success border-success/20' : 'bg-danger/10 text-danger border-danger/20'">
                                                                        <span x-text="activeStock"></span> in stock
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Variants Logic -->
                                                    @if($approval->product->variants && isset($approval->product->variants['items']) && is_array($approval->product->variants['items']))
                                                        <div class="space-y-4">
                                                            <!-- Main Variants (e.g. Color) -->
                                                            <div>
                                                                <h3 class="font-extrabold text-text-main text-[11px] uppercase tracking-widest mb-3">{{ $approval->product->variants['title'] ?? 'Variants' }}</h3>
                                                                <div class="flex flex-wrap gap-2">
                                                                    @foreach($approval->product->variants['items'] as $vIndex => $item)
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
                                                                                        selectedMain = null; selectedSub = null;
                                                                                        activePrice = basePrice; activeStock = totalStockCount; activeImage = defaultImage;
                                                                                    } else {
                                                                                        selectedMain = {{ $vIndex }}; selectedSub = null;
                                                                                        activePrice = {{ $item['price'] ?? $approval->product->price }}; activeStock = {{ $itemStock }};
                                                                                        @if(isset($item['image']) && $item['image'])
                                                                                            activeImage = '{{ asset('storage/' . $item['image']) }}';
                                                                                        @endif
                                                                                    }
                                                                                "
                                                                                :class="selectedMain === {{ $vIndex }} ? 'border-primary text-primary-dark font-bold bg-brand-light/10 ring-1 ring-primary/30' : 'bg-surface text-text-main border-border-subtle hover:border-primary-dark font-medium'"
                                                                                class="px-4 py-1.5 text-xs border rounded-full transition-all cursor-pointer">
                                                                            {{ $item['name'] ?? 'Unnamed' }}
                                                                        </button>
                                                                    @endforeach
                                                                </div>
                                                            </div>

                                                            <!-- Sub Variants (e.g. Size) -->
                                                            @foreach($approval->product->variants['items'] as $vIndex => $item)
                                                                @if(isset($item['subs']) && count($item['subs']) > 0)
                                                                    <div x-show="selectedMain === {{ $vIndex }}" x-collapse class="pt-2">
                                                                        <h4 class="font-extrabold text-text-main text-[11px] uppercase tracking-widest mb-3">{{ $approval->product->variants['sub_title'] ?? 'Sub Variants' }}</h4>
                                                                        <div class="flex flex-wrap gap-2 items-center">
                                                                            @foreach($item['subs'] as $sIndex => $sub)
                                                                                @php $subStock = (int)($sub['stock'] ?? 0); @endphp
                                                                                <button @click.stop="
                                                                                            if (selectedSub === {{ $sIndex }}) {
                                                                                                selectedSub = null; activePrice = {{ $item['price'] ?? $approval->product->price }}; activeStock = {{ $itemStock }};
                                                                                                activeImage = {{ (isset($item['image']) && $item['image']) ? '\'' . asset('storage/' . $item['image']) . '\'' : 'defaultImage' }};
                                                                                            } else {
                                                                                                selectedSub = {{ $sIndex }}; activePrice = {{ $sub['price'] ?? ($item['price'] ?? $approval->product->price) }}; activeStock = {{ $subStock }};
                                                                                                @if(isset($sub['image']) && $sub['image'])
                                                                                                    activeImage = '{{ asset('storage/' . $sub['image']) }}';
                                                                                                @endif
                                                                                            }
                                                                                        "
                                                                                        :class="selectedSub === {{ $sIndex }} ? 'bg-primary border-primary text-surface shadow-md' : 'bg-surface border-border-subtle text-text-main hover:border-primary-dark'"
                                                                                        class="pl-4 pr-1.5 py-1 text-xs font-bold border rounded-full transition-all cursor-pointer flex items-center gap-2">
                                                                                    <span>{{ $sub['name'] ?? '' }}</span>
                                                                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                                                                                          :class="selectedSub === {{ $sIndex }} ? 'bg-white/20 text-surface' : 'bg-surface-subtle text-text-muted'">
                                                                                        {{ $subStock }} left
                                                                                    </span>
                                                                                </button>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </div>

                                                        <!-- Clean Variant Stock Table -->
                                                        <div class="bg-surface rounded-2xl border border-border-subtle shadow-sm overflow-hidden mt-4 mb-4">
                                                            <div class="px-4 py-3 bg-surface-subtle border-b border-border-subtle">
                                                                <h3 class="font-bold text-text-muted text-[10px] uppercase tracking-widest">All Variant Stock Details</h3>
                                                            </div>
                                                            <div class="overflow-x-auto custom-scrollbar max-h-48">
                                                                <table class="w-full text-center text-xs border-collapse bg-surface">
                                                                    <thead class="sticky top-0 bg-surface z-10 border-b border-border-subtle">
                                                                        <tr class="text-text-muted font-bold text-[10px] uppercase tracking-widest">
                                                                            <th class="p-3 text-left">Variant</th>
                                                                            <th class="p-3">Subvariant</th>
                                                                            <th class="p-3">Price</th>
                                                                            <th class="p-3 text-right">Stock</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="divide-y divide-border-subtle">
                                                                        @foreach($approval->product->variants['items'] as $item)
                                                                            @if(isset($item['subs']) && count($item['subs']) > 0)
                                                                                @foreach($item['subs'] as $sub)
                                                                                    <tr class="hover:bg-brand-light/5 transition-colors group">
                                                                                        <td class="p-3 text-left font-bold text-[#5D3140]">{{ $item['name'] ?? 'N/A' }}</td>
                                                                                        <td class="p-3 text-text-main font-bold">{{ $sub['name'] ?? 'N/A' }}</td>
                                                                                        <td class="p-3 text-text-muted font-medium">₱{{ number_format((float)($sub['price'] ?? ($item['price'] ?? $approval->product->price)), 2) }}</td>
                                                                                        <td class="p-3 text-right">
                                                                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ ($sub['stock'] ?? 0) > 0 ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
                                                                                                {{ $sub['stock'] ?? 0 }} units
                                                                                            </span>
                                                                                        </td>
                                                                                    </tr>
                                                                                @endforeach
                                                                            @else
                                                                                <tr class="hover:bg-brand-light/5 transition-colors group">
                                                                                    <td class="p-3 text-left font-bold text-[#5D3140]">{{ $item['name'] ?? 'N/A' }}</td>
                                                                                    <td class="p-3 text-text-muted italic text-[11px]">None</td>
                                                                                    <td class="p-3 text-text-muted font-medium">₱{{ number_format((float)($item['price'] ?? $approval->product->price), 2) }}</td>
                                                                                    <td class="p-3 text-right">
                                                                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ ($item['stock'] ?? 0) > 0 ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
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

                                                    <!-- Generous Bottom Margin to avoid cutoff -->
                                                    <div class="bg-surface p-5 rounded-2xl border border-border-subtle shadow-sm mt-4 mb-8 md:mb-10">
                                                        <h3 class="font-extrabold text-text-muted text-[10px] uppercase tracking-widest mb-2">Product Description</h3>
                                                        <p class="text-xs text-text-main leading-relaxed whitespace-pre-line">{{ $approval->product->description ?: 'No description provided.' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Disapprove UI Modal -->
                            <template x-teleport="body">
                                <div x-show="disapproveModal" x-cloak class="fixed inset-0 z-[130] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 text-left" @click.stop>
                                    <div @click.away="disapproveModal = false" class="bg-surface rounded-3xl w-full max-w-lg shadow-2xl flex flex-col overflow-hidden border border-border-subtle">
                                        <div class="p-6 border-b border-border-subtle bg-surface-subtle flex justify-between items-center">
                                            <div>
                                                <h3 class="text-xl font-extrabold text-primary-dark font-serif tracking-tight">Disapprove Product</h3>
                                                <p class="text-[11px] text-text-muted mt-1 font-medium">Provide feedback for the seller to revise.</p>
                                            </div>
                                            <button @click="disapproveModal = false" class="p-2 text-text-muted hover:text-danger rounded-full hover:bg-danger/10 transition-colors cursor-pointer">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                        <form action="{{ route('admin.compliance.disapprove', $approval->id) }}" method="POST" class="p-6 space-y-5">
                                            @csrf @method('PATCH')
                                            
                                            <!-- Disapproval Type Radio Cards -->
                                            <div>
                                                <label class="block text-[11px] font-bold text-text-main mb-2 uppercase tracking-wider">Select Disapproval Category <span class="text-danger">*</span></label>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
                                                    <label class="cursor-pointer block">
                                                        <input type="radio" name="disapproval_type" value="Violation" class="peer sr-only" required>
                                                        <div class="p-4 rounded-2xl border-2 border-border-subtle peer-checked:border-danger peer-checked:bg-danger/5 hover:bg-surface-subtle transition-all h-full">
                                                            <div class="font-bold text-danger text-sm">Violation</div>
                                                            <div class="text-[11px] text-text-muted mt-1 leading-relaxed">Explicit, offensive, or strictly prohibited content.</div>
                                                        </div>
                                                    </label>
                                                    <label class="cursor-pointer block">
                                                        <input type="radio" name="disapproval_type" value="Warning" class="peer sr-only" required>
                                                        <div class="p-4 rounded-2xl border-2 border-border-subtle peer-checked:border-warning peer-checked:bg-warning/10 hover:bg-surface-subtle transition-all h-full">
                                                            <div class="font-bold text-warning text-sm">Warning</div>
                                                            <div class="text-[11px] text-text-muted mt-1 leading-relaxed">Incorrect category, assigned product mismatch, or listing errors.</div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] font-bold text-text-main mb-2 uppercase tracking-wider">Admin Feedback Message <span class="text-danger">*</span></label>
                                                <textarea name="remarks" rows="4" required class="w-full p-3 rounded-2xl border-2 border-border-subtle focus:border-primary outline-none custom-scrollbar text-sm" placeholder="Detail the issue for the seller to resolve before resubmitting..."></textarea>
                                            </div>

                                            <div class="flex justify-end gap-3 pt-4 border-t border-border-subtle">
                                                <button type="button" @click="disapproveModal = false" class="px-5 py-2 rounded-xl font-bold text-text-muted hover:bg-surface-subtle border border-transparent transition-colors cursor-pointer text-xs">Cancel</button>
                                                <button type="submit" class="px-5 py-2 bg-danger text-surface rounded-xl shadow-md hover:bg-red-600 transition-colors font-bold cursor-pointer text-xs">Confirm Disapproval</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </template>
                            
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-16 text-center">
                            <div class="flex flex-col items-center justify-center text-text-muted">
                                <svg class="w-12 h-12 text-border-subtle mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <p class="font-bold text-sm">No products pending review.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($approvals->hasPages())
            <div class="p-4 border-t border-border-subtle">{{ $approvals->links() }}</div>
        @endif
    </div>
</div>
@endsection