<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-primary">Seller Compliance</h1>
            <p class="text-text-muted text-xs sm:text-sm mt-1">Review catalog submissions, verify variant details, and enforce listing compliance.</p>
        </div>
        <div class="w-full md:w-72">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search product or store..." class="w-full py-2 px-3 text-sm bg-surface border border-border-subtle rounded-xl outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-xs">
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-semibold flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="flex border-b border-border-subtle mb-6 gap-2 sm:gap-6 overflow-x-auto pb-1">
        @foreach (['pending' => 'Pending Review', 'approved' => 'Approved', 'violations' => 'Violations', 'warnings' => 'Warnings'] as $tab => $label)
            <button type="button" wire:click="setTab('{{ $tab }}')" class="pb-3 px-2 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 whitespace-nowrap {{ $activeTab === $tab ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <span>{{ $label }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $activeTab === $tab ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted' }}">{{ $counts[$tab] }}</span>
            </button>
        @endforeach
    </div>

    <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-subtle border-b border-border-subtle text-text-muted text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="py-3.5 px-4">Product Details</th>
                    <th class="py-3.5 px-4">Store / Seller</th>
                    <th class="py-3.5 px-4">Base Price</th>
                    <th class="py-3.5 px-4 text-center">Status</th>
                    <th class="py-3.5 px-4">Submitted</th>
                    <th class="py-3.5 px-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle/70">
                @forelse($approvals as $approval)
                    @php
                        $firstPic = (!empty($approval->product?->pictures) && is_array($approval->product->pictures)) 
                            ? asset('storage/' . $approval->product->pictures[0]) 
                            : '';
                        $rawVars = $approval->product?->raw_variants ?? $approval->product?->variants;
                        $pendingVariantCount = 0;
                        $pendingSubCount = 0;
                        if (!empty($rawVars['items']) && is_array($rawVars['items'])) {
                            foreach ($rawVars['items'] as $vItem) {
                                if (!empty($vItem['is_archived'])) continue;
                                if (($vItem['status'] ?? '') === 'Pending') {
                                    $pendingVariantCount++;
                                }
                                if (!empty($vItem['subs']) && is_array($vItem['subs'])) {
                                    foreach ($vItem['subs'] as $sItem) {
                                        if (!empty($sItem['is_archived'])) continue;
                                        if (($sItem['status'] ?? '') === 'Pending') {
                                            $pendingSubCount++;
                                        }
                                    }
                                }
                            }
                        }
                        $isVariantUpdate = $approval->status === 'Approved' && ($approval->product?->has_pending_variants || $pendingVariantCount > 0 || $pendingSubCount > 0);
                    @endphp
                    <tr class="hover:bg-brand-light/10 transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-3">
                                @if($firstPic)
                                    <img src="{{ $firstPic }}" class="w-12 h-12 rounded-xl object-cover border border-border-subtle shrink-0 shadow-xs">
                                @else
                                    <div class="w-12 h-12 rounded-xl bg-surface-subtle border border-border-subtle flex items-center justify-center text-text-muted shrink-0">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                @endif
                                <div>
                                    <div class="font-bold text-text-main leading-tight flex flex-wrap items-center gap-1.5">
                                        <span>{{ $approval->product?->name ?? 'N/A' }}</span>
                                        @if($isVariantUpdate)
                                            <span class="text-[10px] bg-yellow-100 text-yellow-800 border border-yellow-300 px-2 py-0.5 rounded-full font-bold">
                                                @if($pendingVariantCount > 0 && $pendingSubCount > 0)
                                                    {{ $pendingVariantCount }} Variant(s) & {{ $pendingSubCount }} Subvariant(s) Pending
                                                @elseif($pendingVariantCount > 0)
                                                    {{ $pendingVariantCount }} New Variant(s) Pending
                                                @else
                                                    {{ $pendingSubCount }} New Subvariant(s) Pending
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-text-muted mt-0.5">{{ $approval->product?->category ?? 'No Category' }} &bull; {{ $approval->product?->subcategory ?? 'General' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-4">
                            <p class="font-medium text-text-main">{{ $approval->seller?->sellerProfile?->business_name ?? 'Unknown Store' }}</p>
                            <p class="text-xs text-text-muted">{{ $approval->seller?->email }}</p>
                        </td>
                        <td class="py-3.5 px-4 font-bold">
                            ₱{{ number_format((float) ($approval->product?->price ?? 0), 2) }}
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            @if($isVariantUpdate)
                                <div class="flex flex-col items-center gap-1">
                                    <span class="px-2.5 py-0.5 bg-green-100 text-green-700 text-[11px] font-bold rounded-full">Approved</span>
                                    <span class="px-2.5 py-0.5 bg-yellow-100 text-yellow-800 border border-yellow-300 text-[10px] font-bold rounded-full">Pending Additions</span>
                                </div>
                            @elseif($approval->status === 'Approved')
                                <span class="px-2.5 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">Approved</span>
                            @elseif($approval->status === 'Pending')
                                <span class="px-2.5 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded-full">Pending</span>
                            @else
                                <span class="px-2.5 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full">
                                    {{ $approval->disapproval_type ?? 'Disapproved' }}
                                </span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 text-text-muted text-xs">
                            {{ $approval->updated_at->format('M d, Y h:i A') }}
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <button type="button" wire:click="viewDetails({{ $approval->id }})" class="px-3 py-1.5 {{ $isVariantUpdate ? 'bg-yellow-500 text-white hover:bg-yellow-600' : 'bg-primary/10 text-primary hover:bg-primary hover:text-white' }} font-bold text-xs rounded-lg transition-colors cursor-pointer">
                                {{ $isVariantUpdate ? 'Review Additions' : 'Inspect Product' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-text-muted text-sm">
                            No records found under {{ strtolower($activeTab) }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $approvals->links() }}
    </div>

    @if($detailModalOpen && $currentApproval && $currentApproval->product)
        @php
            $prod = $currentApproval->product;
            $variants = $prod->raw_variants ?? $prod->variants;
            $items = $variants['items'] ?? [];
            $hasSubTitle = !empty($variants['sub_title']);
            $isVariantUpdateOnly = $currentApproval->status === 'Approved' && ($prod->has_pending_variants || $prod->hasPendingVariantItems());
        @endphp
        <div class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs overflow-y-auto" wire:keydown.escape="$set('detailModalOpen', false)">
            <div class="bg-surface rounded-3xl max-w-5xl w-full p-6 sm:p-8 shadow-2xl border border-border-subtle max-h-[92vh] overflow-y-auto space-y-6 my-auto text-left relative">
                
                @if($isVariantUpdateOnly)
                    <div class="p-4 bg-yellow-50 border border-yellow-300 rounded-xl text-yellow-900 text-sm font-bold flex items-center gap-2">
                        <svg class="w-5 h-5 shrink-0 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>This product is already approved, but the seller has submitted new variants or subvariants for compliance review. Pending items are hidden from the public store until approved.</span>
                    </div>
                @endif

                <div class="flex items-center justify-between border-b border-border-subtle pb-4">
                    <div>
                        <h2 class="text-xl font-bold text-primary-dark">{{ $prod->name }}</h2>
                        <p class="text-xs text-text-muted">
                            Submitted on {{ $currentApproval->created_at->format('M d, Y h:i A') }} &bull; Product Status: <strong>{{ $currentApproval->status }}</strong>
                        </p>
                    </div>
                    <button type="button" wire:click="$set('detailModalOpen', false)" class="text-text-muted hover:text-danger p-1 cursor-pointer">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs bg-surface-subtle p-4 rounded-2xl border border-border-subtle">
                    <div><span class="text-text-muted">Store Name:</span> <strong class="text-text-main">{{ $currentApproval->seller?->sellerProfile?->business_name ?? 'N/A' }}</strong></div>
                    <div><span class="text-text-muted">Registered Category:</span> <strong class="text-text-main">{{ $currentApproval->seller?->sellerProfile?->line_of_business ?? 'N/A' }}</strong></div>
                    <div><span class="text-text-muted">Product Category:</span> <strong class="text-text-main">{{ $prod->category }} / {{ $prod->subcategory }}</strong></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                    <div class="md:col-span-5 space-y-3">
                        <div class="aspect-square bg-surface-subtle rounded-2xl border border-border-subtle flex items-center justify-center p-2 overflow-hidden shadow-inner">
                            @if($activePreviewImage)
                                <img src="{{ $activePreviewImage }}" class="max-h-full max-w-full object-contain rounded-xl">
                            @else
                                <span class="text-xs text-text-muted">No Image</span>
                            @endif
                        </div>
                        @if(!empty($prod->pictures) && is_array($prod->pictures))
                            <div class="flex gap-2 overflow-x-auto pb-2 custom-scrollbar">
                                @foreach($prod->pictures as $pic)
                                    @php $url = asset('storage/' . $pic); @endphp
                                    <button type="button" wire:click="selectPreviewImage('{{ $url }}')" class="w-14 h-14 rounded-xl border-2 shrink-0 p-0.5 bg-surface transition-all cursor-pointer {{ $activePreviewImage === $url ? 'border-primary ring-2 ring-primary/20' : 'border-border-subtle hover:border-primary/50' }}">
                                        <img src="{{ $url }}" class="w-full h-full object-cover rounded-lg">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="md:col-span-7 space-y-4 text-xs">
                        <div class="p-4 bg-brand-light/10 border border-brand-light/30 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-text-muted text-[11px] block uppercase font-bold tracking-wider">Base Price & Discount</span>
                                <span class="text-2xl font-extrabold text-primary-dark">₱{{ number_format((float) $prod->price, 2) }}</span>
                                @if($prod->discount > 0)
                                    <span class="ml-2 text-xs font-bold text-red-500">({{ $prod->discount }}% OFF)</span>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="text-text-muted text-[11px] block uppercase font-bold tracking-wider">Active Approved Stock</span>
                                <span class="text-lg font-bold text-text-main">{{ $prod->stock_quantity }} units</span>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-bold text-text-main uppercase tracking-wider text-[11px] mb-1">Standard Description</h4>
                            <p class="p-3 bg-surface border border-border-subtle rounded-xl text-text-muted leading-relaxed whitespace-pre-line">{{ $prod->description ?: 'No description provided.' }}</p>
                        </div>
                    </div>
                </div>

                @if(!empty($items))
                    <div class="border-t border-border-subtle pt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">
                                Variant Breakdown: <span class="text-primary-dark font-extrabold">{{ $variants['title'] ?? 'Variants' }}</span>
                                @if($hasSubTitle) &bull; Sub-variant: <span class="text-primary-dark font-extrabold">{{ $variants['sub_title'] }}</span>@endif
                            </h3>
                            <span class="text-[11px] text-text-muted font-semibold">{{ collect($items)->where('is_archived', '!=', true)->count() }} Active Variant(s)</span>
                        </div>

                        <div class="space-y-3">
                            @foreach($items as $vIdx => $vItem)
                                @if(!empty($vItem['is_archived'])) @continue @endif
                                @php
                                    $hasSubs = !empty($vItem['subs']) && is_array($vItem['subs']);
                                    $variantImage = !empty($vItem['image']) ? asset('storage/' . $vItem['image']) : null;
                                    $vStatus = $vItem['status'] ?? 'Approved';
                                    $isPending = $vStatus === 'Pending';
                                    $isDisapproved = $vStatus === 'Disapproved';

                                    $hasPendingSubs = false;
                                    $activeSubsCount = 0;
                                    if ($hasSubs) {
                                        foreach($vItem['subs'] as $sub) {
                                            if (!empty($sub['is_archived'])) continue;
                                            $activeSubsCount++;
                                            if (($sub['status'] ?? 'Approved') === 'Pending') {
                                                $hasPendingSubs = true;
                                            }
                                        }
                                    }
                                @endphp
                                <div x-data="{ expanded: true }" class="border rounded-2xl bg-surface overflow-hidden shadow-xs {{ ($isPending || $hasPendingSubs) ? 'border-yellow-400 bg-yellow-50/20' : ($isDisapproved ? 'border-red-200 bg-red-50/10' : 'border-border-subtle') }}">
                                    <div class="p-4 bg-surface-subtle flex flex-wrap items-center justify-between gap-3 border-b border-border-subtle/60">
                                        <div class="flex items-center gap-3">
                                            @if($variantImage)
                                                <button type="button" wire:click="selectPreviewImage('{{ $variantImage }}')" title="Preview in viewer" class="shrink-0 cursor-pointer">
                                                    <img src="{{ $variantImage }}" class="w-10 h-10 rounded-xl object-cover border border-border-subtle hover:scale-105 transition-transform shadow-xs">
                                                </button>
                                            @endif
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-bold text-primary-dark">{{ $vItem['name'] ?? 'Unnamed Variant' }}</span>
                                                @if($isPending)
                                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 font-bold border border-yellow-300">Pending Variant</span>
                                                @elseif($isDisapproved)
                                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-bold border border-red-200">Rejected</span>
                                                @else
                                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-bold border border-green-200">Approved</span>
                                                @endif

                                                @if($hasPendingSubs && !$isPending)
                                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 font-bold border border-yellow-300">Has Pending Subvariant(s)</span>
                                                @endif

                                                @if($hasSubs)
                                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-primary/10 text-primary font-bold">
                                                        {{ $activeSubsCount }} sub-variants
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-4">
                                            @if(!$hasSubs)
                                                <div class="flex items-center gap-4 text-xs font-medium">
                                                    <span>Price: <strong class="text-text-main">₱{{ number_format((float) ($vItem['price'] ?? 0), 2) }}</strong></span>
                                                    <span>Weight: <strong class="text-text-main">{{ $vItem['weight'] ?? 0 }} KG</strong></span>
                                                    <span>Stock: <strong class="text-text-main">{{ $vItem['stock'] ?? 0 }} units</strong></span>
                                                </div>
                                            @endif

                                            @if($currentApproval->status === 'Approved' && $isPending)
                                                <div class="flex items-center gap-1.5">
                                                    <button type="button" wire:click="approveSingleVariant({{ $prod->id }}, {{ $vIdx }})" class="px-2.5 py-1 bg-green-600 hover:bg-green-700 text-white text-[11px] font-bold rounded-lg transition-colors cursor-pointer">
                                                        Approve Variant
                                                    </button>
                                                    <button type="button" wire:click="disapproveSingleVariant({{ $prod->id }}, {{ $vIdx }})" class="px-2.5 py-1 bg-danger hover:bg-red-700 text-white text-[11px] font-bold rounded-lg transition-colors cursor-pointer">
                                                        Reject
                                                    </button>
                                                </div>
                                            @endif

                                            @if($hasSubs)
                                                <button type="button" @click="expanded = !expanded" class="text-xs font-bold text-primary flex items-center gap-1 hover:underline cursor-pointer">
                                                    <span x-text="expanded ? 'Hide Sub-variants' : 'View Sub-variants'"></span>
                                                    <svg class="w-4 h-4 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    @if($hasSubs)
                                        <div x-show="expanded" x-collapse class="p-3 bg-surface">
                                            <table class="w-full text-left text-xs">
                                                <thead class="text-text-muted border-b border-border-subtle">
                                                    <tr>
                                                        <th class="p-2 font-bold">{{ $variants['sub_title'] ?? 'Sub-variant' }}</th>
                                                        <th class="p-2 font-bold">Status</th>
                                                        <th class="p-2 font-bold">Price</th>
                                                        <th class="p-2 font-bold">Weight</th>
                                                        <th class="p-2 font-bold text-right">Stock Quantity</th>
                                                        @if($currentApproval->status === 'Approved')
                                                            <th class="p-2 font-bold text-right">Review Action</th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-border-subtle/50">
                                                    @foreach($vItem['subs'] as $sIdx => $sub)
                                                        @if(!empty($sub['is_archived'])) @continue @endif
                                                        @php
                                                            $subStatus = $sub['status'] ?? 'Approved';
                                                            $isSubPending = $subStatus === 'Pending';
                                                            $isSubDisapproved = $subStatus === 'Disapproved';
                                                        @endphp
                                                        <tr class="{{ $isSubPending ? 'bg-yellow-50/80' : ($isSubDisapproved ? 'bg-red-50/40' : 'hover:bg-brand-light/10') }}">
                                                            <td class="p-2 font-bold text-text-main">
                                                                {{ $sub['name'] ?? 'Unnamed' }}
                                                            </td>
                                                            <td class="p-2">
                                                                @if($isSubPending)
                                                                    <span class="text-[10px] bg-yellow-100 text-yellow-800 border border-yellow-300 px-2 py-0.5 rounded-full font-bold">Pending Approval</span>
                                                                @elseif($isSubDisapproved)
                                                                    <span class="text-[10px] bg-red-100 text-red-700 border border-red-200 px-2 py-0.5 rounded-full font-bold">Rejected</span>
                                                                @else
                                                                    <span class="text-[10px] bg-green-100 text-green-700 border border-green-200 px-2 py-0.5 rounded-full font-bold">Approved</span>
                                                                @endif
                                                            </td>
                                                            <td class="p-2 text-text-main font-semibold">₱{{ number_format((float) ($sub['price'] ?? 0), 2) }}</td>
                                                            <td class="p-2 text-text-muted">{{ $sub['weight'] ?? 0 }} KG</td>
                                                            <td class="p-2 text-right">
                                                                <span class="px-2 py-0.5 rounded-full font-bold {{ ($sub['stock'] ?? 0) > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                                    {{ $sub['stock'] ?? 0 }} units
                                                                </span>
                                                            </td>
                                                            @if($currentApproval->status === 'Approved')
                                                                <td class="p-2 text-right">
                                                                    @if($isSubPending)
                                                                        <div class="inline-flex items-center gap-1">
                                                                            <button type="button" wire:click="approveSingleSubVariant({{ $prod->id }}, {{ $vIdx }}, {{ $sIdx }})" class="px-2 py-1 bg-green-600 hover:bg-green-700 text-white text-[10px] font-bold rounded-md transition-colors cursor-pointer">
                                                                                Approve
                                                                            </button>
                                                                            <button type="button" wire:click="disapproveSingleSubVariant({{ $prod->id }}, {{ $vIdx }}, {{ $sIdx }})" class="px-2 py-1 bg-danger hover:bg-red-700 text-white text-[10px] font-bold rounded-md transition-colors cursor-pointer">
                                                                                Reject
                                                                            </button>
                                                                        </div>
                                                                    @else
                                                                        <span class="text-[10px] text-text-muted italic">Reviewed</span>
                                                                    @endif
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-between border-t border-border-subtle pt-4">
                    <button type="button" wire:click="$set('detailModalOpen', false)" class="px-4 py-2 text-xs font-bold text-text-muted hover:bg-surface-subtle rounded-xl cursor-pointer">
                        Close
                    </button>

                    @if($isVariantUpdateOnly)
                        <div class="flex gap-2">
                            <button type="button" wire:click="disapprovePendingVariants({{ $prod->id }})" class="px-5 py-2 text-xs font-bold text-white bg-danger hover:bg-red-700 rounded-xl transition-colors cursor-pointer">
                                Reject All Pending Additions
                            </button>
                            <button type="button" wire:click="approvePendingVariants({{ $prod->id }})" class="px-5 py-2 text-xs font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl transition-colors cursor-pointer">
                                Approve All Pending Additions
                            </button>
                        </div>
                    @elseif($currentApproval->status === 'Pending')
                        <div class="flex gap-2">
                            <button type="button" wire:click="promptDisapprove({{ $currentApproval->id }})" class="px-5 py-2 text-xs font-bold text-white bg-danger hover:bg-red-700 rounded-xl transition-colors cursor-pointer">
                                Disapprove Product
                            </button>
                            <button type="button" wire:click="approve({{ $currentApproval->id }})" class="px-5 py-2 text-xs font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl transition-colors cursor-pointer">
                                Approve & Publish
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($disapproveModalOpen)
        <div class="fixed inset-0 z-[170] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4 text-left">
                <h3 class="text-base font-bold text-danger font-serif">Disapprove Product</h3>
                <p class="text-xs text-text-muted">Specify whether this issue constitutes a strict violation or listing warning. Feedback is delivered directly to the merchant's portal notification center.</p>

                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer block">
                            <input type="radio" wire:model.live="disapproval_type" value="Violation" class="peer sr-only">
                            <div class="p-3 rounded-xl border-2 peer-checked:border-danger peer-checked:bg-danger/5 transition-all text-center">
                                <span class="font-bold text-danger text-xs block">Violation</span>
                                <span class="text-[10px] text-text-muted">Prohibited content</span>
                            </div>
                        </label>
                        <label class="cursor-pointer block">
                            <input type="radio" wire:model.live="disapproval_type" value="Warning" class="peer sr-only">
                            <div class="p-3 rounded-xl border-2 peer-checked:border-warning peer-checked:bg-warning/10 transition-all text-center">
                                <span class="font-bold text-yellow-600 text-xs block">Warning</span>
                                <span class="text-[10px] text-text-muted">Category/Listing error</span>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-main mb-1">Feedback Remarks <span class="text-danger">*</span></label>
                        <textarea wire:model="remarks" rows="4" placeholder="Detail the reasons for disapproval so the seller can address them before resubmitting..." class="w-full p-3 text-xs border border-border-subtle rounded-xl outline-none focus:border-primary custom-scrollbar"></textarea>
                        @error('remarks') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                    <button type="button" wire:click="$set('disapproveModalOpen', false)" class="px-4 py-2 text-xs font-bold text-text-muted hover:bg-surface-subtle rounded-xl cursor-pointer">Cancel</button>
                    <button type="button" wire:click="confirmDisapprove" class="px-4 py-2 text-xs font-bold text-white bg-danger hover:bg-red-700 rounded-xl cursor-pointer">Confirm Disapproval</button>
                </div>
            </div>
        </div>
    @endif
</div>