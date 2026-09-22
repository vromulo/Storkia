<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Become a Seller</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        /* Strict Mobile/Desktop Visibility Rules */
        .mobile-view { display: flex; }
        .desktop-view { display: none; }
        @media (min-width: 768px) {
            .mobile-view { display: none !important; }
            .desktop-view { display: flex !important; }
        }
    </style>
</head>
<body class="m-0 p-0 h-screen w-screen font-sans antialiased text-text-main overflow-hidden bg-surface selection:bg-brand-light selection:text-primary-dark relative">
    
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
                    You are trying to register a <span class="font-bold text-primary-dark">Seller Account</span>. 
                    <br><br>
                    <span class="text-text-muted">Please access this page from a computer to continue.</span>
                </p>
            </div>
            
            <a href="/" class="w-full flex items-center justify-center px-6 py-4 bg-primary text-white text-lg font-bold rounded-2xl shadow-md hover:bg-primary-dark transition-all duration-200 cursor-pointer active:scale-95">
                <svg class="w-6 h-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Return Home
            </a>
        </div>
    </div>

    <!-- Desktop View Wrapper -->
    <div class="desktop-view h-full w-full flex">
        <!-- Split Layout Canvas -->
        <div class="hidden lg:block lg:w-1/2 h-full">
            <x-ui.carousel />
        </div>

        <!-- Right Canvas: Minimalist, Containerless Column -->
        <div class="w-full lg:w-1/2 h-full flex flex-col justify-between p-8 sm:p-14 lg:p-20 overflow-y-auto bg-surface">
            
            <!-- Centered, Prominent Brand Title -->
            <div class="w-full max-w-md mx-auto text-center pt-2">
                <a href="/" wire:navigate class="inline-block group cursor-pointer focus:outline-none">
                    <span class="font-serif text-4xl sm:text-5xl font-bold text-primary-dark tracking-tight transition-colors duration-200 group-hover:text-primary">
                        Storkia
                    </span>
                </a>
            </div>

            <!-- Center Seller Wizard Body -->
            <div class="w-full max-w-md mx-auto my-auto py-8">
                <livewire:auth.seller-register-wizard />
            </div>

            <!-- Minimalist Bottom Footer -->
            <div class="w-full max-w-md mx-auto text-center pb-2">
                <p class="text-[11px] text-text-muted/70">
                    &copy; {{ date('Y') }} Storkia. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>