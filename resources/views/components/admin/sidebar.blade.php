@php
    $badges = $adminSidebarBadges ?? [
        'apps_identity'  => 0,
        'apps_sellers'   => 0,
        'apps_logistics' => 0,
        'apps_total'     => 0,
        'appeals'        => 0,
        'compliance'     => 0,
        'complaints'     => 0,
        'returns'        => 0,
        'seller_reports' => 0,
        'commissions'    => 0,
    ];

    // Help Center counter from ChatServiceProvider: conversations holding a member message nobody on the team has read yet.
    $chatBadges = $chatBadges ?? ['messages' => 0, 'support' => 0, 'total' => 0];
    $helpCenterBadge = (int) ($chatBadges['support'] ?? 0);
@endphp

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
            request()->is('admin/applications*') || request()->routeIs('admin.applications.*') => 'applications',
            request()->is('admin/accounts*') || request()->routeIs('admin.accounts.*') => 'accounts',
            request()->is('admin/complaints*') || request()->routeIs('admin.complaints.*') || request()->is('admin/seller-reports*') || request()->routeIs('admin.seller-reports.*') => 'complaints',
            request()->is('admin/reports*') || request()->routeIs('admin.reports.*') => 'reports',
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
                     class="relative flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isAppsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                     :title="!sidebarOpen ? 'Registration Applications' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isAppsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Registration Applications</span>
                </div>
                <div class="flex items-center gap-1.5">
                    @if(($badges['apps_total'] ?? 0) > 0)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-500 text-white">
                            {{ $badges['apps_total'] > 99 ? '99+' : $badges['apps_total'] }}
                        </span>
                    @endif
                    <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'applications'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="activeDropdown === 'applications' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('admin.applications.identity') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('admin.applications.identity') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>Buyer Applications</span>
                    @if(($badges['apps_identity'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-danger">{{ $badges['apps_identity'] }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.applications.sellers') }}"
                    class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('admin.applications.sellers') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>Seller Applications</span>
                    @if(($badges['apps_sellers'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-danger">{{ $badges['apps_sellers'] }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.applications.logistics') }}"
                    class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ request()->routeIs('admin.applications.logistics') ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>Logistics Applications</span>
                    @if(($badges['apps_logistics'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-danger">{{ $badges['apps_logistics'] }}</span>
                    @endif
                </a>
            </div>
        </div>

        @php
            $isAccountsActive = request()->is('admin/accounts*') || request()->routeIs('admin.accounts.*');
            $currentAccountRole = request()->query('role', 'Buyer');
        @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'accounts'; } else { activeDropdown = activeDropdown === 'accounts' ? null : 'accounts'; }"
                     class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isAccountsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                     :title="!sidebarOpen ? 'Account Management' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isAccountsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Account Management</span>
                </div>
                <div class="flex items-center gap-1.5">
                    @if(($badges['appeals'] ?? 0) > 0)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500 text-white">
                            {{ $badges['appeals'] }}
                        </span>
                    @endif
                    <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'accounts'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="activeDropdown === 'accounts' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('admin.accounts.index', ['role' => 'Buyer']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isAccountsActive && $currentAccountRole === 'Buyer' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Buyers Directory
                </a>
                <a href="{{ route('admin.accounts.index', ['role' => 'Seller']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isAccountsActive && $currentAccountRole === 'Seller' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Sellers Directory
                </a>
                <a href="{{ route('admin.accounts.index', ['role' => 'Logistics']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isAccountsActive && $currentAccountRole === 'Logistics' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Logistics Hubs
                </a>
                <a href="{{ route('admin.accounts.index', ['role' => 'Courier']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isAccountsActive && $currentAccountRole === 'Courier' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    First-Mile Couriers
                </a>
                <a href="{{ route('admin.accounts.index', ['role' => 'Rider']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isAccountsActive && $currentAccountRole === 'Rider' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Last-Mile Riders
                </a>
                <a href="{{ route('admin.accounts.index', ['role' => 'Appeals']) }}"
                   class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isAccountsActive && $currentAccountRole === 'Appeals' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>3-Strike Ban Appeals</span>
                    @if(($badges['appeals'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">{{ $badges['appeals'] }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.accounts.index', ['role' => 'Archived']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isAccountsActive && $currentAccountRole === 'Archived' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Archived Accounts
                </a>
            </div>
        </div>

        @php $isComplianceActive = request()->is('admin/compliance*') || request()->routeIs('admin.compliance.*'); @endphp
        <a href="{{ route('admin.compliance.index') }}"
            @click="activeDropdown = null"
            class="flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 font-bold group whitespace-nowrap text-sm cursor-pointer {{ $isComplianceActive ? 'bg-brand-light/60 text-primary shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
            :title="!sidebarOpen ? 'Seller Compliance' : ''">
            <div class="flex items-center">
                <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isComplianceActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Seller Compliance</span>
            </div>
            @if(($badges['compliance'] ?? 0) > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-500 text-white">
                    {{ $badges['compliance'] > 99 ? '99+' : $badges['compliance'] }}
                </span>
            @endif
        </a>

        @php
            $isComplaintsActive = request()->is('admin/complaints*') || request()->routeIs('admin.complaints.*');
            $isSellerReportsActive = request()->is('admin/seller-reports*') || request()->routeIs('admin.seller-reports.*');
            // The parent button is lit for either page; the Complaints sub-links only for the Complaints pages.
            $isComplaintsGroupActive = $isComplaintsActive || $isSellerReportsActive;
            $complaintsGroupBadge = (int) ($badges['complaints'] ?? 0) + (int) ($badges['seller_reports'] ?? 0);
            $currentComplaintStatus = request()->query('status', 'unresolved');
        @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'complaints'; } else { activeDropdown = activeDropdown === 'complaints' ? null : 'complaints'; }"
                     class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isComplaintsGroupActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                     :title="!sidebarOpen ? 'Complaints & Disputes' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isComplaintsGroupActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Complaints &amp; Disputes</span>
                </div>
                <div class="flex items-center gap-1.5">
                    @if($complaintsGroupBadge > 0)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-500 text-white">
                            {{ $complaintsGroupBadge > 99 ? '99+' : $complaintsGroupBadge }}
                        </span>
                    @endif
                    <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'complaints'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>
            <div x-show="activeDropdown === 'complaints' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('admin.complaints.index', ['status' => 'all']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isComplaintsActive && $currentComplaintStatus === 'all' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    All Complaints
                </a>
                <a href="{{ route('admin.complaints.index', ['status' => 'unresolved']) }}"
                   class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isComplaintsActive && $currentComplaintStatus === 'unresolved' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>Unresolved Disputes</span>
                    @if(($badges['complaints'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-danger">{{ $badges['complaints'] }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.complaints.index', ['status' => 'resolved']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isComplaintsActive && $currentComplaintStatus === 'resolved' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Resolved
                </a>
                <a href="{{ route('admin.seller-reports.index') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isSellerReportsActive ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    <span>Seller Reports</span>
                    @if(($badges['seller_reports'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-danger">{{ $badges['seller_reports'] }}</span>
                    @endif
                </a>
            </div>
        </div>

        @php $isHelpCenterActive = request()->is('admin/help-center*') || request()->routeIs('admin.help-center.*'); @endphp
        <a href="{{ route('admin.help-center.index') }}"
           @click="activeDropdown = null"
           class="flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isHelpCenterActive ? 'bg-brand-light/60 text-primary font-bold shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
           :title="!sidebarOpen ? 'Help Center' : ''">
            <div class="flex items-center">
                <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isHelpCenterActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Help Center</span>
            </div>
            @if($helpCenterBadge > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-500 text-white">
                    {{ $helpCenterBadge > 99 ? '99+' : $helpCenterBadge }}
                </span>
            @endif
        </a>

        @php $isReturnsActive = request()->is('admin/returns*') || request()->routeIs('admin.returns.*'); @endphp
        <a href="{{ route('admin.returns.index') }}"
           @click="activeDropdown = null"
           class="flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isReturnsActive ? 'bg-brand-light/60 text-primary font-bold shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
           :title="!sidebarOpen ? 'Returns & Refunds' : ''">
            <div class="flex items-center">
                <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isReturnsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
                <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Returns &amp; Refunds</span>
            </div>
            @if(($badges['returns'] ?? 0) > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-500 text-white">
                    {{ $badges['returns'] > 99 ? '99+' : $badges['returns'] }}
                </span>
            @endif
        </a>

        @php $isCommissionsActive = request()->routeIs('admin.commissions.*'); @endphp
        <a href="{{ route('admin.commissions.index') }}"
           @click="activeDropdown = null"
           class="flex items-center justify-between px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isCommissionsActive ? 'bg-brand-light/60 text-primary font-bold shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
           :title="!sidebarOpen ? 'Commissions & Vouchers' : ''">
            <div class="flex items-center">
                <svg class="w-6 h-6 flex-shrink-0 {{ $isCommissionsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Commissions &amp; Vouchers</span>
            </div>
            @if(($badges['commissions'] ?? 0) > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-500 text-white">
                    {{ $badges['commissions'] > 99 ? '99+' : $badges['commissions'] }}
                </span>
            @endif
        </a>

        @php
            $isReportsActive = request()->is('admin/reports*') || request()->routeIs('admin.reports.*');
            $currentReportRoleTab = request()->query('role_tab', 'sellers');
        @endphp
        <div>
            <button @click="if(!sidebarOpen) { sidebarOpen = true; activeDropdown = 'reports'; } else { activeDropdown = activeDropdown === 'reports' ? null : 'reports'; }"
                     class="flex items-center justify-between w-full px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isReportsActive ? 'text-primary font-bold bg-brand-light/20' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
                     :title="!sidebarOpen ? 'Sales & Role Reports' : ''">
                <div class="flex items-center">
                    <svg class="w-6 h-6 flex-shrink-0 transition-colors {{ $isReportsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Sales &amp; Role Reports</span>
                </div>
                <svg x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200 text-text-muted group-hover:text-primary" :class="{'rotate-180': activeDropdown === 'reports'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="activeDropdown === 'reports' && sidebarOpen" x-collapse x-cloak class="ml-6 pl-4 my-1 border-l-2 border-border-subtle/70 space-y-1">
                <a href="{{ route('admin.reports.sales', ['role_tab' => 'sellers']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isReportsActive && $currentReportRoleTab === 'sellers' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Seller Stores Report
                </a>
                <a href="{{ route('admin.reports.sales', ['role_tab' => 'logistics']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isReportsActive && $currentReportRoleTab === 'logistics' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Logistics Hubs Report
                </a>
                <a href="{{ route('admin.reports.sales', ['role_tab' => 'couriers']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isReportsActive && $currentReportRoleTab === 'couriers' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    First-Mile Couriers Report
                </a>
                <a href="{{ route('admin.reports.sales', ['role_tab' => 'riders']) }}"
                   class="block px-3 py-2 text-xs rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $isReportsActive && $currentReportRoleTab === 'riders' ? 'bg-primary/10 text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-brand-light/25' }}">
                    Last-Mile Riders Report
                </a>
                <a href="{{ route('admin.reports.formal-pdf') }}"
                   target="_blank"
                   class="block px-3 py-2 text-xs rounded-lg text-primary font-bold hover:bg-brand-light/25 transition-all whitespace-nowrap cursor-pointer">
                    Formal Printable PDF Statement &nearr;
                </a>
            </div>
        </div>

        @php $isAuditLogsActive = request()->routeIs('admin.audit-logs.*'); @endphp
        <a href="{{ route('admin.audit-logs.index') }}"
           @click="activeDropdown = null"
           class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isAuditLogsActive ? 'bg-brand-light/60 text-primary font-bold shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
           :title="!sidebarOpen ? 'Audit & Security Logs' : ''">
            <svg class="w-6 h-6 flex-shrink-0 {{ $isAuditLogsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Audit &amp; Security Logs</span>
        </a>

        @php $isSettingsActive = request()->routeIs('admin.settings.*'); @endphp
        <a href="{{ route('admin.settings.index') }}"
           @click="activeDropdown = null"
           class="flex items-center px-3 py-3 rounded-xl transition-all duration-200 font-medium group whitespace-nowrap text-sm cursor-pointer {{ $isSettingsActive ? 'bg-brand-light/60 text-primary font-bold shadow-xs' : 'text-text-muted hover:bg-brand-light/30 hover:text-primary' }}"
           :title="!sidebarOpen ? 'Platform & Hero Settings' : ''">
            <svg class="w-6 h-6 flex-shrink-0 {{ $isSettingsActive ? 'text-primary' : 'text-text-muted group-hover:text-primary' }} transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span x-show="sidebarOpen" x-transition.opacity.duration.300ms class="ml-3">Platform &amp; Hero Settings</span>
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