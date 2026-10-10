@props(['isGlobal' => false])

@if ($isGlobal)
    <!-- Mobile Drawer Backdrop (< 768px): Smooth Fade with 60% Black -->
    <div x-show="mobileDrawerOpen" 
         x-cloak
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-250"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileDrawerOpen = false"
         class="fixed inset-0 z-[100] bg-black/60">
    </div>

    <!-- Mobile Slide-Over Drawer Container: Smooth Left-to-Right Slide -->
    <aside x-show="mobileDrawerOpen"
           x-cloak
           x-transition:enter="transition-transform ease-out duration-300 transform"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition-transform ease-in duration-250 transform"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           class="fixed inset-y-0 left-0 z-[100] w-72 max-w-[85vw] bg-surface shadow-2xl overflow-y-auto custom-scrollbar flex flex-col justify-between">
@else
    <!-- Desktop Static Docked Sidebar (>= 768px in Personal Center) -->
    <aside class="hidden md:flex md:w-56 lg:w-64 shrink-0 bg-surface md:shadow-none overflow-y-auto custom-scrollbar flex-col justify-between">
@endif

    <div>
        <!-- User Profile Card -->
        <div class="p-6 pb-5 flex flex-col items-start border-b border-border-subtle/80 relative">
            @if ($isGlobal)
                <!-- Mobile Close Button (✕) -->
                <button type="button" 
                        @click="mobileDrawerOpen = false" 
                        class="absolute top-4 right-4 p-1.5 text-text-muted hover:text-text-main rounded-lg transition-colors cursor-pointer"
                        aria-label="Close menu">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif

            <!-- Circular User Avatar -->
            <div class="w-14 h-14 rounded-full bg-brand-light/50 text-text-main border border-border-subtle flex items-center justify-center font-bold text-xl shadow-xs mb-3.5 shrink-0">
                {{ strtoupper(substr(auth()->user()->first_name ?? 'U', 0, 1)) }}
            </div>

            <!-- Name & Email -->
            <h3 class="font-bold text-base text-text-main tracking-tight leading-snug">
                {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
            </h3>
            <p class="text-xs text-text-muted truncate max-w-[210px] mt-0.5 font-normal">
                {{ auth()->user()->email ?? 'user@example.com' }}
            </p>
        </div>

        <!-- Navigation Menu -->
        <nav class="p-3 space-y-1 text-xs">
            <!-- 1. My Account (Dropdown) -->
            <div x-data="{ open: {{ request()->routeIs('user.profile', 'user.addresses', 'user.change-password', 'user.account-management') ? 'true' : 'false' }} }">
                <button type="button" 
                        @click="open = !open" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer">
                    <div class="flex items-center gap-3">
                        <svg class="w-4.5 h-4.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>My Account</span>
                    </div>
                    <svg class="w-4 h-4 text-text-muted transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" x-collapse class="mt-1 space-y-0.5 pl-6" x-cloak>
                    <a href="{{ route('user.profile') }}"
                       class="block px-3 py-2 rounded-lg font-medium transition-colors {{ request()->routeIs('user.profile') ? 'bg-primary/10 text-primary font-bold' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        Profile
                    </a>
                    <a href="{{ route('user.addresses') }}"
                       class="block px-3 py-2 rounded-lg font-medium transition-colors {{ request()->routeIs('user.addresses') ? 'bg-primary/10 text-primary font-bold' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        Address Book
                    </a>
                    <a href="{{ route('user.change-password') }}"
                       class="block px-3 py-2 rounded-lg font-medium transition-colors {{ request()->routeIs('user.change-password') ? 'bg-primary/10 text-primary font-bold' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        Change Password
                    </a>
                    <a href="{{ route('user.account-management') }}"
                       class="block px-3 py-2 rounded-lg font-medium transition-colors {{ request()->routeIs('user.account-management') ? 'bg-primary/10 text-primary font-bold' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        Account Management
                    </a>
                </div>
            </div>

            <!-- 2. My Orders (Direct Link) -->
            <div>
                <a href="{{ route('user.orders') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-semibold transition-colors {{ request()->routeIs('user.orders') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    <svg class="w-4.5 h-4.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span>My Orders</span>
                </a>
            </div>

            <!-- 3. My Assets (Dropdown) -->
            <div x-data="{ open: {{ request()->routeIs('user.coupons', 'user.points') ? 'true' : 'false' }} }">
                <button type="button" 
                        @click="open = !open" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer">
                    <div class="flex items-center gap-3">
                        <svg class="w-4.5 h-4.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>My Assets</span>
                    </div>
                    <svg class="w-4 h-4 text-text-muted transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" x-collapse class="mt-1 space-y-0.5 pl-6" x-cloak>
                    <a href="{{ route('user.coupons') }}"
                       class="block px-3 py-2 rounded-lg font-medium transition-colors {{ request()->routeIs('user.coupons') ? 'bg-primary/10 text-primary font-bold' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        My Coupons
                    </a>
                    <a href="{{ route('user.points') }}"
                       class="block px-3 py-2 rounded-lg font-medium transition-colors {{ request()->routeIs('user.points') ? 'bg-primary/10 text-primary font-bold' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        My Points / Coins
                    </a>
                </div>
            </div>

            <!-- 4. My Favorites (Dropdown) -->
            <div x-data="{ open: {{ request()->routeIs('user.wishlist', 'user.recently-viewed', 'user.following') ? 'true' : 'false' }} }">
                <button type="button" 
                        @click="open = !open" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer">
                    <div class="flex items-center gap-3">
                        <svg class="w-4.5 h-4.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span>My Favorites</span>
                    </div>
                    <svg class="w-4 h-4 text-text-muted transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" x-collapse class="mt-1 space-y-0.5 pl-6" x-cloak>
                    <a href="{{ route('user.wishlist') }}"
                       class="block px-3 py-2 rounded-lg font-medium transition-colors {{ request()->routeIs('user.wishlist') ? 'bg-primary/10 text-primary font-bold' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        Wish List
                    </a>
                    <a href="{{ route('user.recently-viewed') }}"
                       class="block px-3 py-2 rounded-lg font-medium transition-colors {{ request()->routeIs('user.recently-viewed') ? 'bg-primary/10 text-primary font-bold' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        Recently Viewed
                    </a>
                    <a href="{{ route('user.following') }}"
                       class="block px-3 py-2 rounded-lg font-medium transition-colors {{ request()->routeIs('user.following') ? 'bg-primary/10 text-primary font-bold' : 'text-text-muted hover:text-text-main hover:bg-surface-subtle' }}">
                        Following
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <!-- Bottom Actions -->
    <div class="p-3 border-t border-border-subtle/80 space-y-1">
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" 
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-danger hover:bg-danger/10 transition-colors cursor-pointer text-left">
                <svg class="w-4.5 h-4.5 text-danger shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>