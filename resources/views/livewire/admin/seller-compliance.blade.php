@extends('layouts.admin', ['title' => 'Storkia Admin - Seller Compliance'])

@section('content')
@php
    $activeTab = $activeTab ?? strtolower((string) request()->query('status', 'pending'));
    $search = $search ?? request()->query('search', '');
@endphp

<div x-data="{
        detailModalOpen: false,
        disapproveModalOpen: false,
        selectedApproval: null,
        activePreviewImage: '',
        disapprovalType: 'Violation',
        remarks: '',
        openInspectModal(item) {
            this.selectedApproval = item;
            this.activePreviewImage = item.pictures && item.pictures.length > 0 ? item.pictures[0] : '';
            this.detailModalOpen = true;
        },
        openDisapproveModal() {
            this.disapprovalType = 'Violation';
            this.remarks = '';
            this.disapproveModalOpen = true;
        }
     }"
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-4 select-none space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-primary-dark">Seller Compliance</h1>
            <p class="text-text-muted text-xs sm:text-sm mt-1">Review catalog submissions, verify variant details, and enforce listing compliance.</p>
        </div>
        <form method="GET" action="{{ route('admin.compliance.index') }}" class="flex items-center gap-2 w-full md:w-auto">
            <input type="hidden" name="status" value="{{ $activeTab }}">
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Search product or store..."
                   class="w-full md:w-72 py-2.5 px-3.5 text-xs bg-surface border border-border-subtle rounded-xl outline-none focus:border-primary shadow-xs">
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold cursor-pointer">
                Search
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
            @foreach($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <div class="flex border-b border-border-subtle gap-2 sm:gap-6 overflow-x-auto pb-1">
        @foreach (['pending' => 'Pending Review', 'approved' => 'Approved', 'violations' => 'Violations', 'warnings' => 'Warnings'] as $tab => $label)
            <a href="{{ route('admin.compliance.index', array_filter(['status' => $tab, 'search' => $search])) }}"
               class="pb-3 px-2 text-sm font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2 whitespace-nowrap {{ $activeTab === $tab ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <span>{{ $label }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $activeTab === $tab ? 'bg-primary text-white' : 'bg-surface-subtle text-text-muted' }}">
                    {{ $counts[$tab] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs sm:text-sm">
            <thead class="bg-surface-subtle border-b border-border-subtle text-text-muted text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="py-3.5 px-5">Product Details</th>
                    <th class="py-3.5 px-4">Store / Seller</th>
                    <th class="py-3.5 px-4">Base Price</th>
                    <th class="py-3.5 px-4 text-center">Status</th>
                    <th class="py-3.5 px-4">Submitted</th>
                    <th class="py-3.5 px-5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle/70">
                @forelse($approvals as $approval)
                    @php
                        $prod = $approval->product;
                        if (!$prod) continue;
                        $sellerUser = $approval->seller ?? $prod->user;
                        $sellerProfile = $sellerUser?->sellerProfile;

                        $picturesList = (!empty($prod->pictures) && is_array($prod->pictures))
                            ? array_values(array_map(fn ($p) => asset('storage/' . $p), array_filter($prod->pictures)))
                            : [];
                        $firstPic = $picturesList[0] ?? '';

                        $rawVars = $prod->raw_variants ?? $prod->variants;
                        $pendingVariantCount = 0;
                        $pendingSubCount = 0;
                        $normalizedItems = [];

                        if (!empty($rawVars['items']) && is_array($rawVars['items'])) {
                            foreach ($rawVars['items'] as $vIdx => $vItem) {
                                if (!empty($vItem['is_archived'])) continue;
                                $vStatus = $vItem['status'] ?? 'Approved';
                                if ($vStatus === 'Pending') {
                                    $pendingVariantCount++;
                                }
                                $subsNormalized = [];
                                if (!empty($vItem['subs']) && is_array($vItem['subs'])) {
                                    foreach ($vItem['subs'] as $sIdx => $sItem) {
                                        if (!empty($sItem['is_archived'])) continue;
                                        $sStatus = $sItem['status'] ?? 'Approved';
                                        if ($sStatus === 'Pending') {
                                            $pendingSubCount++;
                                        }
                                        $subsNormalized[] = [
                                            'sIdx'   => $sIdx,
                                            'name'   => $sItem['name'] ?? 'Unnamed',
                                            'status' => $sStatus,
                                            'price'  => number_format((float) ($sItem['price'] ?? 0), 2),
                                            'weight' => $sItem['weight'] ?? 0,
                                            'stock'  => (int) ($sItem['stock'] ?? 0),
                                        ];
                                    }
                                }
                                $normalizedItems[] = [
                                    'vIdx'      => $vIdx,
                                    'name'      => $vItem['name'] ?? 'Unnamed Variant',
                                    'status'    => $vStatus,
                                    'image_url' => !empty($vItem['image']) ? asset('storage/' . $vItem['image']) : null,
                                    'price'     => number_format((float) ($vItem['price'] ?? $prod->price ?? 0), 2),
                                    'weight'    => $vItem['weight'] ?? ($prod->weight ?? 0),
                                    'stock'     => (int) ($vItem['stock'] ?? 0),
                                    'subs'      => $subsNormalized,
                                ];
                            }
                        }

                        $isVariantUpdate = $approval->status === 'Approved' && ($prod->has_pending_variants || $pendingVariantCount > 0 || $pendingSubCount > 0);
                        $mainCategory = $prod->category ?: ($sellerProfile?->line_of_business ?? 'Uncategorized');
                        $subCategory = $prod->subcategory ?: 'Uncategorized';

                        $modalPayload = [
                            'approval_id'          => $approval->id,
                            'product_id'           => $prod->id,
                            'product_name'         => $prod->name,
                            'status'               => $approval->status,
                            'disapproval_type'     => $approval->disapproval_type,
                            'remarks'              => $approval->remarks,
                            'is_variant_update'    => $isVariantUpdate,
                            'submitted_at'         => $approval->created_at?->format('M d, Y h:i A'),
                            'store_name'           => $sellerProfile?->business_name ?? 'Unknown Store',
                            'registered_category'  => $sellerProfile?->line_of_business ?? 'N/A',
                            'product_category'     => $mainCategory . ' / ' . $subCategory,
                            'price'                => number_format((float) ($prod->price ?? 0), 2),
                            'discount'             => (float) ($prod->discount ?? 0),
                            'stock_quantity'       => (int) ($prod->stock_quantity ?? 0),
                            'description'          => $prod->description ?: 'No description provided.',
                            'additional_desc'      => $prod->additional_descriptions ?: '',
                            'pictures'             => $picturesList,
                            'variant_title'        => $rawVars['title'] ?? 'Variants',
                            'sub_variant_title'    => $rawVars['sub_title'] ?? null,
                            'variants'             => $normalizedItems,
                            'specs'                => $prod->specifications ? $prod->specifications->map(fn ($s) => ['title' => $s->title, 'description' => $s->description])->values()->all() : [],
                            'approve_url'          => route('admin.compliance.approve', $prod->id),
                            'disapprove_url'       => route('admin.compliance.disapprove', $prod->id),
                            'review_variants_url'  => route('admin.compliance.variants.review', $prod->id),
                        ];
                    @endphp
                    <tr class="hover:bg-brand-light/10 transition-colors">
                        <td class="py-3.5 px-5">
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
                                        <span>{{ $prod->name }}</span>
                                        @if($isVariantUpdate)
                                            <span class="text-[10px] bg-yellow-100 text-yellow-800 border border-yellow-300 px-2 py-0.5 rounded-full font-bold">
                                                @if($pendingVariantCount > 0 && $pendingSubCount > 0)
                                                    {{ $pendingVariantCount }} Variant(s) &amp; {{ $pendingSubCount }} Subvariant(s) Pending
                                                @elseif($pendingVariantCount > 0)
                                                    {{ $pendingVariantCount }} New Variant(s) Pending
                                                @else
                                                    {{ $pendingSubCount }} New Subvariant(s) Pending
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-text-muted mt-0.5">{{ $mainCategory }} &bull; {{ $subCategory }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-4">
                            <p class="font-medium text-text-main">{{ $sellerProfile?->business_name ?? 'Unknown Store' }}</p>
                            <p class="text-xs text-text-muted font-mono">{{ $sellerUser?->email }}</p>
                        </td>
                        <td class="py-3.5 px-4 font-bold text-primary-dark">
                            &#8369;{{ number_format((float) ($prod->price ?? 0), 2) }}
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
                            {{ $approval->updated_at?->format('M d, Y h:i A') }}
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <button type="button"
                                    @click="openInspectModal(@js($modalPayload))"
                                    class="px-3.5 py-1.5 {{ $isVariantUpdate ? 'bg-yellow-500 text-white hover:bg-yellow-600' : 'bg-primary/10 text-primary hover:bg-primary hover:text-white' }} font-bold text-xs rounded-xl transition-colors cursor-pointer">
                                {{ $isVariantUpdate ? 'Review Additions' : 'Inspect Product' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-10 text-center text-text-muted text-xs sm:text-sm">
                            No product compliance records found under {{ strtolower($activeTab) }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $approvals->links() }}
    </div>

    <!-- Product & Variant Compliance Inspection Modal -->
    <template x-teleport="body">
        <div x-show="detailModalOpen && selectedApproval"
             x-cloak
             @keydown.escape.window="detailModalOpen = false"
             class="fixed inset-0 z-[190] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs overflow-y-auto">
            <div @click.away="detailModalOpen = false"
                 class="bg-surface rounded-3xl max-w-5xl w-full p-6 sm:p-8 shadow-2xl border border-border-subtle max-h-[92vh] overflow-y-auto space-y-6 my-auto text-left relative custom-scrollbar">

                <template x-if="selectedApproval?.is_variant_update">
                    <div class="p-4 bg-yellow-50 border border-yellow-300 rounded-2xl text-yellow-900 text-xs sm:text-sm font-bold flex items-center gap-2">
                        <span>This product is already approved, but the seller has submitted new variants or subvariants for compliance review. Pending items remain hidden from the public store until approved.</span>
                    </div>
                </template>

                <div class="flex items-center justify-between border-b border-border-subtle pb-4">
                    <div>
                        <h2 class="text-xl font-bold text-primary-dark" x-text="selectedApproval?.product_name"></h2>
                        <p class="text-xs text-text-muted">
                            Submitted on <span x-text="selectedApproval?.submitted_at"></span> &bull;
                            Product Status: <strong x-text="selectedApproval?.status"></strong>
                        </p>
                    </div>
                    <button type="button" @click="detailModalOpen = false" class="text-text-muted hover:text-danger p-1 cursor-pointer">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs bg-surface-subtle p-4 rounded-2xl border border-border-subtle">
                    <div><span class="text-text-muted">Store Name:</span> <strong class="text-text-main" x-text="selectedApproval?.store_name"></strong></div>
                    <div><span class="text-text-muted">Registered Category:</span> <strong class="text-text-main" x-text="selectedApproval?.registered_category"></strong></div>
                    <div><span class="text-text-muted">Product Category:</span> <strong class="text-text-main" x-text="selectedApproval?.product_category"></strong></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                    <div class="md:col-span-5 space-y-3">
                        <div class="aspect-square bg-surface-subtle rounded-2xl border border-border-subtle flex items-center justify-center p-2 overflow-hidden shadow-inner">
                            <template x-if="activePreviewImage">
                                <img :src="activePreviewImage" class="max-h-full max-w-full object-contain rounded-xl">
                            </template>
                            <template x-if="!activePreviewImage">
                                <span class="text-xs text-text-muted">No Image</span>
                            </template>
                        </div>

                        <template x-if="selectedApproval?.pictures && selectedApproval.pictures.length > 0">
                            <div class="flex gap-2 overflow-x-auto pb-2 custom-scrollbar">
                                <template x-for="(picUrl, pIdx) in selectedApproval.pictures" :key="pIdx">
                                    <button type="button"
                                            @click="activePreviewImage = picUrl"
                                            :class="activePreviewImage === picUrl ? 'border-primary ring-2 ring-primary/20' : 'border-border-subtle hover:border-primary/50'"
                                            class="w-14 h-14 rounded-xl border-2 shrink-0 p-0.5 bg-surface transition-all cursor-pointer">
                                        <img :src="picUrl" class="w-full h-full object-cover rounded-lg">
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="md:col-span-7 space-y-4 text-xs">
                        <div class="p-4 bg-brand-light/10 border border-brand-light/30 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-text-muted text-[11px] block uppercase font-bold tracking-wider">Base Price &amp; Discount</span>
                                <span class="text-2xl font-extrabold text-primary-dark" x-text="'\u20B1' + (selectedApproval?.price || '0.00')"></span>
                                <template x-if="Number(selectedApproval?.discount || 0) > 0">
                                    <span class="ml-2 text-xs font-bold text-red-500" x-text="'(' + selectedApproval.discount + '% OFF)'"></span>
                                </template>
                            </div>
                            <div class="text-right">
                                <span class="text-text-muted text-[11px] block uppercase font-bold tracking-wider">Active Approved Stock</span>
                                <span class="text-lg font-bold text-text-main" x-text="(selectedApproval?.stock_quantity || 0) + ' units'"></span>
                            </div>
                        </div>

                        <template x-if="selectedApproval?.specs && selectedApproval.specs.length > 0">
                            <div>
                                <h4 class="font-bold text-text-main uppercase tracking-wider text-[11px] mb-1">Specifications</h4>
                                <div class="p-3 bg-surface border border-border-subtle rounded-xl text-text-muted text-xs divide-y divide-border-subtle/50">
                                    <template x-for="(spec, sIdx) in selectedApproval.specs" :key="sIdx">
                                        <div class="flex items-center py-1.5">
                                            <span class="font-medium w-1/3" x-text="spec.title"></span>
                                            <span class="text-text-main font-bold flex-1" x-text="spec.description"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div>
                            <h4 class="font-bold text-text-main uppercase tracking-wider text-[11px] mb-1">Standard Description</h4>
                            <p class="p-3 bg-surface border border-border-subtle rounded-xl text-text-muted leading-relaxed whitespace-pre-line" x-text="selectedApproval?.description"></p>
                        </div>

                        <template x-if="selectedApproval?.additional_desc">
                            <div>
                                <h4 class="font-bold text-text-main uppercase tracking-wider text-[11px] mb-1">Additional Descriptions</h4>
                                <p class="p-3 bg-surface border border-border-subtle rounded-xl text-text-muted leading-relaxed whitespace-pre-line" x-text="selectedApproval?.additional_desc"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Variants & Subvariants Breakdown -->
                <template x-if="selectedApproval?.variants && selectedApproval.variants.length > 0">
                    <div class="border-t border-border-subtle pt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted">
                                Variant Breakdown: <span class="text-primary-dark font-extrabold" x-text="selectedApproval.variant_title"></span>
                            </h3>
                            <span class="text-[11px] text-text-muted font-semibold" x-text="selectedApproval.variants.length + ' Active Variant(s)'"></span>
                        </div>

                        <div class="space-y-3">
                            <template x-for="vItem in selectedApproval.variants" :key="vItem.vIdx">
                                <div class="border border-border-subtle rounded-2xl bg-surface overflow-hidden shadow-2xs">
                                    <div class="p-4 bg-surface-subtle flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <template x-if="vItem.image_url">
                                                <button type="button" @click="activePreviewImage = vItem.image_url" class="shrink-0 cursor-pointer">
                                                    <img :src="vItem.image_url" class="w-10 h-10 rounded-xl object-cover border border-border-subtle">
                                                </button>
                                            </template>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-bold text-primary-dark" x-text="vItem.name"></span>
                                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold border"
                                                      :class="vItem.status === 'Pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-300' : (vItem.status === 'Disapproved' ? 'bg-red-100 text-red-700 border-red-200' : 'bg-green-100 text-green-700 border-green-200')"
                                                      x-text="vItem.status"></span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3 text-xs">
                                            <template x-if="!vItem.subs || vItem.subs.length === 0">
                                                <div class="flex items-center gap-4">
                                                    <span>Price: <strong class="text-text-main" x-text="'\u20B1' + vItem.price"></strong></span>
                                                    <span>Weight: <strong class="text-text-main" x-text="vItem.weight + ' KG'"></strong></span>
                                                    <span>Stock: <strong class="text-text-main" x-text="vItem.stock + ' units'"></strong></span>
                                                </div>
                                            </template>

                                            <template x-if="selectedApproval.status === 'Approved' && vItem.status === 'Pending'">
                                                <div class="flex items-center gap-1.5">
                                                    <form :action="selectedApproval.review_variants_url" method="POST" class="m-0">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" :name="`variant_decisions[${vItem.vIdx}]`" value="Approved">
                                                        <button type="submit" class="px-2.5 py-1 bg-green-600 hover:bg-green-700 text-white text-[11px] font-bold rounded-lg cursor-pointer">
                                                            Approve Variant
                                                        </button>
                                                    </form>
                                                    <form :action="selectedApproval.review_variants_url" method="POST" class="m-0">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" :name="`variant_decisions[${vItem.vIdx}]`" value="Disapproved">
                                                        <button type="submit" class="px-2.5 py-1 bg-danger hover:bg-red-700 text-white text-[11px] font-bold rounded-lg cursor-pointer">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <template x-if="vItem.subs && vItem.subs.length > 0">
                                        <div class="p-3 bg-surface border-t border-border-subtle">
                                            <table class="w-full text-left text-xs">
                                                <thead class="text-text-muted border-b border-border-subtle">
                                                    <tr>
                                                        <th class="p-2 font-bold" x-text="selectedApproval.sub_variant_title || 'Sub-variant'"></th>
                                                        <th class="p-2 font-bold">Status</th>
                                                        <th class="p-2 font-bold">Price</th>
                                                        <th class="p-2 font-bold">Weight</th>
                                                        <th class="p-2 font-bold text-right">Stock</th>
                                                        <th class="p-2 font-bold text-right">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-border-subtle/50">
                                                    <template x-for="sub in vItem.subs" :key="sub.sIdx">
                                                        <tr>
                                                            <td class="p-2 font-bold text-text-main" x-text="sub.name"></td>
                                                            <td class="p-2">
                                                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold border"
                                                                      :class="sub.status === 'Pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-300' : (sub.status === 'Disapproved' ? 'bg-red-100 text-red-700 border-red-200' : 'bg-green-100 text-green-700 border-green-200')"
                                                                      x-text="sub.status"></span>
                                                            </td>
                                                            <td class="p-2 font-semibold" x-text="'\u20B1' + sub.price"></td>
                                                            <td class="p-2 text-text-muted" x-text="sub.weight + ' KG'"></td>
                                                            <td class="p-2 text-right font-bold" x-text="sub.stock + ' units'"></td>
                                                            <td class="p-2 text-right">
                                                                <template x-if="selectedApproval.status === 'Approved' && sub.status === 'Pending'">
                                                                    <div class="inline-flex items-center gap-1">
                                                                        <form :action="selectedApproval.review_variants_url" method="POST" class="m-0">
                                                                            @csrf
                                                                            @method('PATCH')
                                                                            <input type="hidden" :name="`sub_variant_decisions[${vItem.vIdx}][${sub.sIdx}]`" value="Approved">
                                                                            <button type="submit" class="px-2 py-1 bg-green-600 text-white text-[10px] font-bold rounded-md cursor-pointer">
                                                                                Approve
                                                                            </button>
                                                                        </form>
                                                                        <form :action="selectedApproval.review_variants_url" method="POST" class="m-0">
                                                                            @csrf
                                                                            @method('PATCH')
                                                                            <input type="hidden" :name="`sub_variant_decisions[${vItem.vIdx}][${sub.sIdx}]`" value="Disapproved">
                                                                            <button type="submit" class="px-2 py-1 bg-danger text-white text-[10px] font-bold rounded-md cursor-pointer">
                                                                                Reject
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </template>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex items-center justify-between border-t border-border-subtle pt-4">
                    <button type="button" @click="detailModalOpen = false" class="px-4 py-2 text-xs font-bold text-text-muted hover:bg-surface-subtle rounded-xl cursor-pointer">
                        Close
                    </button>

                    <template x-if="selectedApproval?.is_variant_update">
                        <div class="flex items-center gap-2.5">
                            <form :action="selectedApproval.review_variants_url" method="POST" class="m-0">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="decision" value="Disapproved">
                                <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-danger hover:bg-red-700 rounded-xl cursor-pointer">
                                    Reject All Pending Additions
                                </button>
                            </form>
                            <form :action="selectedApproval.review_variants_url" method="POST" class="m-0">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="decision" value="Approved">
                                <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl cursor-pointer">
                                    Approve All Pending Additions
                                </button>
                            </form>
                        </div>
                    </template>

                    <template x-if="!selectedApproval?.is_variant_update && selectedApproval?.status === 'Pending'">
                        <div class="flex items-center gap-2.5">
                            <button type="button"
                                    @click="openDisapproveModal()"
                                    class="px-5 py-2.5 text-xs font-bold text-white bg-danger hover:bg-red-700 rounded-xl cursor-pointer">
                                Disapprove Product
                            </button>
                            <form :action="selectedApproval?.approve_url" method="POST" class="m-0">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl cursor-pointer">
                                    Approve &amp; Publish
                                </button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- Disapprove Product Modal -->
    <template x-teleport="body">
        <div x-show="disapproveModalOpen && selectedApproval"
             x-cloak
             class="fixed inset-0 z-[205] flex items-center justify-center p-4 bg-black/65 backdrop-blur-xs">
            <div @click.away="disapproveModalOpen = false"
                 class="bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4 text-left">
                <h3 class="text-base font-bold text-danger font-serif">Disapprove Product</h3>
                <p class="text-xs text-text-muted">Specify whether this issue constitutes a strict violation or listing warning. Feedback is delivered directly to the merchant's portal notification center.</p>

                <form :action="selectedApproval?.disapprove_url" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer block" @click="disapprovalType = 'Violation'">
                            <input type="radio" name="disapproval_type" value="Violation" x-model="disapprovalType" class="sr-only">
                            <div class="p-3 rounded-xl border-2 transition-all text-center"
                                 :class="disapprovalType === 'Violation' ? 'border-danger bg-danger/5' : 'border-border-subtle'">
                                <span class="font-bold text-danger text-xs block">Violation</span>
                                <span class="text-[10px] text-text-muted">Prohibited content</span>
                            </div>
                        </label>
                        <label class="cursor-pointer block" @click="disapprovalType = 'Warning'">
                            <input type="radio" name="disapproval_type" value="Warning" x-model="disapprovalType" class="sr-only">
                            <div class="p-3 rounded-xl border-2 transition-all text-center"
                                 :class="disapprovalType === 'Warning' ? 'border-amber-500 bg-amber-50' : 'border-border-subtle'">
                                <span class="font-bold text-yellow-700 text-xs block">Warning</span>
                                <span class="text-[10px] text-text-muted">Category/Listing error</span>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-main mb-1">Feedback Remarks <span class="text-danger">*</span></label>
                        <textarea name="remarks"
                                  x-model="remarks"
                                  rows="4"
                                  required
                                  minlength="3"
                                  placeholder="Detail the reasons for disapproval so the seller can address them before resubmitting..."
                                  class="w-full p-3 text-xs border border-border-subtle rounded-xl outline-none focus:border-primary"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border-subtle">
                        <button type="button" @click="disapproveModalOpen = false" class="px-4 py-2 font-bold text-text-muted cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 font-bold text-white bg-danger hover:bg-red-700 rounded-xl cursor-pointer">Confirm Disapproval</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
@endsection