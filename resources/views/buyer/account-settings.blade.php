@extends('layouts.app')

@section('content')
<div class="bg-surface min-h-[calc(100vh-250px)] py-4 sm:py-6 md:py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumb Navigation & Header: Hidden on mobile (< 768px), visible on md+ -->
        <div class="hidden md:block mb-6">
            @php
                $currentLabel = match(true) {
                    request()->routeIs('user.account') => 'Personal Center',
                    request()->routeIs('user.account-management') => 'Account Management',
                    request()->routeIs('user.profile') => 'Profile',
                    request()->routeIs('user.addresses') => 'Address Book',
                    request()->routeIs('user.change-password') => 'Change Password',
                    request()->routeIs('user.orders') => 'My Orders',
                    request()->routeIs('user.coupons') => 'My Coupons',
                    request()->routeIs('user.points') => 'My Points/Coins',
                    request()->routeIs('user.wishlist') => 'Wish List',
                    request()->routeIs('user.recently-viewed') => 'Recently Viewed',
                    request()->routeIs('user.following') => 'Following',
                    default => $breadcrumbSection ?? null,
                };
            @endphp

            <nav class="flex items-center space-x-2 text-xs text-text-muted mb-3 select-none" aria-label="Breadcrumb">
                <a href="/" class="hover:text-primary transition-colors">Home</a>

                @if($currentLabel)
                    <span class="text-border-subtle">/</span>
                    <span class="text-text-main font-semibold">
                        {{ $currentLabel }}
                    </span>
                @endif
            </nav>
        </div>

        <!-- Flush Two-column Layout on Desktop (md:flex-row) -->
        <div class="flex flex-col md:flex-row items-start bg-surface">
            <!-- Desktop Sidebar (Hidden on mobile via account-sidebar) -->
            <x-buyer.account-sidebar />

            <!-- Option Content Area -->
            <main class="flex-1 w-full bg-surface rounded-none border-0 shadow-none pt-0 px-4 sm:px-6 pb-6 md:px-8 md:pb-8 lg:px-10 lg:pb-10 md:border-l md:border-border-subtle">
                <!-- Unified Subpage Header for all subpages except Personal Center dashboard -->
                @if(!request()->routeIs('user.account'))
                    @php
                        $subpageTitle = match(true) {
                            request()->routeIs('user.account-management') => 'Manage My Account',
                            request()->routeIs('user.profile') => 'Profile',
                            request()->routeIs('user.addresses') => 'Address Book',
                            request()->routeIs('user.change-password') => 'Change Password',
                            request()->routeIs('user.orders') => 'My Orders',
                            request()->routeIs('user.coupons') => 'My Coupons',
                            request()->routeIs('user.points') => 'My Points/Coins',
                            request()->routeIs('user.wishlist') => 'Wish List',
                            request()->routeIs('user.recently-viewed') => 'Recently Viewed',
                            request()->routeIs('user.following') => 'Following',
                            default => $pageTitle ?? 'My Account',
                        };
                    @endphp

                    <div class="flex items-center justify-between md:justify-center gap-3 pb-4 mb-6 select-none relative">
                        <!-- Subpage Title: Extra-bold, Left on mobile, Centered in column on desktop -->
                        <h2 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-text-main tracking-tight leading-tight truncate text-left md:text-center md:w-full">
                            {{ $subpageTitle }}
                        </h2>

                        <!-- "<" Back to Menu Link: Visible on mobile, Completely hidden on desktop -->
                        <a href="{{ route('user.account', ['view' => 'menu']) }}" 
                           class="md:hidden p-1.5 -mr-1.5 text-text-muted hover:text-text-main transition-colors flex items-center justify-center shrink-0 cursor-pointer"
                           aria-label="Back to Menu"
                           title="Back to Menu">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </a>
                    </div>
                @endif

                @yield('option-content')
            </main>
        </div>

    </div>
</div>
@endsection