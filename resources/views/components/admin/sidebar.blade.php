<aside :class="sidebarOpen ? 'w-72' : 'w-20'" class="relative flex flex-col h-screen border-r border-border-subtle shadow-lg transition-all duration-300 ease-in-out bg-gradient-to-b from-surface via-surface to-brand-light/30 flex-shrink-0 z-40">
    
    <button @click="sidebarOpen = !sidebarOpen" class="absolute -right-3.5 top-8 bg-surface border border-border-subtle text-primary rounded-full p-1.5 shadow-md hover:bg-brand-light/50 transition-colors z-50 focus:outline-none cursor-pointer">
        <svg class="w-4 h-4 transition-transform duration-300" :class="!sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <div class="h-20 flex items-center justify-center px-4 border-b border-border-subtle overflow-hidden">
        <a href="{{ route('admin.dashboard') }}"
            @click="if (window.location.href.split('?')[0] === '{{ route('admin.dashboard') }}') { $event.preventDefault(); window.scrollTo({top: 0, behavior: 'smooth'}); }"
            class="flex items-center justify-center h-10 w-full overflow-hidden text-primary transition-colors cursor-pointer">
            <img x-show="sidebarOpen" x-transition.opacity.duration.300ms src="{{ asset('assets/pink-storkia-maximized.webp') }}" alt="Storkia" class="h-8 w-auto" />
            <img x-show="!sidebarOpen" x-cloak src="{{ asset('assets/pink-storkia-minimized.webp') }}" alt="S" class="h-8 w-auto" />
        </a>
    </div>

    <nav x-data="{ 
        activeDropdown: '{{ match(true) {
            request()->is('admin/applications*') => 'applications',
            request()->is('admin/users*') => 'users',
            request()->is('admin/complaints*') => 'complaints',
            request()->is('admin/reports*') => 'reports',
            request()->is('admin/settings*') => 'settings',
            default => null,
        } }}'
    }" class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-1.5 custom-scrollbar">
        <a href="{{ route('admin.dashboard') }}"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-bold group whitespace-nowrap relative text-sm cursor-pointer {{ request()->routeIs('admin.dashboard') ? 'bg-brand-light/60 text-primary shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Dashboard' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Dashboard</span>
        </a>

        @php $isAppsActive = request()->is('admin/applications*') || request()->routeIs('admin.applications.*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'applications'; } else { activeDropdown = activeDropdown === 'applications' ? null : 'applications'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isAppsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
                    :title="!sidebarOpen ? 'Registration Applications' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isAppsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Registration Applications</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'applications'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'applications' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('admin.applications.identity') }}" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">
                    Buyer Applications
                </a>
                <a href="{{ route('admin.applications.sellers') }}"
                    class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('admin.applications.sellers') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Seller Applications
                </a>
                <a href="{{ route('admin.applications.logistics') }}"
                    class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('admin.applications.logistics') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Logistics Applications
                </a>
            </div>
        </div>

        @php $isComplianceActive = request()->is('admin/compliance*') || request()->routeIs('admin.compliance.*'); @endphp
        <a href="{{ route('admin.compliance.index') }}"
            @click="activeDropdown = null"
            class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-bold group whitespace-nowrap text-sm cursor-pointer {{ $isComplianceActive ? 'bg-brand-light/60 text-primary shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Seller Compliance' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isComplianceActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Seller Compliance</span>
        </a>

        @php $isComplaintsActive = request()->is('admin/complaints*') || request()->routeIs('admin.complaints.*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'complaints'; } else { activeDropdown = activeDropdown === 'complaints' ? null : 'complaints'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isComplaintsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
                    :title="!sidebarOpen ? 'Complaints & Disputes' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isComplaintsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Complaints &amp; Disputes</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'complaints'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'complaints' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('admin.complaints.index', ['status' => 'all']) }}" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">All Complaints</a>
                <a href="{{ route('admin.complaints.index', ['status' => 'unresolved']) }}" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Unresolved Disputes</a>
                <a href="{{ route('admin.complaints.index', ['status' => 'resolved']) }}" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Resolved</a>
            </div>
        </div>

        @php $isCommissionsActive = request()->routeIs('admin.commissions.*'); @endphp
        <a href="{{ route('admin.commissions.index') }}"
           @click="activeDropdown = null"
           class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isCommissionsActive ? 'bg-brand-light/60 text-primary font-bold shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
           :title="!sidebarOpen ? 'Commissions' : ''">
            <svg class="w-6 h-6 flex-shrink-0 {{ $isCommissionsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Commissions &amp; Ledger</span>
        </a>
    </nav>

    <div class="p-3 border-t border-border-subtle bg-surface/50 backdrop-blur-sm">
        <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
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