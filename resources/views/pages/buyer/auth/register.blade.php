<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Register</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        .rounded-scrollbar::-webkit-scrollbar { width: 6px; }
        .rounded-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .rounded-scrollbar::-webkit-scrollbar-thumb { background-color: var(--color-primary, #CF4173); border-radius: 9999px; }
        .rounded-scrollbar::-webkit-scrollbar-thumb:hover { background-color: var(--color-primary-dark, #5D3140); }
    </style>
</head>
<body class="m-0 p-0 h-screen w-screen font-sans antialiased text-text-main overflow-hidden bg-surface relative">

    <!-- The original 50/50 Flex Layout with relative positioning -->
    <div class="relative z-10 flex w-full h-full">
        
        {{-- Left Panel: Carousel (Hidden on Mobile, 50% width on Large Screens) --}}
        <div class="hidden lg:block lg:w-1/2 h-full z-20">
            <x-carousel />
        </div>

        {{-- Right Panel: Register Form Container with the Pattern Background Applied directly inside it --}}
        <div class="relative w-full lg:w-1/2 h-full flex flex-col items-center justify-start p-4 sm:p-8 lg:p-12 overflow-y-auto rounded-scrollbar">
            
            <!-- Logo Background Overlay applied SPECIFICALLY to the Right Panel -->
            <div class="absolute inset-0 z-0 opacity-10 overflow-hidden" aria-hidden="true" style="pointer-events: none;">
                <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                    <defs>
                        <pattern id="stork-pattern-register" width="120" height="120" patternUnits="userSpaceOnUse" patternTransform="rotate(-15)">
                            <image href="{{ asset('assets/storkia-minimized.png') }}" x="36" y="36" width="48" height="48" style="filter: brightness(0);" />
                        </pattern>
                    </defs>
                    <!-- Background Color Gradient added here so the pattern overlays it seamlessly -->
                    <rect width="100%" height="100%" fill="url(#stork-pattern-register)" />
                </svg>
            </div>
            
            <!-- The Gradient Background for the right panel -->
            <div class="absolute inset-0 z-[-1] bg-gradient-to-br from-surface-subtle via-surface to-brand-light/30"></div>

            {{-- Form Container - Increased z-index to stay above the panel's internal background --}}
            <div class="relative z-10 w-full max-w-2xl bg-surface/90 backdrop-blur-sm p-6 sm:p-8 rounded-2xl sm:rounded-[2rem] shadow-xl border border-border-subtle my-auto">

                <div class="text-center mb-6 sm:mb-8">
                    <a href="/" wire:navigate class="inline-block mb-1 cursor-pointer">
                        <img src="{{ asset('assets/storkia-maximized.png') }}" alt="Storkia Logo" class="h-12 sm:h-16 w-auto mx-auto drop-shadow-md hover:drop-shadow-lg transition-all">
                    </a>
                    <h1 class="text-xl sm:text-2xl font-bold text-text-main">Create your Profile</h1>
                    <p class="text-text-muted mt-1 text-xs sm:text-sm">Please fill in the details below to join us.</p>
                </div>

                <livewire:auth.register-wizard />

                <p class="mt-6 text-center text-xs text-text-muted">
                    Already have an account?
                    <a href="{{ route('login') }}" wire:navigate class="font-bold text-primary hover:text-primary-dark transition-colors cursor-pointer">Sign in here</a>
                </p>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>