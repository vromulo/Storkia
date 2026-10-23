@extends('buyer.account-settings', [
    'pageTitle' => 'Personal Center',
    'pageSubtitle' => 'Overview of your account profile, assets, and shopping activity.'
])

@section('option-content')
@php
    $user = auth()->user();
    $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    $initials = strtoupper(substr($user->first_name ?? 'U', 0, 1));
@endphp

<div x-data="{ 
        view: (window.innerWidth < 768 && new URLSearchParams(window.location.search).get('view') === 'menu') ? 'menu' : 'center',
        goBackToCenter() {
            this.view = 'center';
            if (new URLSearchParams(window.location.search).has('view')) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }
     }" 
     @resize.window="if (window.innerWidth >= 768) { view = 'center'; }"
     class="select-none pt-0">

    <!-- ======================================================== -->
    <!-- VIEW 1: PERSONAL CENTER OVERVIEW (DEFAULT & ALWAYS MD+)  -->
    <!-- ======================================================== -->
    <div x-show="view === 'center'" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="space-y-6 md:!block">

        <!-- Top Identity & Profile Row -->
        <div class="flex flex-row items-center justify-between gap-3 pb-4 border-b border-border-subtle">
            
            <!-- Left: Greeting, VIP Badge, & Membership Info -->
            <div class="min-w-0 pr-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-base sm:text-lg font-semibold text-text-main font-sans tracking-tight leading-snug">
                        Hi, {{ $fullName ?: 'Valued Customer' }}
                    </h2>
                </div>
                <p class="text-[11px] text-text-muted mt-0.5">
                    Member since {{ $user->created_at ? $user->created_at->format('M Y') : '2026' }}
                </p>
            </div>

            <!-- Right: Settings Icon on Top & My Profile Action Link Below -->
            <div class="shrink-0 flex flex-col items-end justify-center gap-1.5">
                <!-- Small Settings Icon (< 768px): Tapping dynamically switches view to 'menu' -->
                <button type="button" 
                        @click="view = 'menu'" 
                        class="md:hidden p-1 text-text-main hover:text-primary transition-colors flex items-center justify-center cursor-pointer focus:outline-none"
                        aria-label="Account Menu">
                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>

                <!-- My Profile Action Link -->
                <a href="{{ route('user.profile') }}" 
                   class="text-xs font-semibold text-text-main hover:text-primary transition-colors flex items-center gap-1 group whitespace-nowrap">
                    <span>My Profile</span>
                    <svg class="w-3.5 h-3.5 text-text-muted group-hover:text-primary group-hover:translate-x-0.5 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </div>

        <!-- Assets & Favorites Counters Row -->
        <div class="grid grid-cols-5 gap-1 sm:gap-2 text-center w-full">
            
            <!-- 1. Coupons -->
            <a href="{{ route('user.coupons') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                <div class="h-8 flex items-center justify-center mb-1.5">
                    <span class="text-lg sm:text-xl font-bold text-text-main group-hover:text-primary transition-colors font-sans leading-none">
                        4
                    </span>
                </div>
                <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors truncate w-full">
                    Coupons
                </span>
            </a>

            <!-- 2. Points -->
            <a href="{{ route('user.points') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                <div class="h-8 flex items-center justify-center mb-1.5">
                    <div class="w-6 h-6 rounded-full border-2 border-text-main group-hover:border-primary flex items-center justify-center font-bold text-xs text-text-main group-hover:text-primary transition-colors leading-none">
                        S
                    </div>
                </div>
                <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors truncate w-full">
                    Points
                </span>
            </a>

            <!-- 3. Wishlist -->
            <a href="{{ route('user.wishlist') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                <div class="w-8 h-8 flex items-center justify-center text-text-main group-hover:text-primary transition-colors mb-1.5">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </div>
                <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors truncate w-full">
                    Wishlist
                </span>
            </a>

            <!-- 4. Following -->
            <a href="{{ route('user.following') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                <div class="w-8 h-8 flex items-center justify-center text-text-main group-hover:text-primary transition-colors mb-1.5">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors truncate w-full">
                    Following
                </span>
            </a>

            <!-- 5. Recently Viewed -->
            <a href="{{ route('user.recently-viewed') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                <div class="w-8 h-8 flex items-center justify-center text-text-main group-hover:text-primary transition-colors mb-1.5">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors truncate w-full">
                    Recent
                </span>
            </a>
        </div>

        <!-- Orders Overview Section -->
        <div class="pt-5 border-t border-border-subtle">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-sm sm:text-base font-bold text-text-main font-sans tracking-tight">
                    My Orders
                </h3>
                <a href="{{ route('user.orders') }}" 
                   class="text-xs font-semibold text-text-main hover:text-primary transition-colors flex items-center gap-1 group">
                    <span>View All</span>
                    <svg class="w-3.5 h-3.5 text-text-muted group-hover:text-primary group-hover:translate-x-0.5 transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
            
            <!-- Order Status Tabs Grid -->
            <div class="grid grid-cols-5 gap-1 sm:gap-2 text-center">
                
                <!-- 1. Unpaid -->
                <a href="{{ route('user.orders') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                    <div class="w-8 h-8 flex items-center justify-center text-text-main group-hover:text-primary transition-colors mb-1.5">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <rect x="3" y="5" width="18" height="14" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="3" y1="10" x2="21" y2="10" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="14" y1="15" x2="18" y2="15" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors">
                        Unpaid
                    </span>
                </a>

                <!-- 2. Processing -->
                <a href="{{ route('user.orders') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                    <div class="w-8 h-8 flex items-center justify-center text-text-main group-hover:text-primary transition-colors mb-1.5">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="5" width="16" height="4" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9M10 9v4"/>
                        </svg>
                    </div>
                    <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors">
                        Processing
                    </span>
                </a>

                <!-- 3. Shipped -->
                <a href="{{ route('user.orders') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                    <div class="w-8 h-8 flex items-center justify-center text-text-main group-hover:text-primary transition-colors mb-1.5">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h2m-2 3h3m-3 3h2M7 6h8a1 1 0 011 1v9H7V6zm8 4h3.586a1 1 0 01.707.293l2.414 2.414a1 1 0 01.293.707V16h-7v-6z"/>
                            <circle cx="9" cy="18" r="1.5"/>
                            <circle cx="18" cy="18" r="1.5"/>
                        </svg>
                    </div>
                    <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors">
                        Shipped
                    </span>
                </a>

                <!-- 4. Review -->
                <a href="{{ route('user.orders') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                    <div class="w-8 h-8 flex items-center justify-center text-text-main group-hover:text-primary transition-colors mb-1.5">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 6h14a1 1 0 011 1v9a1 1 0 01-1 1H9l-4 4v-4H5a1 1 0 01-1-1V7a1 1 0 011-1z"/>
                            <circle cx="9" cy="11.5" r="0.75" fill="currentColor"/>
                            <circle cx="15" cy="11.5" r="0.75" fill="currentColor"/>
                        </svg>
                    </div>
                    <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors">
                        Review
                    </span>
                </a>

                <!-- 5. Returns -->
                <a href="{{ route('user.orders') }}" class="py-2 px-1 rounded-xl hover:bg-surface-subtle transition-colors group flex flex-col items-center">
                    <div class="w-8 h-8 flex items-center justify-center text-text-main group-hover:text-primary transition-colors mb-1.5">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="5" width="16" height="3" rx="0.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 8v11a1 1 0 001 1h12a1 1 0 001-1V8"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 13h-4m0 0l1.75-1.75M10 13l1.75 1.75"/>
                        </svg>
                    </div>
                    <span class="text-[11px] sm:text-xs font-normal text-text-main group-hover:text-primary transition-colors">
                        Returns
                    </span>
                </a>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- VIEW 2: FULL SIDEBAR MENU LIST (MOBILE ONLY < 768PX)    -->
    <!-- ======================================================== -->
    <div x-show="view === 'menu'" 
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-x-2"
         x-transition:enter-end="opacity-100 translate-x-0"
         class="space-y-4 md:!hidden">

        <!-- Top Back Bar (Returns to Personal Center and strips view=menu) -->
        <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
            <button type="button" 
                    @click="goBackToCenter()" 
                    class="text-sm font-semibold text-text-muted hover:text-text-main transition-colors flex items-center gap-1.5 cursor-pointer focus:outline-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                <span>Back</span>
            </button>
            <h3 class="text-sm font-bold text-text-main">Menu</h3>
            <div class="w-12"></div>
        </div>

        <!-- Flat Menu Sections (No accordion dropdowns) -->
        <div class="space-y-4 text-xs divide-y divide-border-subtle/50">

            <!-- 1. My Account (Static Header + Flat Sub-links) -->
            <div class="pt-1">
                <div class="flex items-center gap-2 px-3 py-1 text-[11px] font-bold text-text-muted uppercase tracking-wider">
                    <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>My Account</span>
                </div>
                <div class="mt-1 space-y-0.5">
                    <a href="{{ route('user.profile') }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Profile</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.addresses') }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Address Book</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.account-management') }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Account Management</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- 2. My Orders (Static Header + Flat Sub-links) -->
            <div class="pt-3">
                <div class="flex items-center gap-2 px-3 py-1 text-[11px] font-bold text-text-muted uppercase tracking-wider">
                    <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span>My Orders</span>
                </div>
                <div class="mt-1 space-y-0.5">
                    <a href="{{ route('user.orders') }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>All Orders</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.orders', ['status' => 'unpaid']) }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Unpaid Orders</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.orders', ['status' => 'processing']) }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Processing Orders</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.orders', ['status' => 'shipped']) }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Shipped Orders</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.orders', ['status' => 'review']) }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Review Orders</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.orders', ['status' => 'returns']) }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Return Orders</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- 3. My Assets (Static Header + Flat Sub-links) -->
            <div class="pt-3">
                <div class="flex items-center gap-2 px-3 py-1 text-[11px] font-bold text-text-muted uppercase tracking-wider">
                    <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>My Assets</span>
                </div>
                <div class="mt-1 space-y-0.5">
                    <a href="{{ route('user.coupons') }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>My Coupons</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.points') }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>My Points / Coins</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- 4. My Favorites (Static Header + Flat Sub-links) -->
            <div class="pt-3">
                <div class="flex items-center gap-2 px-3 py-1 text-[11px] font-bold text-text-muted uppercase tracking-wider">
                    <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                    <span>My Favorites</span>
                </div>
                <div class="mt-1 space-y-0.5">
                    <a href="{{ route('user.wishlist') }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Wish List</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.recently-viewed') }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Recently Viewed</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('user.following') }}" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-text-main hover:bg-surface-subtle transition-colors">
                        <span>Following</span>
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- Logout Button -->
            <div class="pt-3">
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" 
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-bold text-danger hover:bg-danger/10 transition-colors cursor-pointer text-left">
                        <div class="flex items-center gap-3">
                            <svg class="w-4.5 h-4.5 text-danger shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Logout</span>
                        </div>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection