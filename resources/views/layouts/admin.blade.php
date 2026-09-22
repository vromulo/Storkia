<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? 'Storkia - Admin' }}</title>
        
        <!-- Google Fonts: DM Serif Display & Plus Jakarta Sans -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0,1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

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
        
        <!-- Mobile Restricted View (same guard used on the dashboard) -->
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
                        You are accessing an <span class="font-bold text-primary-dark">Administrative Account</span>.
                        <br><br>
                        <span class="text-text-muted">Please log in from a computer to access this dashboard interface.</span>
                    </p>
                </div>
    
                <form method="POST" action="{{ route('admin.logout') }}" class="w-full">
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
 
        <!-- Alpine State Wrapper (Alpine here comes from Livewire's bundled copy via @@livewireScripts below) -->
        <div x-data="{ sidebarOpen: localStorage.getItem('adminSidebarOpen') !== 'false' }"
            x-init="$watch('sidebarOpen', val => localStorage.setItem('adminSidebarOpen', val))"
            class="desktop-view h-screen w-full relative">
    
            <!-- Sidebar Component -->
            @include('components.admin.sidebar')
    
            <!-- Main Content Area: this is where each Livewire full-page component renders -->
            <main class="flex-1 h-screen overflow-y-auto custom-scrollbar relative">
                {{ $slot }}
            </main>
        </div>
    
        @livewireScripts

    </body>
</html>