@extends('layouts.admin', ['title' => 'Storkia Admin - Platform, GCash Escrow & Hero Settings'])

@section('content')
@php
    $banners = $banners ?? collect();
    $announcements = $announcements ?? collect();
    $platformGcash = $platformGcash ?? null;
    $existingQrUrl = (!empty($platformGcash?->qr_image_path))
        ? asset('storage/' . $platformGcash->qr_image_path)
        : '';
@endphp

<div x-data="{
    activeSection: '{{ request()->query('section', 'gcash') }}',
    gcashName: @js(old('account_name', $platformGcash->account_name ?? 'Storkia Escrow Administration')),
    gcashNumber: @js(old('gcash_number', $platformGcash->gcash_number ?? '')),
    gcashInstructions: @js(old('instructions', $platformGcash->instructions ?? 'Send the exact checkout total to the official Storkia Admin Escrow GCash account and upload your payment receipt screenshot.')),
    gcashActive: {{ ($platformGcash === null || !empty($platformGcash->is_active)) ? 'true' : 'false' }},
    existingQrUrl: @js($existingQrUrl),
    newQrPreview: '',
    newQrName: '',
    gcashError: '',
    confirmGcashModalOpen: false,
    isSubmittingGcash: false,
    onQrFileChange(e) {
        this.gcashError = '';
        const file = e.target.files[0];
        if (!file) {
            this.newQrPreview = '';
            this.newQrName = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            this.gcashError = 'GCash QR Code image must not exceed 5MB.';
            e.target.value = '';
            this.newQrPreview = '';
            this.newQrName = '';
            return;
        }
        this.newQrName = file.name;
        const reader = new FileReader();
        reader.onload = (ev) => {
            this.newQrPreview = ev.target.result;
        };
        reader.readAsDataURL(file);
    },
    sanitizeGcashNumber() {
        let digits = (this.gcashNumber || '').replace(/\D/g, '');
        if (digits.startsWith('63') && digits.length === 12) {
            digits = '0' + digits.slice(2);
        } else if (digits.startsWith('9') && digits.length === 10) {
            digits = '0' + digits;
        }
        this.gcashNumber = digits.slice(0, 11);
    },
    openGcashConfirmation() {
        this.gcashError = '';
        this.sanitizeGcashNumber();
        if (!this.gcashName || this.gcashName.trim().length < 2) {
            this.gcashError = 'Please enter a valid Admin GCash Account Name (at least 2 characters).';
            return;
        }
        if (!/^09\d{9}$/.test(this.gcashNumber)) {
            this.gcashError = 'Please enter a valid 11-digit Philippine GCash mobile number starting with 09 (e.g., 09171234567).';
            return;
        }
        this.confirmGcashModalOpen = true;
    },
    confirmAndSubmitGcash() {
        this.isSubmittingGcash = true;
        this.$refs.gcashForm.submit();
    }
}" class="max-w-7xl mx-auto space-y-8 select-none">

    <!-- Header & Section Switcher -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Platform, Admin GCash Escrow &amp; Storefront Settings</h1>
            <p class="text-sm text-text-muted mt-1">
                Configure the centralized Storkia Admin GCash Escrow account for all online buyer payments, manage Home Page Hero Banners, and publish Platform Announcements.
            </p>
        </div>

        <div class="inline-flex flex-wrap items-center gap-1.5 p-1.5 bg-surface-subtle border border-border-subtle rounded-2xl">
            <button type="button"
                    @click="activeSection = 'gcash'"
                    :class="activeSection === 'gcash' ? 'bg-primary-dark text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                    class="px-4 py-2 rounded-xl text-xs transition-all cursor-pointer">
                1. Admin GCash Escrow
            </button>
            <button type="button"
                    @click="activeSection = 'banners'"
                    :class="activeSection === 'banners' ? 'bg-primary-dark text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                    class="px-4 py-2 rounded-xl text-xs transition-all cursor-pointer">
                2. Hero Carousel Banners ({{ $banners->count() }})
            </button>
            <button type="button"
                    @click="activeSection = 'announcements'"
                    :class="activeSection === 'announcements' ? 'bg-primary-dark text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main font-semibold'"
                    class="px-4 py-2 rounded-xl text-xs transition-all cursor-pointer">
                3. Announcements &amp; Policies ({{ $announcements->count() }})
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
            @foreach($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <!-- ================================================================= -->
    <!-- SECTION 1: CENTRALIZED ADMIN GCASH ESCROW ACCOUNT                 -->
    <!-- ================================================================= -->
    <div x-show="activeSection === 'gcash'" class="space-y-6">
        <div class="bg-surface rounded-3xl border border-border-subtle p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-border-subtle">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="text-lg font-bold text-primary-dark">Centralized Storkia Admin GCash Escrow Account</h2>
                        @if($platformGcash && $platformGcash->is_active && !empty($platformGcash->gcash_number))
                            <span class="px-3 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                Active at Buyer Checkout (All Stores)
                            </span>
                        @else
                            <span class="px-3 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                Inactive — Buyers Currently Limited to COD
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-text-muted mt-1 leading-relaxed">
                        All Buyer online GCash payments across every store are routed to this centralized Storkia Admin Escrow account. Upon order completion, 90% of the product subtotal is credited to the Seller's E-Wallet and 90%/10% of the shipping fee is credited to the Logistics Hub and Rider E-Wallets.
                    </p>
                </div>
            </div>

            <form x-ref="gcashForm"
                  action="{{ route('admin.settings.gcash.save') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  @submit.prevent="openGcashConfirmation()"
                  class="space-y-5 text-xs">
                @csrf
                <input type="hidden" name="is_active" :value="gcashActive ? 1 : 0">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                            Official Admin GCash Account Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               name="account_name"
                               x-model="gcashName"
                               maxlength="120"
                               required
                               placeholder="e.g. Storkia Escrow Administration"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-sm text-text-main outline-none focus:border-primary">
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                            Admin GCash Mobile Number (11 Digits) <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               name="gcash_number"
                               x-model="gcashNumber"
                               @input="sanitizeGcashNumber()"
                               maxlength="11"
                               required
                               placeholder="09171234567"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-sm font-mono font-bold text-primary-dark outline-none focus:border-primary">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                        Buyer Checkout Payment Instructions
                    </label>
                    <textarea name="instructions"
                              x-model="gcashInstructions"
                              rows="2"
                              maxlength="500"
                              class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                    <div>
                        <label class="block font-bold text-text-muted uppercase tracking-wider mb-1.5">
                            Upload Official Storkia GCash QR Code Image (Optional — Max 5MB)
                        </label>
                        <input type="file"
                               name="qr_image"
                               accept="image/jpeg,image/png,image/jpg,image/webp"
                               @change="onQrFileChange($event)"
                               class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold cursor-pointer">
                        <p class="text-[11px] text-text-muted mt-1">
                            Displayed inside the interactive Cellphone GCash Manual during Buyer Checkout Step 1. Automatically optimized to WebP.
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <template x-if="newQrPreview || existingQrUrl">
                                <img :src="newQrPreview || existingQrUrl"
                                     alt="Admin GCash QR Preview"
                                     class="w-20 h-20 rounded-xl object-contain bg-white border border-border-subtle p-1 shrink-0">
                            </template>
                            <template x-if="!newQrPreview && !existingQrUrl">
                                <div class="w-20 h-20 rounded-xl bg-white border border-border-subtle flex items-center justify-center text-[10px] font-bold text-text-muted text-center p-2 shrink-0">
                                    No QR Uploaded
                                </div>
                            </template>
                            <div>
                                <div class="font-bold text-text-main" x-text="newQrPreview ? 'New QR Image Selected' : (existingQrUrl ? 'Current Admin GCash QR on File' : 'No QR Image Uploaded')"></div>
                                <div class="text-[11px] text-text-muted" x-text="newQrName || (existingQrUrl ? 'Shown to Buyers on Checkout' : 'Buyers can still pay via Account Name & Number')"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-border-subtle">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox"
                               x-model="gcashActive"
                               class="w-4 h-4 rounded text-primary focus:ring-primary">
                        <span class="text-xs font-bold text-text-main">Enable Centralized Online Payment (GCash) across all Storkia checkouts</span>
                    </label>

                    <button type="submit"
                            class="px-6 py-3 rounded-xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                        Review &amp; Save Admin GCash Escrow Settings
                    </button>
                </div>

                <template x-if="gcashError">
                    <p class="text-danger text-xs font-bold" x-text="gcashError"></p>
                </template>
            </form>

            @if(!empty($platformGcash?->qr_image_path))
                <form action="{{ route('admin.settings.gcash.remove-qr') }}" method="POST" class="pt-1">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs font-bold text-danger hover:underline cursor-pointer">
                        Remove Uploaded Admin GCash QR Code Image
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- SECTION 2: STOREFRONT HERO CAROUSEL BANNERS                       -->
    <!-- ================================================================= -->
    <div x-show="activeSection === 'banners'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-5 bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
            <div class="border-b border-border-subtle pb-3">
                <h2 class="text-base font-bold text-primary-dark">Add Storefront Hero Banner Slide</h2>
                <p class="text-xs text-text-muted">Uploaded banner images are automatically converted to WebP for fast storefront loading.</p>
            </div>

            <form action="{{ route('admin.settings.hero.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Headline Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" required maxlength="150" placeholder="e.g. Grab Up to 50% Off Super Deals" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                </div>
                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Subtitle</label>
                    <input type="text" name="subtitle" maxlength="255" placeholder="Fast delivery from verified local merchants..." class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Badge Text</label>
                        <input type="text" name="badge_text" value="Limited Offer" maxlength="60" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="1" min="1" max="99" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">CTA Button Text</label>
                        <input type="text" name="cta_text" value="Shop Now" maxlength="60" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">CTA Target URL</label>
                        <input type="text" name="cta_url" value="/shop#products-grid" maxlength="255" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Banner Image (Max 5MB) <span class="text-danger">*</span></label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold cursor-pointer">
                        Publish Banner Slide
                    </button>
                </div>
            </form>
        </div>

        <div class="lg:col-span-7 bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
            <h2 class="text-base font-bold text-primary-dark border-b border-border-subtle pb-3">Configured Hero Banner Slides</h2>
            @if($banners->isEmpty())
                <div class="p-10 text-center text-xs text-text-muted">No custom hero banners uploaded yet. The storefront is currently displaying the default Storkia slides.</div>
            @else
                <div class="space-y-3">
                    @foreach($banners as $b)
                        <div class="p-4 rounded-2xl border border-border-subtle bg-surface-subtle/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
                            <div class="flex items-center gap-3.5 min-w-0">
                                @if($b->image_path)
                                    <img src="{{ asset('storage/' . $b->image_path) }}" class="w-24 h-14 rounded-xl object-cover border border-border-subtle shrink-0">
                                @endif
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded bg-primary/10 text-primary font-extrabold text-[10px]">#{{ $b->sort_order }}</span>
                                        <span class="font-bold text-sm text-text-main truncate">{{ $b->title }}</span>
                                    </div>
                                    <p class="text-text-muted truncate mt-0.5">{{ $b->subtitle }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <form action="{{ route('admin.settings.hero.toggle', $b->id) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="px-3 py-1.5 rounded-xl font-bold cursor-pointer {{ $b->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $b->is_active ? 'Active' : 'Hidden' }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.settings.hero.delete', $b->id) }}" method="POST" onsubmit="return confirm('Delete this hero banner?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-red-50 text-danger font-bold cursor-pointer">Delete</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- SECTION 3: PLATFORM ANNOUNCEMENTS & POLICY UPDATES                -->
    <!-- ================================================================= -->
    <div x-show="activeSection === 'announcements'" x-cloak class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-5 bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
            <div class="border-b border-border-subtle pb-3">
                <h2 class="text-base font-bold text-primary-dark">Publish Platform Announcement or Policy Update</h2>
                <p class="text-xs text-text-muted">Appears on the Buyer Announcements &amp; Policy Updates center with an unread badge indicator.</p>
            </div>

            <form action="{{ route('admin.settings.announcements.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Announcement Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" required minlength="3" maxlength="150" placeholder="e.g. Updated GCash Escrow & Return Policy" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Category <span class="text-danger">*</span></label>
                        <select name="category" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                            <option value="announcement">General Announcement</option>
                            <option value="policy_update">Policy Update</option>
                            <option value="promotion">Promo / Event</option>
                            <option value="maintenance">System Maintenance</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Target Audience</label>
                        <select name="target_audience" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-bold">
                            <option value="all">All Users &amp; Buyers</option>
                            <option value="buyers">Buyers Only</option>
                            <option value="sellers">Sellers Only</option>
                            <option value="logistics">Logistics Only</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Full Announcement Details <span class="text-danger">*</span></label>
                    <textarea name="body" rows="5" required minlength="10" maxlength="5000" placeholder="Write the full announcement or policy update details..." class="w-full p-3 rounded-xl border border-border-subtle bg-surface"></textarea>
                </div>

                <div>
                    <label class="block font-bold text-text-muted uppercase mb-1">Optional Banner / Graphic Attachment (WebP)</label>
                    <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp" class="w-full p-2 rounded-xl border border-border-subtle bg-surface">
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold cursor-pointer">
                        Publish Announcement
                    </button>
                </div>
            </form>
        </div>

        <div class="lg:col-span-7 bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
            <h2 class="text-base font-bold text-primary-dark border-b border-border-subtle pb-3">Published Announcements Feed</h2>
            @if($announcements->isEmpty())
                <div class="p-10 text-center text-xs text-text-muted">No announcements published yet.</div>
            @else
                <div class="space-y-3">
                    @foreach($announcements as $ann)
                        <div class="p-4 rounded-2xl border border-border-subtle bg-surface-subtle/40 space-y-2 text-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-primary/10 text-primary">
                                        {{ str_replace('_', ' ', $ann->category) }}
                                    </span>
                                    <h3 class="font-bold text-sm text-text-main">{{ $ann->title }}</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <form action="{{ route('admin.settings.announcements.toggle', $ann->id) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="px-3 py-1 rounded-lg font-bold cursor-pointer {{ $ann->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                                            {{ $ann->is_active ? 'Active' : 'Hidden' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.settings.announcements.delete', $ann->id) }}" method="POST" onsubmit="return confirm('Delete this announcement?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="px-3 py-1 rounded-lg bg-red-50 text-danger font-bold cursor-pointer">Delete</button>
                                    </form>
                                </div>
                            </div>
                            <p class="text-text-muted leading-relaxed whitespace-pre-line">{{ $ann->body }}</p>
                            <div class="text-[10px] text-text-muted">
                                Published: {{ \Carbon\Carbon::parse($ann->created_at)->format('M d, Y h:i A') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Confirm Admin GCash Escrow Modal -->
    <template x-teleport="body">
        <div x-show="confirmGcashModalOpen"
             x-cloak
             class="fixed inset-0 z-[205] flex items-center justify-center p-4">
            <div @click="confirmGcashModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Confirm Admin GCash Escrow Account</h3>
                    <button type="button" @click="confirmGcashModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <p class="text-xs text-text-muted leading-relaxed">
                    Please verify the centralized Storkia Admin GCash details below. Buyers selecting Online Payment (GCash) at checkout will transfer their order total to this account:
                </p>

                <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-text-muted">Escrow Account Name:</span>
                        <strong class="text-text-main" x-text="gcashName"></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">GCash Mobile Number:</span>
                        <strong class="font-mono text-primary-dark" x-text="gcashNumber"></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Checkout Status:</span>
                        <strong :class="gcashActive ? 'text-green-700' : 'text-danger'" x-text="gcashActive ? 'Enabled Platform-Wide' : 'Disabled (COD Only)'"></strong>
                    </div>
                </div>

                <div class="flex justify-end gap-2.5 pt-2">
                    <button type="button" @click="confirmGcashModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted text-xs font-bold cursor-pointer">
                        Edit Details
                    </button>
                    <button type="button"
                            @click="confirmAndSubmitGcash()"
                            :disabled="isSubmittingGcash"
                            class="px-5 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold cursor-pointer disabled:opacity-50">
                        <span x-show="!isSubmittingGcash">Confirm &amp; Save Escrow Account</span>
                        <span x-show="isSubmittingGcash" x-cloak>Saving...</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection