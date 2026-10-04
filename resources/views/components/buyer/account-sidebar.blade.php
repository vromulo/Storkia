<aside class="w-full lg:w-64 shrink-0">
    <!-- User Quick Identity Card -->
    <div class="bg-surface rounded-2xl border border-border-subtle p-4 mb-4 shadow-xs flex items-center gap-3">
        <div class="w-12 h-12 rounded-full bg-primary/10 text-primary border border-primary/20 flex items-center justify-center font-bold text-lg shrink-0">
            {{ strtoupper(substr(auth()->user()->first_name ?? 'U', 0, 1)) }}
        </div>
        <div class="overflow-hidden flex-1">
            <h4 class="font-bold text-sm text-text-main truncate">
                {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
            </h4>
            <a href="{{ route('user.profile') }}" class="text-xs text-primary hover:text-primary-dark font-medium flex items-center gap-1 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
                Edit Profile
            </a>
        </div>
    </div>

    <!-- Sidebar Navigation Card -->
    <nav class="bg-surface rounded-2xl border border-border-subtle p-3.5 shadow-xs space-y-2">
        
        <!-- 1. My Account (Dropdown) -->
        <div x-data="{ open: {{ request()->routeIs('user.profile', 'user.addresses', 'user.change-password', 'user.account-management') ? 'true' : 'false' }} }">
            <button type="button" 
                    @click="open = !open" 
                    class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold uppercase tracking-wider text-text-muted hover:text-text-main hover:bg-surface-subtle rounded-xl transition-colors cursor-pointer focus:outline-none">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 {{ request()->routeIs('user.profile', 'user.addresses', 'user.change-password', 'user.account-management') ? 'text-primary' : 'text-text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>My Account</span>
                </div>
                <svg class="w-3.5 h-3.5 text-text-muted transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" x-collapse class="mt-1 space-y-0.5" x-cloak>
                <a href="{{ route('user.profile') }}"
                   class="block pl-9 pr-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.profile') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    Profile
                </a>
                <a href="{{ route('user.addresses') }}"
                   class="block pl-9 pr-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.addresses') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    Address Book
                </a>
                <a href="{{ route('user.change-password') }}"
                   class="block pl-9 pr-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.change-password') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    Change Password
                </a>
                <a href="{{ route('user.account-management') }}"
                   class="block pl-9 pr-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.account-management') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    Account Management
                </a>
            </div>
        </div>

        <!-- 2. My Orders (Direct Link) -->
        <div>
            <a href="{{ route('user.orders') }}"
               class="flex items-center gap-2.5 px-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.orders') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                <svg class="w-4 h-4 {{ request()->routeIs('user.orders') ? 'text-primary' : 'text-text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span class="font-bold">My Orders</span>
            </a>
        </div>

        <!-- 3. My Assets (Dropdown) -->
        <div x-data="{ open: {{ request()->routeIs('user.coupons', 'user.points') ? 'true' : 'false' }} }">
            <button type="button" 
                    @click="open = !open" 
                    class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold uppercase tracking-wider text-text-muted hover:text-text-main hover:bg-surface-subtle rounded-xl transition-colors cursor-pointer focus:outline-none">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 {{ request()->routeIs('user.coupons', 'user.points') ? 'text-primary' : 'text-text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>My Assets</span>
                </div>
                <svg class="w-3.5 h-3.5 text-text-muted transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" x-collapse class="mt-1 space-y-0.5" x-cloak>
                <a href="{{ route('user.coupons') }}"
                   class="block pl-9 pr-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.coupons') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    My Coupons
                </a>
                <a href="{{ route('user.points') }}"
                   class="block pl-9 pr-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.points') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    My Points / Coins
                </a>
            </div>
        </div>

        <!-- 4. My Favorites (Dropdown) -->
        <div x-data="{ open: {{ request()->routeIs('user.wishlist', 'user.recently-viewed', 'user.following') ? 'true' : 'false' }} }">
            <button type="button" 
                    @click="open = !open" 
                    class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold uppercase tracking-wider text-text-muted hover:text-text-main hover:bg-surface-subtle rounded-xl transition-colors cursor-pointer focus:outline-none">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 {{ request()->routeIs('user.wishlist', 'user.recently-viewed', 'user.following') ? 'text-primary' : 'text-text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                    <span>My Favorites</span>
                </div>
                <svg class="w-3.5 h-3.5 text-text-muted transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="open" x-collapse class="mt-1 space-y-0.5" x-cloak>
                <a href="{{ route('user.wishlist') }}"
                   class="block pl-9 pr-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.wishlist') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    Wish List
                </a>
                <a href="{{ route('user.recently-viewed') }}"
                   class="block pl-9 pr-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.recently-viewed') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    Recently Viewed
                </a>
                <a href="{{ route('user.following') }}"
                   class="block pl-9 pr-3 py-2 text-xs rounded-xl transition-colors {{ request()->routeIs('user.following') ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-surface-subtle' }}">
                    Following
                </a>
            </div>
        </div>

    </nav>
</aside>