@extends('layouts.app')

@section('content')
<div class="bg-surface min-h-[calc(100vh-250px)] py-4 sm:py-6 md:py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumb Navigation & Header: Hidden on mobile (< 768px), visible on md+ -->
        <div class="hidden md:block mb-6">
            @php
                $currentLabel = match(true) {
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
                <span class="text-border-subtle">/</span>
                
                <span class="text-text-muted font-normal cursor-default">
                    Personal Center
                </span>

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
                @yield('option-content')
            </main>
        </div>

    </div>
</div>
@endsection