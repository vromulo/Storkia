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

<div class="space-y-6 select-none pt-0">

    <!-- Top Identity & Profile Row -->
    <div class="flex flex-row items-center justify-between gap-3 pb-4 border-b border-border-subtle">
        
        <!-- Left: Greeting, VIP Badge, & Membership Info -->
        <div class="min-w-0 pr-2">
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-base sm:text-lg font-semibold text-text-main font-sans tracking-tight leading-snug">
                    Hi, {{ $fullName ?: 'Valued Customer' }}
                </h2>
                <!-- Badge -->
                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-text-main text-surface shrink-0">
                    <svg class="w-2.5 h-2.5 text-amber-300" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <span>S1</span>
                </span>
            </div>
            <p class="text-[11px] text-text-muted mt-0.5">
                Member since {{ $user->created_at ? $user->created_at->format('M Y') : '2026' }}
            </p>
        </div>

        <!-- Right: My Profile Action Link -->
        <div class="shrink-0 flex items-center">
            <a href="{{ route('user.profile') }}" 
               class="text-xs font-semibold text-text-main hover:text-primary transition-colors flex items-center gap-1 group whitespace-nowrap">
                <span>My Profile</span>
                <svg class="w-3.5 h-3.5 text-text-muted group-hover:text-primary group-hover:translate-x-0.5 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
    </div>

    <!-- Assets & Favorites Counters Row (no wrapping) -->
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
        <!-- Section Header with View All Link -->
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
        
        <!-- Order Status Tabs Grid (Unpaid, Processing, Shipped, Review, Returns) -->
        <div class="grid grid-cols-5 gap-1 sm:gap-2 text-center">
            
            <!-- 1. Unpaid (Payment / Card icon) -->
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

            <!-- 2. Processing (Packaging Box icon) -->
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

            <!-- 3. Shipped (Delivery Truck icon) -->
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

            <!-- 4. Review (Speech Bubble with text dots icon) -->
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

            <!-- 5. Returns (Box with Return Arrow icon) -->
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
@endsection