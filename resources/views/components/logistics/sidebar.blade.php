<aside :class="sidebarOpen ? 'w-72' : 'w-20'" class="relative flex flex-col h-screen border-r border-border-subtle shadow-lg transition-all duration-300 ease-in-out bg-gradient-to-b from-surface via-surface to-brand-light/30 flex-shrink-0 z-40">
    
    <!-- Minimize/Maximize Toggle Button -->
    <button @click="sidebarOpen = !sidebarOpen" class="absolute -right-3.5 top-8 bg-surface border border-border-subtle text-primary rounded-full p-1.5 shadow-md hover:bg-brand-light/50 transition-colors z-50 focus:outline-none cursor-pointer">
        <svg class="w-4 h-4 transition-transform duration-300" :class="!sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <!-- Logo Header -->
    <div class="h-20 flex items-center justify-center px-4 border-b border-border-subtle overflow-hidden">
        <a href="{{ route('logistics.logistics-dashboard') }}"
            @click="if (window.location.href.split('?')[0] === '{{ route('logistics.logistics-dashboard') }}') { $event.preventDefault(); window.scrollTo({top: 0, behavior: 'smooth'}); }"
            class="flex items-center justify-center h-10 w-full overflow-hidden text-primary-dark transition-colors cursor-pointer">
            <img x-show="sidebarOpen" x-transition.opacity.duration.300ms src="{{ asset('assets/storkia-maximized.png') }}" alt="Storkia" class="h-8 w-auto" />
            <img x-show="!sidebarOpen" x-cloak src="{{ asset('assets/storkia-minimized.png') }}" alt="S" class="h-8 w-auto" />
        </a>
    </div>

    <!-- Navigation Links -->
    <nav x-data="{ activeDropdown: null }" class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-1.5 custom-scrollbar">

        <!-- Dashboard Link -->
        <a href="{{ route('logistics.logistics-dashboard') }}" 
           @click="activeDropdown = null" 
           class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-bold group whitespace-nowrap relative text-sm cursor-pointer {{ request()->routeIs('logistics.logistics-dashboard') ? 'bg-brand-light/60 text-primary shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
           :title="!sidebarOpen ? 'Dashboard' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Dashboard</span>
        </a>

        <!-- Orders -->
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'orders'; } else { activeDropdown = activeDropdown === 'orders' ? null : 'orders'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer text-text-muted hover:bg-brand-light/30 hover:text-primary" 
                    :title="!sidebarOpen ? 'Orders' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Orders</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'orders'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'orders' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">All Orders</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Pending Orders</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Processing</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Ready for Shipment</a>
            </div>
        </div>

        <!-- Shipments -->
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'shipments'; } else { activeDropdown = activeDropdown === 'shipments' ? null : 'shipments'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer text-text-muted hover:bg-brand-light/30 hover:text-primary" 
                    :title="!sidebarOpen ? 'Shipments' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Shipments</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'shipments'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'shipments' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">All Shipments</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">To Pick Up</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">In Transit</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Out for Delivery</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Delivered</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Failed / Returned</a>
            </div>
        </div>

        <!-- Deliveries -->
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'deliveries'; } else { activeDropdown = activeDropdown === 'deliveries' ? null : 'deliveries'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer text-text-muted hover:bg-brand-light/30 hover:text-primary" 
                    :title="!sidebarOpen ? 'Deliveries' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Deliveries</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'deliveries'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'deliveries' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Delivery Management</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Delivery Tracking</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Delivery Routes</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Delivery History</a>
            </div>
        </div>

        <!-- Riders / Drivers -->
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'riders'; } else { activeDropdown = activeDropdown === 'riders' ? null : 'riders'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer text-text-muted hover:bg-brand-light/30 hover:text-primary" 
                    :title="!sidebarOpen ? 'Riders / Drivers' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Riders / Drivers</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'riders'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'riders' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">All Riders</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Available</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">On Delivery</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Rider Performance</a>
            </div>
        </div>

        <!-- Warehouses -->
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'warehouses'; } else { activeDropdown = activeDropdown === 'warehouses' ? null : 'warehouses'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer text-text-muted hover:bg-brand-light/30 hover:text-primary" 
                    :title="!sidebarOpen ? 'Warehouses' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Warehouses</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'warehouses'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'warehouses' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">All Warehouses</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Stock Transfers</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Warehouse Operations</a>
            </div>
        </div>

        <!-- Inventory -->
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'inventory'; } else { activeDropdown = activeDropdown === 'inventory' ? null : 'inventory'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer text-text-muted hover:bg-brand-light/30 hover:text-primary" 
                    :title="!sidebarOpen ? 'Inventory' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Inventory</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'inventory'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'inventory' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Inventory Overview</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Low Stock</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Stock Movement</a>
            </div>
        </div>

        <!-- Returns -->
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'returns'; } else { activeDropdown = activeDropdown === 'returns' ? null : 'returns'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer text-text-muted hover:bg-brand-light/30 hover:text-primary" 
                    :title="!sidebarOpen ? 'Returns' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Returns</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'returns'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'returns' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Return Requests</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">To Receive</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Returned Items</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Refund Processing</a>
            </div>
        </div>

        <!-- Reports -->
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'reports'; } else { activeDropdown = activeDropdown === 'reports' ? null : 'reports'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer text-text-muted hover:bg-brand-light/30 hover:text-primary" 
                    :title="!sidebarOpen ? 'Reports' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Reports</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'reports'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'reports' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Delivery Reports</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Shipment Reports</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Rider Reports</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Logistics Performance</a>
            </div>
        </div>

        <!-- Settings -->
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'settings'; } else { activeDropdown = activeDropdown === 'settings' ? null : 'settings'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer text-text-muted hover:bg-brand-light/30 hover:text-primary" 
                    :title="!sidebarOpen ? 'Settings' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Settings</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'settings'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'settings' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Delivery Zones</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Shipping Rates</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Delivery Methods</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Logistics Settings</a>
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