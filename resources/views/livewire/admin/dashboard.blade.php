<div wire:poll.15s class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-8 space-y-8 font-sans">
    
    <!-- Header with Dynamic Livewire Filter Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-primary-dark tracking-tight font-serif">
                    Administrative Overview
                </h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-surface-subtle text-text-muted border border-border-subtle">
                    <span class="w-1.5 h-1.5 rounded-full bg-success animate-pulse"></span>
                    Live Sync
                </span>
            </div>
            <p class="text-xs sm:text-sm text-text-muted mt-1">
                Real-time marketplace operations, catalog compliance, and onboarding pipeline.
            </p>
        </div>

        <!-- Minimalist Pill Date Filter -->
        <div class="inline-flex p-1 bg-surface-subtle border border-border-subtle rounded-2xl shadow-xs self-start md:self-auto">
            @php
                $periods = [
                    'all'    => 'All Time',
                    'today'  => 'Today',
                    '7days'  => '7 Days',
                    '30days' => '30 Days',
                    'year'   => 'This Year',
                ];
            @endphp
            @foreach($periods as $key => $label)
                <button 
                    type="button" 
                    wire:click="setPeriod('{{ $key }}')"
                    class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer {{ $period === $key ? 'bg-surface text-text-main shadow-xs border border-border-subtle' : 'text-text-muted hover:text-text-main' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Top KPI Performance Cards (Minimalist Border & Card Treatment) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Total Registered Users -->
        <div class="p-6 bg-surface rounded-2xl border border-border-subtle shadow-xs hover:border-text-muted/40 transition-colors">
            <div class="flex items-center justify-between text-text-muted mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider">User Registrations</span>
                <svg class="w-5 h-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-3xl font-extrabold text-text-main tracking-tight">{{ number_format($totalUsers) }}</p>
                <span class="text-xs text-text-muted font-medium">registered</span>
            </div>
            <div class="mt-4 pt-3 border-t border-border-subtle flex items-center justify-between text-[11px] text-text-muted">
                <span>{{ $roleBreakdown['buyers'] }} Buyers</span>
                <span>&bull;</span>
                <span>{{ $roleBreakdown['sellers'] }} Sellers</span>
                <span>&bull;</span>
                <span>{{ $roleBreakdown['logistics'] }} Hubs</span>
            </div>
        </div>

        <!-- Marketplace Volume or Active Listings -->
        @if($orderMetrics['has_orders'])
            <div class="p-6 bg-surface rounded-2xl border border-border-subtle shadow-xs hover:border-text-muted/40 transition-colors">
                <div class="flex items-center justify-between text-text-muted mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Gross Volume (GMV)</span>
                    <span class="text-xs font-bold text-text-main">₱</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <p class="text-3xl font-extrabold text-text-main tracking-tight">₱{{ number_format($orderMetrics['gross_volume'], 2) }}</p>
                </div>
                <div class="mt-4 pt-3 border-t border-border-subtle flex items-center justify-between text-[11px] text-text-muted">
                    <span>{{ number_format($orderMetrics['total_orders']) }} total orders</span>
                    <span class="text-success font-semibold">Active</span>
                </div>
            </div>
        @else
            <!-- Catalog Size Metric -->
            <div class="p-6 bg-surface rounded-2xl border border-border-subtle shadow-xs hover:border-text-muted/40 transition-colors">
                <div class="flex items-center justify-between text-text-muted mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Catalog Listings</span>
                    <svg class="w-5 h-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="flex items-baseline gap-2">
                    <p class="text-3xl font-extrabold text-text-main tracking-tight">{{ number_format($totalProducts) }}</p>
                    <span class="text-xs text-text-muted font-medium">products</span>
                </div>
                <div class="mt-4 pt-3 border-t border-border-subtle flex items-center justify-between text-[11px] text-text-muted">
                    <span>{{ $productsInPeriod }} added in filter</span>
                    <span class="text-text-main font-semibold">All Categories</span>
                </div>
            </div>
        @endif

        <!-- Pending Catalog Compliance -->
        <div class="p-6 bg-surface rounded-2xl border border-border-subtle shadow-xs hover:border-text-muted/40 transition-colors">
            <div class="flex items-center justify-between text-text-muted mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider">Catalog Reviews</span>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $pendingComplianceReviews > 0 ? 'bg-warning/15 text-warning' : 'bg-surface-subtle text-text-muted' }}">
                    {{ $pendingComplianceReviews }} pending
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-3xl font-extrabold text-text-main tracking-tight">{{ $pendingComplianceReviews }}</p>
                <span class="text-xs text-text-muted font-medium">needs review</span>
            </div>
            <div class="mt-4 pt-3 border-t border-border-subtle flex items-center justify-between text-[11px]">
                <a href="{{ route('admin.compliance.index') }}" class="text-primary hover:underline font-bold inline-flex items-center gap-1">
                    Review listings &rarr;
                </a>
            </div>
        </div>

        <!-- Pending Onboarding Applications -->
        <div class="p-6 bg-surface rounded-2xl border border-border-subtle shadow-xs hover:border-text-muted/40 transition-colors">
            <div class="flex items-center justify-between text-text-muted mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider">Onboarding Queue</span>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ ($pendingSellerApps + $pendingLogisticsApps + $pendingIdentityVerifs) > 0 ? 'bg-primary/10 text-primary' : 'bg-surface-subtle text-text-muted' }}">
                    {{ $pendingSellerApps + $pendingLogisticsApps + $pendingIdentityVerifs }} total
                </span>
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-3xl font-extrabold text-text-main tracking-tight">{{ $pendingSellerApps + $pendingLogisticsApps + $pendingIdentityVerifs }}</p>
                <span class="text-xs text-text-muted font-medium">unprocessed</span>
            </div>
            <div class="mt-4 pt-3 border-t border-border-subtle flex items-center justify-between text-[11px] text-text-muted">
                <span>{{ $pendingSellerApps }} Sellers</span>
                <span>&bull;</span>
                <span>{{ $pendingLogisticsApps }} Hubs</span>
                <span>&bull;</span>
                <span>{{ $pendingIdentityVerifs }} IDs</span>
            </div>
        </div>
    </div>

    <!-- Operational Queues requiring immediate Admin attention -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-text-main uppercase tracking-wider">
                Action Required Queue
            </h2>
            <span class="text-xs text-text-muted">Direct routing into administrative inspection workflows</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            
            <!-- Seller Applications Card -->
            <a href="{{ route('admin.applications.sellers') }}" class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs hover:border-primary/50 transition-all group flex items-start justify-between">
                <div>
                    <span class="text-xs font-bold text-text-muted uppercase tracking-wider">Merchant Onboarding</span>
                    <h3 class="text-lg font-bold text-text-main group-hover:text-primary transition-colors mt-1">Seller Applications</h3>
                    <p class="text-xs text-text-muted mt-1 leading-relaxed">Permit and tax certificate verification for new store creators.</p>
                </div>
                <span class="px-3 py-1 rounded-xl text-xs font-bold shrink-0 ml-4 {{ $pendingSellerApps > 0 ? 'bg-primary text-surface' : 'bg-surface-subtle text-text-muted' }}">
                    {{ $pendingSellerApps }}
                </span>
            </a>

            <!-- Logistics Applications Card -->
            <a href="{{ route('admin.applications.logistics') }}" class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs hover:border-primary/50 transition-all group flex items-start justify-between">
                <div>
                    <span class="text-xs font-bold text-text-muted uppercase tracking-wider">Logistics Dispatch</span>
                    <h3 class="text-lg font-bold text-text-main group-hover:text-primary transition-colors mt-1">Sorting Center Hubs</h3>
                    <p class="text-xs text-text-muted mt-1 leading-relaxed">Facility verification and driver handler hub onboarding requests.</p>
                </div>
                <span class="px-3 py-1 rounded-xl text-xs font-bold shrink-0 ml-4 {{ $pendingLogisticsApps > 0 ? 'bg-primary text-surface' : 'bg-surface-subtle text-text-muted' }}">
                    {{ $pendingLogisticsApps }}
                </span>
            </a>

            <!-- Buyer Identity Card -->
            <a href="{{ route('admin.applications.identity') }}" class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs hover:border-primary/50 transition-all group flex items-start justify-between">
                <div>
                    <span class="text-xs font-bold text-text-muted uppercase tracking-wider">Trust & Safety</span>
                    <h3 class="text-lg font-bold text-text-main group-hover:text-primary transition-colors mt-1">Identity Verifications</h3>
                    <p class="text-xs text-text-muted mt-1 leading-relaxed">Government ID validation for customer verification and trust score.</p>
                </div>
                <span class="px-3 py-1 rounded-xl text-xs font-bold shrink-0 ml-4 {{ $pendingIdentityVerifs > 0 ? 'bg-primary text-surface' : 'bg-surface-subtle text-text-muted' }}">
                    {{ $pendingIdentityVerifs }}
                </span>
            </a>
        </div>
    </div>

    <!-- Real-time Activity Panels (2 Columns) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Recent Seller Submissions -->
        <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle flex items-center justify-between bg-surface-subtle">
                <div>
                    <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Recent Merchant Applications</h3>
                    <p class="text-[11px] text-text-muted mt-0.5">Latest sellers registered in system</p>
                </div>
                <a href="{{ route('admin.applications.sellers') }}" class="text-xs font-bold text-primary hover:underline">
                    View All
                </a>
            </div>

            <div class="divide-y divide-border-subtle">
                @forelse($recentSellerApps as $app)
                    <div class="p-4 flex items-center justify-between hover:bg-surface-subtle transition-colors">
                        <div>
                            <p class="text-sm font-bold text-text-main">{{ $app->business_name }}</p>
                            <p class="text-xs text-text-muted">{{ $app->user?->first_name }} {{ $app->user?->last_name }} &bull; {{ $app->line_of_business ?? 'Seller' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold capitalize 
                                {{ $app->status === 'approved' ? 'bg-success/15 text-success' : ($app->status === 'rejected' ? 'bg-danger/15 text-danger' : 'bg-warning/15 text-warning') }}">
                                {{ $app->status }}
                            </span>
                            <span class="block text-[10px] text-text-muted mt-1">{{ $app->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-text-muted">
                        No recent merchant applications found.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Listing Compliance Queue -->
        <div class="bg-surface rounded-2xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle flex items-center justify-between bg-surface-subtle">
                <div>
                    <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Catalog Compliance Feed</h3>
                    <p class="text-[11px] text-text-muted mt-0.5">Recent products submitted for review</p>
                </div>
                <a href="{{ route('admin.compliance.index') }}" class="text-xs font-bold text-primary hover:underline">
                    View All
                </a>
            </div>

            <div class="divide-y divide-border-subtle">
                @forelse($recentCompliance as $item)
                    <div class="p-4 flex items-center justify-between hover:bg-surface-subtle transition-colors">
                        <div class="flex items-center gap-3">
                            @php
                                $firstPic = (!empty($item->product?->pictures) && is_array($item->product->pictures))
                                    ? asset('storage/' . $item->product->pictures[0])
                                    : null;
                            @endphp
                            @if($firstPic)
                                <img src="{{ $firstPic }}" class="w-10 h-10 rounded-xl object-cover border border-border-subtle shrink-0">
                            @else
                                <div class="w-10 h-10 rounded-xl bg-surface-subtle border border-border-subtle flex items-center justify-center text-text-muted shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                            <div>
                                <p class="text-sm font-bold text-text-main line-clamp-1">{{ $item->product?->name ?? 'Unnamed Product' }}</p>
                                <p class="text-xs text-text-muted">₱{{ number_format((float) ($item->product?->price ?? 0), 2) }} &bull; {{ $item->seller?->email ?? 'Merchant' }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold 
                                {{ $item->status === 'Approved' ? 'bg-success/15 text-success' : ($item->status === 'Disapproved' ? 'bg-danger/15 text-danger' : 'bg-warning/15 text-warning') }}">
                                {{ $item->status }}
                            </span>
                            <span class="block text-[10px] text-text-muted mt-1">{{ $item->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-text-muted">
                        No product compliance records submitted yet.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>