<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Seller Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        
        /* Custom scrollbar for a cleaner UI */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--tw-colors-brand-light, #d1d5db); border-radius: 10px; }
        .custom-scrollbar:hover::-webkit-scrollbar-thumb { background: var(--tw-colors-primary, #9ca3af); }

        /* Strict Mobile/Desktop Visibility Rules */
        .mobile-view { display: flex; }
        .desktop-view { display: none; }
        @media (min-width: 768px) {
            .mobile-view { display: none !important; }
            .desktop-view { display: flex !important; }
        }
    </style>
</head>
<body class="m-0 p-0 h-screen w-screen font-sans antialiased text-text-main overflow-hidden bg-gradient-to-b from-surface via-surface to-brand-light/30 relative">
    
    <!-- Mobile Restricted View -->
    <div class="mobile-view absolute inset-0 bg-surface/80 backdrop-blur-md z-40"></div>
    
    <div class="mobile-view flex-col items-center justify-center h-screen w-screen px-4 sm:px-6 py-8 z-50 relative overflow-y-auto">
        <div class="bg-surface/95 backdrop-blur-xl rounded-3xl shadow-2xl shadow-black/10 border border-border-subtle w-full max-w-md p-6 sm:p-8 text-center flex flex-col items-center">
            
            <div class="w-20 h-20 bg-brand-light/40 text-primary-dark rounded-full flex items-center justify-center mb-5 shadow-inner">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <h2 class="text-2xl sm:text-3xl font-extrabold text-primary-dark mb-4 tracking-tight">Desktop Only</h2>
            
            <div class="bg-brand-light/20 border border-border-subtle rounded-2xl p-5 mb-8 w-full shadow-sm">
                <p class="text-text-main text-sm sm:text-base leading-relaxed font-medium">
                    You are accessing a <span class="font-bold text-primary-dark">Seller Account</span>. 
                    <br><br>
                    <span class="text-text-muted">Please log in from a computer to access this dashboard interface.</span>
                </p>
            </div>
            
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center px-6 py-4 bg-red-500 text-white text-lg font-bold rounded-2xl shadow-md hover:bg-red-600 transition-all duration-200 cursor-pointer active:scale-95">
                    <svg class="w-6 h-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </div>

    <!-- Alpine State Wrapper -->
    <div x-data="{ sidebarOpen: localStorage.getItem('sellerSidebarOpen') !== 'false' }" 
         x-init="$watch('sidebarOpen', val => localStorage.setItem('sellerSidebarOpen', val))" 
         class="desktop-view h-screen w-full relative">
        
        <!-- Sidebar Component -->
        @include('components.seller-components.seller-sidebar')

        <!-- Main Content Area with Animation Wrapper -->
        <main class="flex-1 h-screen overflow-y-auto custom-scrollbar relative"
              x-data="{ showContent: false }" 
              x-init="setTimeout(() => showContent = true, 50)">
            
            <!-- Animated Inner Container -->
            <div class="p-8 lg:p-12"
                 x-show="showContent" 
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-cloak>
                 
                <div class="max-w-7xl mx-auto">

                    <!-- Application Status Gate Banner -->
                    @if($latestApp && $latestApp->status === 'pending')
                        <div class="mb-8 p-6 bg-yellow-50 border border-yellow-200 rounded-3xl shadow-xs">
                            <div class="flex items-start gap-4">
                                <div class="p-3 bg-yellow-100 text-yellow-700 rounded-2xl shrink-0">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-yellow-900">Application Under Review</h3>
                                    <p class="text-xs sm:text-sm text-yellow-800 mt-1 leading-relaxed">
                                        Your application for <strong>{{ $latestApp->business_name }}</strong> (revision v{{ $latestApp->version }}) is currently being reviewed by the Storkia administration. Seller catalog privileges will be unlocked once approved.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @elseif($latestApp && $latestApp->status === 'rejected')
                        <div class="mb-8 p-6 bg-red-50 border border-red-200 rounded-3xl shadow-xs">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <div class="p-3 bg-red-100 text-danger rounded-2xl shrink-0">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-red-900">Application Not Approved</h3>
                                        <p class="text-xs sm:text-sm text-red-800 mt-1">Reason: <strong class="italic">"{{ $latestApp->rejection_reason }}"</strong></p>
                                        <p class="text-xs text-text-muted mt-1">You may correct the issues and submit a revised application.</p>
                                    </div>
                                </div>
                                <a href="{{ route('seller.reapply') }}" class="px-5 py-3 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-md transition-colors shrink-0 text-center">
                                    Update & Re-apply
                                </a>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-8">
                        <h1 class="text-3xl font-bold text-primary-dark">Seller Portal</h1>
                        <p class="text-text-muted">Welcome back, {{ auth()->user()->first_name ?? 'Seller' }}</p>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="p-6 bg-white rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all duration-300">
                            <h3 class="text-text-muted font-bold mb-2">Total Sales</h3>
                            <p class="text-2xl text-primary font-serif">₱0.00</p>
                        </div>
                        <div class="p-6 bg-white rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all duration-300">
                            <h3 class="text-text-muted font-bold mb-2">Active Products</h3>
                            <p class="text-2xl text-primary font-serif">0</p>
                        </div>
                        <div class="p-6 bg-white rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all duration-300">
                            <h3 class="text-text-muted font-bold mb-2">Pending Orders</h3>
                            <p class="text-2xl text-primary font-serif">0</p>
                        </div>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>

</body>
</html>