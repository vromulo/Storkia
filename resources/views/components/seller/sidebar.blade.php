<aside :class="sidebarOpen ? 'w-72' : 'w-20'" class="relative flex flex-col h-screen border-r border-border-subtle shadow-lg transition-all duration-300 ease-in-out bg-gradient-to-b from-surface via-surface to-brand-light/30 flex-shrink-0 z-40">
    
    <!-- Minimize/Maximize Toggle Button -->
    <button @click="sidebarOpen = !sidebarOpen" class="absolute -right-3.5 top-8 bg-surface border border-border-subtle text-primary rounded-full p-1.5 shadow-md hover:bg-brand-light/50 transition-colors z-50 focus:outline-none cursor-pointer">
        <svg class="w-4 h-4 transition-transform duration-300" :class="!sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <!-- Logo Header -->
    <div class="h-20 flex items-center justify-center px-4 border-b border-border-subtle overflow-hidden">
        <a href="{{ route('seller.seller-dashboard') }}"
            @click="if (window.location.href.split('?')[0] === '{{ route('seller.seller-dashboard') }}') { $event.preventDefault(); window.scrollTo({top: 0, behavior: 'smooth'}); }"
            class="flex items-center justify-center h-10 w-full overflow-hidden text-primary-dark transition-colors cursor-pointer">
            <img x-show="sidebarOpen" x-transition.opacity.duration.300ms src="{{ asset('assets/storkia-maximized.png') }}" alt="Storkia" class="h-8 w-auto object-contain">
            <img x-show="!sidebarOpen" x-cloak src="{{ asset('assets/storkia-minimized.png') }}" alt="S" class="h-8 w-auto object-contain">
        </a>
    </div>

    <!-- Navigation Links -->
    <nav x-data="{ activeDropdown: '{{ request()->is('seller/products*') ? 'products' : (request()->is('seller/orders*') ? 'orders' : (request()->is('seller/promotions*') ? 'promotions' : (request()->is('seller/sales*') ? 'sales' : (request()->is('seller/account*') ? 'account' : null)))) }}' }" class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-1.5 custom-scrollbar">

        <!-- Dashboard Link -->
        <a href="{{ route('seller.seller-dashboard') }}"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-bold group whitespace-nowrap relative text-sm cursor-pointer {{ request()->routeIs('seller.seller-dashboard') ? 'text-primary bg-brand-light/60 shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Dashboard' : ''">
            <svg class="w-6 h-6 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Dashboard</span>
        </a>

        <!-- Products -->
        @if(auth()->user()->sellerProfile)
        @php $isProductsActive = request()->is('seller/products*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'products'; } else { activeDropdown = activeDropdown === 'products' ? null : 'products'; }"
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isProductsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                    :title="!sidebarOpen ? 'Products' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isProductsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Products</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'products'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'products' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('seller.products.index') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.products.index') || request()->routeIs('seller.products.create') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    All Products
                </a>
                <a href="{{ route('seller.products.inventory') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.products.inventory') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Inventory
                </a>
                <a href="{{ route('seller.products.archived') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.products.archived') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Archived Products
                </a>
            </div>
        </div>
        @endif

        <!-- Orders -->
        @if(auth()->user()->sellerProfile)
        @php $isOrdersActive = request()->is('seller/orders*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'orders'; } else { activeDropdown = activeDropdown === 'orders' ? null : 'orders'; }"
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isOrdersActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                    :title="!sidebarOpen ? 'Orders' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isOrdersActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Orders</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'orders'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'orders' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">All Orders</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">New Orders</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">To Process</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">To Ship</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Shipped</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Delivered</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Cancelled / Returned</a>
            </div>
        </div>
        @endif

        <!-- Promotions -->
        @if(auth()->user()->sellerProfile)
        @php $isPromotionsActive = request()->is('seller/promotions*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'promotions'; } else { activeDropdown = activeDropdown === 'promotions' ? null : 'promotions'; }"
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isPromotionsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                    :title="!sidebarOpen ? 'Promotions' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isPromotionsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c.41 0 .672-.425.534-.813a4.001 4.001 0 00-7.625 2.138A4.001 4.001 0 005.436 13.683z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Promotions</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'promotions'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'promotions' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Discounts</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Vouchers</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Campaigns</a>
            </div>
        </div>
        @endif

        <!-- Sales & Reports -->
        @if(auth()->user()->sellerProfile)
        @php $isSalesActive = request()->is('seller/sales*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'sales'; } else { activeDropdown = activeDropdown === 'sales' ? null : 'sales'; }"
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isSalesActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                    :title="!sidebarOpen ? 'Sales & Reports' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isSalesActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Sales & Reports</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'sales'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'sales' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Sales Overview</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Profit & Financial</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Performance</a>
            </div>
        </div>
        @endif

        <!-- Reviews & Feedback -->
        <a href="#"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ request()->is('seller/reviews*') ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Reviews & Feedback' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ request()->is('seller/reviews*') ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Reviews & Feedback</span>
        </a>

        <!-- Chat / Messages -->
        <a href="#"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ request()->is('seller/chat*') ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Chat / Messages' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ request()->is('seller/chat*') ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Chat / Messages</span>
        </a>

        <!-- Account -->
        @php $isAccountActive = request()->is('seller/account*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'account'; } else { activeDropdown = activeDropdown === 'account' ? null : 'account'; }"
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isAccountActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                    :title="!sidebarOpen ? 'Account' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isAccountActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Account</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'account'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'account' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Store Profile</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Personal Information</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Payment / Payout</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Security</a>
            </div>
        </div>
    </nav>

    <!-- Logout Footer -->
    <div class="p-3 border-t border-border-subtle bg-surface/50 backdrop-blur-sm">
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="w-full flex items-center px-3 py-3 text-red-500 font-bold hover:bg-red-50 rounded-xl transition-colors whitespace-nowrap text-sm cursor-pointer" :title="!sidebarOpen ? 'Logout' : ''">
                <svg class="w-6 h-6 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Logout</span>
            </button>
        </form>
    </div>
</aside>