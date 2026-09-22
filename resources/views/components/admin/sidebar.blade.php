<aside :class="sidebarOpen ? 'w-72' : 'w-20'" class="relative flex flex-col h-screen border-r border-border-subtle shadow-lg transition-all duration-300 ease-in-out bg-gradient-to-b from-surface via-surface to-brand-light/30 flex-shrink-0 z-40">
    
    <!-- Minimize/Maximize Toggle Button -->
    <button @click="sidebarOpen = !sidebarOpen" class="absolute -right-3.5 top-8 bg-surface border border-border-subtle text-primary rounded-full p-1.5 shadow-md hover:bg-brand-light/50 transition-colors z-50 focus:outline-none cursor-pointer">
        <svg class="w-4 h-4 transition-transform duration-300" :class="!sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <!-- Logo Header -->
    <div class="h-20 flex items-center justify-center px-4 border-b border-border-subtle overflow-hidden">
        <a href="{{ route('admin.dashboard') }}"
            @click="if (window.location.href.split('?')[0] === '{{ route('admin.dashboard') }}') { $event.preventDefault(); window.scrollTo({top: 0, behavior: 'smooth'}); }"
            class="flex items-center justify-center h-10 w-full overflow-hidden text-primary-dark transition-colors cursor-pointer">
            <!-- Full Logo when open -->
            <img x-show="sidebarOpen" x-transition.opacity.duration.300ms src="{{ asset('assets/storkia-maximized.png') }}" alt="Storkia" class="h-8 w-auto" />
            <!-- Icon/Initial when minimized -->
            <img x-show="!sidebarOpen" x-cloak src="{{ asset('assets/storkia-minimized.png') }}" alt="S" class="h-8 w-auto" />
        </a>
    </div>

    <!-- Navigation Links -->
    <nav x-data="{ 
        activeDropdown: '{{ request()->is('admin/applications*') ? 'applications' : (request()->is('admin/users*') ? 'users' : (request()->is('admin/compliance*') ? 'compliance' : (request()->is('admin/complaints*') ? 'complaints' : (request()->is('admin/reports*') ? 'reports' : (request()->is('admin/settings*') ? 'settings' : null))))) }}' 
    }" class="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-1.5 custom-scrollbar">

        <!-- Dashboard -->
        <a href="{{ route('admin.dashboard') }}" 
           @click="activeDropdown = null" 
           class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-bold group whitespace-nowrap relative text-sm cursor-pointer {{ request()->routeIs('admin.dashboard') ? 'bg-brand-light/60 text-primary shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
           :title="!sidebarOpen ? 'Dashboard' : ''">
            <svg class="w-6 h-6 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Dashboard</span>
        </a>

        <!-- Registration Applications -->
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
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">
                    All Applications
                </a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">
                    Buyer Applications
                </a>
                <a href="{{ route('admin.applications.sellers') }}" 
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('admin.applications.sellers') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Seller Applications
                </a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">
                    Logistics Applications
                </a>
            </div>
        </div>

        <!-- Users -->
        @php $isUsersActive = request()->is('admin/users*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'users'; } else { activeDropdown = activeDropdown === 'users' ? null : 'users'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isUsersActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
                    :title="!sidebarOpen ? 'Users' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isUsersActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Users</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'users'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'users' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Buyers</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Sellers</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Logistics</a>
            </div>
        </div>

        <!-- Seller Compliance -->
        @php $isComplianceActive = request()->is('admin/compliance*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'compliance'; } else { activeDropdown = activeDropdown === 'compliance' ? null : 'compliance'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isComplianceActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
                    :title="!sidebarOpen ? 'Seller Compliance' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isComplianceActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Seller Compliance</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'compliance'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'compliance' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Product Reviews</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Violations</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Warnings</a>
            </div>
        </div>

        <!-- Complaints & Disputes -->
        @php $isComplaintsActive = request()->is('admin/complaints*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'complaints'; } else { activeDropdown = activeDropdown === 'complaints' ? null : 'complaints'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isComplaintsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
                    :title="!sidebarOpen ? 'Complaints & Disputes' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isComplaintsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Complaints & Disputes</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'complaints'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'complaints' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">All Complaints</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Pending</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Under Review</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Resolved</a>
            </div>
        </div>

        <!-- Commissions -->
        <a href="#" @click="activeDropdown = null" class="flex items-center px-3 py-3 text-text-muted hover:bg-brand-light/30 hover:text-primary rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer" :title="!sidebarOpen ? 'Commissions' : ''">
            <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Commissions</span>
        </a>

        <!-- Reports -->
        @php $isReportsActive = request()->is('admin/reports*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'reports'; } else { activeDropdown = activeDropdown === 'reports' ? null : 'reports'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isReportsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
                    :title="!sidebarOpen ? 'Reports' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isReportsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Reports</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'reports'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'reports' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Sales Summary</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Commission Report</a>
            </div>
        </div>

        <!-- Announcements -->
        <a href="#" @click="activeDropdown = null" class="flex items-center px-3 py-3 text-text-muted hover:bg-brand-light/30 hover:text-primary rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer" :title="!sidebarOpen ? 'Announcements' : ''">
            <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c.41 0 .672-.425.534-.813a4.001 4.001 0 00-7.625 2.138A4.001 4.001 0 005.436 13.683z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Announcements</span>
        </a>

        <!-- Platform Settings -->
        @php $isSettingsActive = request()->is('admin/settings*'); @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'settings'; } else { activeDropdown = activeDropdown === 'settings' ? null : 'settings'; }" 
                    class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isSettingsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}" 
                    :title="!sidebarOpen ? 'Platform Settings' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isSettingsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Platform Settings</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'settings'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'settings' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Policies</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">Categories</a>
                <a href="#" class="block px-3 py-2 text-xs rounded-lg text-text-muted hover:text-text-main hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">System Settings</a>
            </div>
        </div>

        <!-- Chat / Messages -->
        <a href="#" @click="activeDropdown = null" class="flex items-center px-3 py-3 text-text-muted hover:bg-brand-light/30 hover:text-primary rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer" :title="!sidebarOpen ? 'Chat / Messages' : ''">
            <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Chat / Messages</span>
        </a>

        <!-- Account -->
        <a href="#" @click="activeDropdown = null" class="flex items-center px-3 py-3 text-text-muted hover:bg-brand-light/30 hover:text-primary rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer" :title="!sidebarOpen ? 'Account' : ''">
            <svg class="w-6 h-6 flex-shrink-0 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Account</span>
        </a>
    </nav>

    <!-- Logout Footer -->
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