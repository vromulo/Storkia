@extends('layouts.app')

@section('content')
    @php
        $productCardComponent = view()->exists('components.storefront.seller-product-card')
            ? 'storefront.seller-product-card'
            : (view()->exists('components.buyer.seller-product-card')
                ? 'buyer.seller-product-card'
                : 'seller-product-card');

        $productsJson = $products->map(function ($product) {
            $effectivePrice = $product->discount > 0
                ? (float) $product->price - ((float) $product->price * ((float) $product->discount / 100))
                : (float) $product->price;

            return [
                'id'        => $product->id,
                'name'      => strtolower($product->name ?? ''),
                'category'  => $product->resolved_sub_category ?? '',
                'price'     => round($effectivePrice, 2),
                'timestamp' => $product->created_at ? $product->created_at->timestamp : 0,
            ];
        })->values();
    @endphp

    <div class="relative bg-surface-subtle font-sans antialiased text-text-main overflow-x-hidden min-h-screen pb-20"
         x-data="sellerStorefront({
             products: {{ Js::from($productsJson) }},
             initialFollowers: {{ (int) $stats['followers'] }}
         })">

        <!-- Seller Profile Header (No Cover Photo) -->
        <div class="bg-white border-b border-border-subtle/80 shadow-2xs">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-7 sm:py-10">
                <div class="flex flex-col lg:flex-row items-center lg:items-start gap-6 sm:gap-8">
                    
                    <!-- Profile Logo Picture -->
                    <div class="relative w-28 h-28 sm:w-36 sm:h-36 rounded-full p-1 bg-gradient-to-br from-primary via-secondary to-primary/40 shadow-md flex-shrink-0">
                        <div class="w-full h-full bg-white rounded-full p-1 overflow-hidden flex items-center justify-center">
                            @if($seller->logo_path)
                                <img src="{{ Storage::url($seller->logo_path) }}" alt="{{ $seller->business_name }}" class="w-full h-full rounded-full object-cover">
                            @else
                                <div class="w-full h-full rounded-full bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                                    <span class="text-4xl sm:text-5xl font-extrabold text-primary">
                                        {{ strtoupper(substr($seller->business_name, 0, 1)) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <span class="absolute bottom-1.5 right-1.5 w-4 h-4 rounded-full bg-success ring-4 ring-white" title="Verified Active Store"></span>
                    </div>

                    <!-- Seller Details & Stats Beside Profile Picture -->
                    <div class="flex-1 w-full text-center lg:text-left">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pb-5 border-b border-border-subtle/70">
                            <div>
                                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-text-main tracking-tight leading-tight">
                                    {{ $seller->business_name }}
                                </h1>
                                <p class="text-sm sm:text-base font-normal text-text-muted mt-1">
                                    {{ $seller->line_of_business }}
                                </p>
                            </div>

                            <!-- Store Action Buttons -->
                            <div class="flex items-center gap-2.5 sm:gap-3">
                                <button type="button"
                                        @click="toggleFollow()"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-300 cursor-pointer shadow-2xs"
                                        :class="isFollowing
                                            ? 'bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/40'
                                            : 'bg-primary text-white hover:bg-primary-dark shadow-md shadow-primary/20'">
                                    <svg x-show="!isFollowing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <svg x-show="isFollowing" x-cloak class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span x-text="isFollowing ? 'Following' : 'Follow'"></span>
                                </button>

                                @auth
                                    <a href="{{ route('user.messages.buyer-messages', ['contact' => $seller->user_id]) }}"
                                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-surface hover:bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/40 transition-all duration-300 shadow-2xs">
                                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                        <span>Chat Now</span>
                                    </a>
                                @else
                                    <a href="{{ route('login') }}"
                                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-surface hover:bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/40 transition-all duration-300 shadow-2xs">
                                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                        <span>Chat Now</span>
                                    </a>
                                @endauth
                            </div>
                        </div>

                        <!-- Store Metrics Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-y-4 gap-x-6 pt-5 text-left">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Chat Response Rate</p>
                                    <p class="text-xs sm:text-sm font-bold text-primary">{{ $stats['chat_response_rate'] }}%</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Following</p>
                                    <p class="text-xs sm:text-sm font-bold text-text-main">{{ number_format($stats['following']) }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Followers</p>
                                    <p class="text-xs sm:text-sm font-bold text-text-main" x-text="followersCount.toLocaleString()"></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Ratings</p>
                                    <p class="text-xs sm:text-sm font-bold text-primary">{{ $stats['ratings'] }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Joined</p>
                                    <p class="text-xs sm:text-sm font-bold text-text-main truncate">{{ $stats['joined'] }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-text-muted font-medium truncate">Products</p>
                                    <p class="text-xs sm:text-sm font-bold text-primary">{{ number_format($stats['products_count']) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Storefront Filter Console & Products Section -->
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- Filter Bar -->
            <div class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 p-5 sm:p-7 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-4 items-end">

                    <!-- Store Search Bar -->
                    <div class="xl:col-span-4">
                        <label class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Search Store</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-primary">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text"
                                   x-model.debounce.150ms="searchQuery"
                                   placeholder="Search product of this store"
                                   class="w-full bg-surface hover:bg-surface-subtle focus:bg-white text-text-main text-sm font-medium rounded-2xl pl-11 pr-10 py-3 border border-border-subtle focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none transition-all shadow-2xs">
                            <button type="button"
                                    x-show="searchQuery.length > 0"
                                    x-cloak
                                    @click="searchQuery = ''"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-text-muted hover:text-primary cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Sub Category Dropdown -->
                    <div class="xl:col-span-3 relative" x-data="{ open: false }">
                        <label class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Sub Category</label>
                        <div @click="open = !open" @click.away="open = false"
                             class="relative w-full bg-surface hover:bg-surface-subtle text-text-main text-sm font-bold rounded-2xl pl-11 pr-10 py-3 cursor-pointer shadow-2xs transition-all flex items-center justify-between select-none border"
                             :class="open ? 'ring-2 ring-primary/30 border-primary' : 'border-border-subtle'">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-primary">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                            </div>
                            <span class="truncate" x-text="activeCategory ? activeCategory : 'All Sub Categories'"></span>
                            <div class="absolute inset-y-0 right-0 flex items-center px-3.5 text-text-muted">
                                <svg class="w-4 h-4 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>

                        <div x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-2"
                             class="absolute z-50 w-full mt-2 bg-white border border-border-subtle rounded-2xl shadow-xl py-1.5 max-h-72 overflow-y-auto modern-scrollbar">
                            <button type="button"
                                    @click="activeCategory = ''; open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="activeCategory === '' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                All Sub Categories
                            </button>
                            @foreach($subCategories as $cat)
                                <button type="button"
                                        @click="activeCategory = '{{ addslashes($cat) }}'; open = false"
                                        class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                        :class="activeCategory === '{{ addslashes($cat) }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                    {{ $cat }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Price Filter Dropdown -->
                    <div class="xl:col-span-2 relative" x-data="{ open: false }">
                        <label class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Price</label>
                        <div @click="open = !open" @click.away="open = false"
                             class="relative w-full bg-surface hover:bg-surface-subtle text-text-main text-sm font-bold rounded-2xl pl-10 pr-9 py-3 cursor-pointer shadow-2xs transition-all flex items-center justify-between select-none border"
                             :class="open ? 'ring-2 ring-primary/30 border-primary' : 'border-border-subtle'">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-primary">
                                <span class="text-sm font-extrabold">₱</span>
                            </div>
                            <span class="truncate" x-text="priceSortLabel"></span>
                            <div class="absolute inset-y-0 right-0 flex items-center px-3 text-text-muted">
                                <svg class="w-4 h-4 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>

                        <div x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-2"
                             class="absolute z-50 w-full mt-2 bg-white border border-border-subtle rounded-2xl shadow-xl py-1.5 max-h-60 overflow-y-auto modern-scrollbar">
                            <button type="button" @click="setSort(''); open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="!['price_asc', 'price_desc'].includes(sortBy) ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                Default Price
                            </button>
                            <button type="button" @click="setSort('price_asc'); open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="sortBy === 'price_asc' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                Price: Low to High
                            </button>
                            <button type="button" @click="setSort('price_desc'); open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="sortBy === 'price_desc' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                Price: High to Low
                            </button>
                        </div>
                    </div>

                    <!-- Date Added (New / Old) & Alphabetical (A-Z / Z-A) Dropdown -->
                    <div class="xl:col-span-3 relative" x-data="{ open: false }">
                        <label class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Sort By (Date & Name)</label>
                        <div @click="open = !open" @click.away="open = false"
                             class="relative w-full bg-surface hover:bg-surface-subtle text-text-main text-sm font-bold rounded-2xl pl-11 pr-10 py-3 cursor-pointer shadow-2xs transition-all flex items-center justify-between select-none border"
                             :class="open ? 'ring-2 ring-primary/30 border-primary' : 'border-border-subtle'">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-primary">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path></svg>
                            </div>
                            <span class="truncate" x-text="generalSortLabel"></span>
                            <div class="absolute inset-y-0 right-0 flex items-center px-3.5 text-text-muted">
                                <svg class="w-4 h-4 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>

                        <div x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-2"
                             class="absolute z-50 w-full mt-2 bg-white border border-border-subtle rounded-2xl shadow-xl py-1.5 max-h-72 overflow-y-auto modern-scrollbar">
                            <button type="button" @click="setSort('newest'); open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="sortBy === 'newest' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                Newest Added
                            </button>
                            <button type="button" @click="setSort('oldest'); open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="sortBy === 'oldest' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                Oldest Added
                            </button>
                            <button type="button" @click="setSort('az'); open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="sortBy === 'az' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                Name: A to Z
                            </button>
                            <button type="button" @click="setSort('za'); open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                    :class="sortBy === 'za' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                Name: Z to A
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Active Filter Pills -->
            <div x-show="hasActiveFilters" x-cloak class="flex flex-wrap items-center gap-2.5 mb-8 text-xs sm:text-sm animate-fade-in-up">
                <span class="text-text-muted font-bold uppercase tracking-wider text-xs">Active Filters:</span>

                <span x-show="searchQuery.trim() !== ''" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary font-bold">
                    Search: "<span x-text="searchQuery"></span>"
                    <button type="button" @click="searchQuery = ''" class="hover:bg-primary/20 rounded-full p-0.5 transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </span>

                <span x-show="activeCategory !== ''" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary font-bold">
                    Category: <span x-text="activeCategory"></span>
                    <button type="button" @click="activeCategory = ''" class="hover:bg-primary/20 rounded-full p-0.5 transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </span>

                <span x-show="sortBy !== 'newest'" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary font-bold">
                    Sort: <span x-text="activeSortBadgeLabel"></span>
                    <button type="button" @click="sortBy = 'newest'" class="hover:bg-primary/20 rounded-full p-0.5 transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </span>

                <button type="button" @click="resetFilters()" class="text-text-muted hover:text-primary font-bold underline underline-offset-4 ml-2 transition-colors cursor-pointer">
                    Clear All
                </button>
            </div>

            <!-- Products Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6 min-h-[360px]">
                @foreach($products as $product)
                    <div x-show="isVisible({{ $product->id }})"
                         :style="{ order: getOrder({{ $product->id }}) }"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="bg-white rounded-2xl p-3 border border-border-subtle/60 hover:border-primary/30 hover:shadow-md transition-all duration-300 flex flex-col">
                        <x-dynamic-component :component="$productCardComponent" :product="$product" />
                    </div>
                @endforeach

                <!-- Empty State -->
                <div x-show="visibleCount === 0" x-cloak class="col-span-full flex flex-col items-center justify-center py-20 px-4 text-center bg-white rounded-3xl border border-border-subtle shadow-xs">
                    <div class="w-16 h-16 bg-surface-subtle rounded-2xl flex items-center justify-center mb-4 text-text-muted/60 border border-border-subtle">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-extrabold text-text-main mb-1">No Products Found</h3>
                    <p class="text-text-muted font-medium text-xs sm:text-sm max-w-sm mx-auto mb-6">
                        We couldn't find any products in this store matching your current filters.
                    </p>
                    <button type="button" @click="resetFilters()" class="px-6 py-2.5 bg-primary text-white rounded-full font-bold text-xs hover:bg-primary-dark transition-all duration-300 shadow-md shadow-primary/20 cursor-pointer">
                        Reset Filters
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('sellerStorefront', ({ products, initialFollowers }) => ({
                products: products || [],
                searchQuery: '',
                activeCategory: '',
                sortBy: 'newest',
                isFollowing: false,
                followersCount: initialFollowers || 0,

                toggleFollow() {
                    this.isFollowing = !this.isFollowing;
                    this.followersCount += this.isFollowing ? 1 : -1;
                },

                get hasActiveFilters() {
                    return this.searchQuery.trim() !== '' || this.activeCategory !== '' || this.sortBy !== 'newest';
                },

                get priceSortLabel() {
                    if (this.sortBy === 'price_asc') return 'Low to High';
                    if (this.sortBy === 'price_desc') return 'High to Low';
                    return 'Default Price';
                },

                get generalSortLabel() {
                    const labels = {
                        newest: 'Newest Added',
                        oldest: 'Oldest Added',
                        az: 'Name: A to Z',
                        za: 'Name: Z to A',
                        price_asc: 'Newest Added',
                        price_desc: 'Newest Added'
                    };
                    return labels[this.sortBy] || 'Newest Added';
                },

                get activeSortBadgeLabel() {
                    const labels = {
                        newest: 'Newest Added',
                        oldest: 'Oldest Added',
                        az: 'Name: A to Z',
                        za: 'Name: Z to A',
                        price_asc: 'Price: Low to High',
                        price_desc: 'Price: High to Low'
                    };
                    return labels[this.sortBy] || 'Newest Added';
                },

                get filteredAndSortedIds() {
                    const query = this.searchQuery.trim().toLowerCase();
                    const category = this.activeCategory;

                    let filtered = this.products.filter(item => {
                        const matchSearch = !query || item.name.includes(query);
                        const matchCategory = !category || item.category === category;
                        return matchSearch && matchCategory;
                    });

                    filtered.sort((a, b) => {
                        switch (this.sortBy) {
                            case 'price_asc':
                                return a.price - b.price;
                            case 'price_desc':
                                return b.price - a.price;
                            case 'oldest':
                                return a.timestamp - b.timestamp;
                            case 'az':
                                return a.name.localeCompare(b.name);
                            case 'za':
                                return b.name.localeCompare(a.name);
                            case 'newest':
                            default:
                                return b.timestamp - a.timestamp;
                        }
                    });

                    return filtered.map(item => item.id);
                },

                get visibleCount() {
                    return this.filteredAndSortedIds.length;
                },

                isVisible(id) {
                    return this.filteredAndSortedIds.includes(id);
                },

                getOrder(id) {
                    const idx = this.filteredAndSortedIds.indexOf(id);
                    return idx === -1 ? 9999 : idx;
                },

                setSort(type) {
                    this.sortBy = type || 'newest';
                },

                resetFilters() {
                    this.searchQuery = '';
                    this.activeCategory = '';
                    this.sortBy = 'newest';
                }
            }));
        });
    </script>

    <style>
        [x-cloak] { display: none !important; }

        @keyframes fade-in-up {
            0% { opacity: 0; transform: translateY(15px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fade-in-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .modern-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .modern-scrollbar::-webkit-scrollbar-track {
            background: transparent;
            margin-block: 6px;
            border-radius: 10px;
        }
        .modern-scrollbar::-webkit-scrollbar-thumb {
            background-color: #E7E5E4;
            border-radius: 10px;
            transition: background-color 0.3s ease;
        }
        .modern-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: #CF4173;
        }
        .modern-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: #E7E5E4 transparent;
        }
    </style>
@endsection