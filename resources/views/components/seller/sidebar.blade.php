@php
    $badges = $sellerSidebarBadges ?? [
        'orders_new'        => 0,
        'orders_pending'    => 0,
        'orders_to_process' => 0,
        'orders_to_ship'    => 0,
        'orders_disputed'   => 0,
        'orders_total'      => 0,
        'low_stock'         => 0,
        'disapproved_prods' => 0,
        'products_total'    => 0,
        'unread_messages'   => 0,
        'unreplied_reviews' => 0,
    ];
    $isProductsActive = request()->is('seller/products*');
    $isPromotionsActive = request()->is('seller/promotions*') || request()->is('seller/vouchers*');
    $isOrdersActive = request()->is('seller/orders*');
    $isSalesActive = request()->is('seller/sales*');
    $isRatingsActive = request()->is('seller/ratings*');

    // Chat counters from ChatServiceProvider: 'messages' = unread direct chats (buyers + logistics),
    // 'support' = unread replies from the Storkia support team.
    $chatBadges = $chatBadges ?? ['messages' => 0, 'support' => 0, 'total' => 0];
    $messagesBadge = (int) ($chatBadges['messages'] ?: ($badges['unread_messages'] ?? 0));
    $supportBadge = (int) ($chatBadges['support'] ?? 0);
    $isMessagesActive = request()->is('seller/messages*');
    $isHelpCenterActive = request()->is('seller/help-center*');
@endphp

