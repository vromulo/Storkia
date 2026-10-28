@php
    $user = auth()->user();
    $hasProfile = $user && $user->logisticsProfile !== null;
    $isMainLogistics = $user && $user->isMainLogisticsAccount();
    $isRiderApplicationRoute = request()->routeIs('logistics.rider-applications*');
    $isCourierRoute = request()->routeIs('logistics.couriers*');
    $isActiveRidersRoute = request()->routeIs('logistics.riders*');
    $isEmployeesRoute = request()->routeIs('logistics.employees*') || request()->routeIs('rider.register');
    $isRiderRoute = $isRiderApplicationRoute || $isCourierRoute || $isActiveRidersRoute || $isEmployeesRoute;
    $isBranchesRoute = request()->routeIs('logistics.branches*');
    $isShipmentsRoute = request()->routeIs('logistics.shipments*');
    $isFinanceRoute = request()->routeIs('logistics.finance*');
@endphp

<aside :class="sidebarOpen ? 'w-72' : 'w-20'" class="relative flex flex-col h-screen border-r border-border-subtle shadow-lg transition-all duration-300 ease-in-out bg-gradient-to-b from-surface via-surface to-brand-light/30 flex-shrink-0 z-40">
    
    <button @click="sidebarOpen = !sidebarOpen" class="absolute -right-3.5 top-8 bg-surface border border-border-subtle text-primary rounded-full p-1.5 shadow-md hover:bg-brand-light/50 transition-colors z-50 focus:outline-none cursor-pointer">
        <svg class="w-4 h-4 transition-transform duration-300" :class="!sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <div class="h-20 flex items-center justify-center px-4 border-b border-border-subtle overflow-hidden">
        <a href="{{ route('logistics.logistics-dashboard') }}"
            @click="if (window.location.href.split('?')[0] === '{{ route('logistics.logistics-dashboard') }}') { $event.preventDefault(); window.scrollTo({top: 0, behavior: 'smooth'}); }"
            class="flex items-center justify-center h-10 w-full overflow-hidden text-primary-dark transition-colors cursor-pointer">
            <img x-show="sidebarOpen" x-transition.opacity.duration.300ms src="{{ asset('assets/pink-storkia-maximized.webp') }}" alt="Storkia" class="h-8 w-auto object-contain" />
            <img x-show="!sidebarOpen" x-cloak src="{{ asset('assets/pink-storkia-minimized.webp') }}" alt="S" class="h-8 w-auto object-contain" />
        </a>
    </div>

    <nav x-data="{ activeDropdown: '{{ $isRiderRoute ? 'riders' : ($isShipmentsRoute ? 'shipments' : ($isFinanceRoute ? 'finance' : '')) }}' }" class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-1.5 custom-scrollbar">
        <a href="{{ route('logistics.logistics-dashboard') }}"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-bold group whitespace-nowrap relative text-sm cursor-pointer {{ request()->routeIs('logistics.logistics-dashboard') ? 'bg-brand-light/60 text-primary shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Dashboard' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Dashboard</span>
        </a>

        @if($hasProfile)
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'shipments'; } else { activeDropdown = activeDropdown === 'shipments' ? null : 'shipments'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isShipmentsRoute ? 'bg-brand-light/60 text-primary font-bold shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
                    :title="!sidebarOpen ? 'Shipments' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 {{ $isShipmentsRoute ? 'text-primary' : 'text-text-muted group-hover:text-primary' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Shipments & Dispatch</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'shipments'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'shipments' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('logistics.shipments.index') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('logistics.shipments.index') && !request('status') ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">All Shipments</a>
                <a href="{{ route('logistics.shipments.index', ['status' => 'to_pickup']) }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'to_pickup' ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">To Pick Up / Drop-Off</a>
                <a href="{{ route('logistics.shipments.index', ['status' => 'in_transit_hub']) }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'in_transit_hub' ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">In Transit to Hub</a>
                <a href="{{ route('logistics.shipments.index', ['status' => 'at_hub']) }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'at_hub' ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">At Sorting Hub</a>
                <a href="{{ route('logistics.shipments.index', ['status' => 'out_for_delivery']) }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'out_for_delivery' ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">Out for Delivery</a>
                <a href="{{ route('logistics.shipments.index', ['status' => 'delivered']) }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'delivered' ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">Delivered</a>
                <a href="{{ route('logistics.shipments.index', ['status' => 'failed_returned']) }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request('status') === 'failed_returned' ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">Failed / Returned</a>
            </div>
        </div>

        @if($isMainLogistics)
        <a href="{{ route('logistics.branches.index') }}"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isBranchesRoute ? 'text-primary font-bold bg-brand-light/60 shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Branch Hub Management' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isBranchesRoute ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Branch Hub Management</span>
        </a>
        @endif

        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'riders'; } else { activeDropdown = activeDropdown === 'riders' ? null : 'riders'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isRiderRoute ? 'bg-brand-light/60 text-primary font-bold shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
                    :title="!sidebarOpen ? 'Employee Management' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 {{ $isRiderRoute ? 'text-primary' : 'text-text-muted group-hover:text-primary' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Employee Management</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 {{ $isRiderRoute ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" :class="{'rotate-180': activeDropdown === 'riders'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'riders' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('logistics.employees.index') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('logistics.employees*') ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">Hub & Barangay Assignments</a>
                <a href="{{ route('logistics.couriers.create') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('logistics.couriers.create') ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">Create Courier Account</a>
                <a href="{{ route('rider.register') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('rider.register') || request()->routeIs('logistics.riders.create') ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">Create Rider Account</a>
                <a href="{{ route('logistics.couriers.index') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('logistics.couriers.index') ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">All Couriers (First-Mile)</a>
                <a href="{{ route('logistics.riders.active') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isActiveRidersRoute ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">All Approved Riders</a>
                <a href="{{ route('logistics.rider-applications.index') }}" class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isRiderApplicationRoute ? 'text-primary font-bold bg-brand-light/40' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">Rider Directory & Docs</a>
            </div>
        </div>

        <a href="{{ route('logistics.finance.index') }}"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isFinanceRoute ? 'text-primary font-bold bg-brand-light/60 shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'COD & Settlements' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isFinanceRoute ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">COD & Settlements</span>
        </a>

        @if($isMainLogistics)
        <a href="{{ route('logistics.hub-settings') }}"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ request()->routeIs('logistics.hub-settings*') ? 'text-primary font-bold bg-brand-light/60 shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Hub Settings' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ request()->routeIs('logistics.hub-settings*') ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Hub Settings</span>
        </a>
        @endif
        @endif
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