<aside :class="sidebarOpen ? 'w-72' : 'w-20'" class="relative flex flex-col h-screen border-r border-border-subtle shadow-lg transition-all duration-300 ease-in-out bg-gradient-to-b from-surface via-surface to-brand-light/30 flex-shrink-0 z-40">

    <button @click="sidebarOpen = !sidebarOpen" class="absolute -right-3.5 top-8 bg-surface border border-border-subtle text-primary rounded-full p-1.5 shadow-md hover:bg-brand-light/50 transition-colors z-50 focus:outline-none cursor-pointer">
        <svg class="w-4 h-4 transition-transform duration-300" :class="!sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <div class="h-20 flex items-center justify-center px-4 border-b border-border-subtle overflow-hidden">
        <a href="{{ route('seller.seller-dashboard') }}"
            @click="if (window.location.href.split('?')[0] === '{{ route('seller.seller-dashboard') }}') { $event.preventDefault(); window.scrollTo({top: 0, behavior: 'smooth'}); }"
            class="flex items-center justify-center h-10 w-full overflow-hidden text-primary-dark transition-colors cursor-pointer">
            <img x-show="sidebarOpen" x-transition.opacity.duration.300ms src="{{ asset('assets/pink-storkia-maximized.webp') }}" alt="Storkia" class="h-8 w-auto object-contain">
            <img x-show="!sidebarOpen" x-cloak src="{{ asset('assets/pink-storkia-minimized.webp') }}" alt="S" class="h-8 w-auto object-contain">
        </a>
    </div>

    <nav x-data="{ activeDropdown: '{{ $isProductsActive ? 'products' : ($isPromotionsActive ? 'promotions' : ($isOrdersActive ? 'orders' : ($isSalesActive ? 'sales' : (request()->is('seller/account*') ? 'account' : null)))) }}' }" class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-1.5 custom-scrollbar">

        <a href="{{ route('seller.seller-dashboard') }}"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-bold group whitespace-nowrap relative text-sm cursor-pointer {{ request()->routeIs('seller.seller-dashboard') ? 'text-primary bg-brand-light/60 shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Dashboard' : ''">
            <svg class="w-6 h-6 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Dashboard</span>
        </a>

        @if(auth()->user()->sellerProfile)
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
                <div class="flex items-center gap-1.5">
                    @if(($badges['products_total'] ?? 0) > 0)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500 text-white">
                            {{ $badges['products_total'] }}
                        </span>
                    @endif
                    <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'products'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="activeDropdown === 'products' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('seller.products.index') }}" class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.products.index') || request()->routeIs('seller.products.create') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>All Products &amp; Drafts</span>
                    @if(($badges['disapproved_prods'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-danger">{{ $badges['disapproved_prods'] }}</span>
                    @endif
                </a>
                <a href="{{ route('seller.products.inventory') }}" class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.products.inventory') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>Inventory</span>
                    @if(($badges['low_stock'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">{{ $badges['low_stock'] }}</span>
                    @endif
                </a>
                <a href="{{ route('seller.products.archived') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.products.archived') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Archived Products
                </a>
            </div>
        </div>

        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'promotions'; } else { activeDropdown = activeDropdown === 'promotions' ? null : 'promotions'; }"
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isPromotionsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                    :title="!sidebarOpen ? 'Promotions & Vouchers' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isPromotionsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Promotions &amp; Vouchers</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'promotions'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'promotions' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('seller.promotions.index') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.promotions.*') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Super Deals, Sales &amp; Bundles
                </a>
                <a href="{{ route('seller.vouchers.index') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.vouchers.*') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Store Vouchers
                </a>
            </div>
        </div>

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
                <div class="flex items-center gap-1.5">
                    @if(($badges['orders_total'] ?? 0) > 0)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-500 text-white">
                            {{ $badges['orders_total'] > 99 ? '99+' : $badges['orders_total'] }}
                        </span>
                    @endif
                    <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'orders'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="activeDropdown === 'orders' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('seller.orders.index') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.orders.index') && !request('status') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">All Orders</a>
                <a href="{{ route('seller.orders.index', ['status' => 'new']) }}" class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'new' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>New Orders</span>
                    @if(($badges['orders_new'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-danger">{{ $badges['orders_new'] }}</span>
                    @endif
                </a>
                <a href="{{ route('seller.orders.index', ['status' => 'pending']) }}" class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'pending' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>Pending Buyer Response</span>
                    @if(($badges['orders_pending'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">{{ $badges['orders_pending'] }}</span>
                    @endif
                </a>
                <a href="{{ route('seller.orders.index', ['status' => 'to_process']) }}" class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'to_process' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>To Process</span>
                    @if(($badges['orders_to_process'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">{{ $badges['orders_to_process'] }}</span>
                    @endif
                </a>
                <a href="{{ route('seller.orders.index', ['status' => 'to_ship']) }}" class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'to_ship' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>To Ship</span>
                    @if(($badges['orders_to_ship'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800">{{ $badges['orders_to_ship'] }}</span>
                    @endif
                </a>
                <a href="{{ route('seller.orders.index', ['status' => 'shipped']) }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'shipped' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">Shipped</a>
                <a href="{{ route('seller.orders.index', ['status' => 'delivered']) }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'delivered' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">Delivered</a>
                <a href="{{ route('seller.orders.index', ['status' => 'cancelled']) }}" class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'cancelled' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>Cancelled / Returned</span>
                    @if(($badges['orders_disputed'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-danger">{{ $badges['orders_disputed'] }}</span>
                    @endif
                </a>
            </div>
        </div>

        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'sales'; } else { activeDropdown = activeDropdown === 'sales' ? null : 'sales'; }"
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isSalesActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                    :title="!sidebarOpen ? 'Sales & Reports' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isSalesActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Sales &amp; Settlements</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'sales'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'sales' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('seller.settlements.index') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('seller.settlements.index') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">E-Wallet, Payouts &amp; Ledger</a>
                <a href="{{ route('seller.reports.formal-pdf') }}" target="_blank" class="block px-3 py-2 text-xs rounded-lg text-primary font-bold hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Formal PDF Statement &nearr;</a>
            </div>
        </div>

        <a href="{{ route('seller.ratings.index') }}"
            @click="activeDropdown = null"
            class="flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isRatingsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Store & Product Ratings' : ''">
            <div class="flex items-center">
                <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isRatingsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                </svg>
                <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Store &amp; Product Ratings</span>
            </div>
            @if(($badges['unreplied_reviews'] ?? 0) > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500 text-white">
                    {{ $badges['unreplied_reviews'] }}
                </span>
            @endif
        </a>

        <a href="{{ route('seller.messages.seller-messages') }}"
            @click="activeDropdown = null"
            class="flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isMessagesActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Messages' : ''">
            <div class="flex items-center">
                <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isMessagesActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
                <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Messages</span>
            </div>
            @if($messagesBadge > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-500 text-white">
                    {{ $messagesBadge > 99 ? '99+' : $messagesBadge }}
                </span>
            @endif
        </a>

        <a href="{{ route('seller.store-settings') }}"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ request()->routeIs('seller.store-settings*') ? 'text-primary font-bold bg-brand-light/60 shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Store Settings' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ request()->routeIs('seller.store-settings*') ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Store Settings</span>
        </a>
        @endif

        {{-- Outside the seller profile check on purpose: pending or rejected sellers can still contact support. --}}
        <a href="{{ route('seller.help-center.index') }}"
            @click="activeDropdown = null"
            class="flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isHelpCenterActive ? 'text-primary font-bold bg-brand-light/60 shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Help Center' : ''">
            <div class="flex items-center">
                <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isHelpCenterActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Help Center</span>
            </div>
            @if($supportBadge > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-500 text-white">
                    {{ $supportBadge > 99 ? '99+' : $supportBadge }}
                </span>
            @endif
        </a>
    </nav>

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