<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Become a Seller</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        .rounded-scrollbar::-webkit-scrollbar { width: 6px; }
        .rounded-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .rounded-scrollbar::-webkit-scrollbar-thumb { background-color: var(--color-primary, #CF4173); border-radius: 9999px; }
    </style>
</head>
<body class="m-0 p-0 h-screen w-screen font-sans antialiased text-text-main overflow-hidden bg-surface relative">
    
    <div class="relative z-10 flex w-full h-full">
        <div class="hidden lg:block lg:w-1/2 h-full z-20">
            <x-carousel />
        </div>
        
        <div class="relative w-full lg:w-1/2 h-full flex flex-col items-center justify-start p-4 sm:p-8 lg:p-12 overflow-y-auto rounded-scrollbar bg-surface">
            <div class="relative z-10 w-full max-w-2xl bg-surface p-6 sm:p-8 rounded-2xl sm:rounded-[2rem] shadow-xl border border-border-subtle my-auto">
                <div class="text-center mb-6 sm:mb-8">
                    <a href="/" class="inline-block mb-1 cursor-pointer">
                        <img src="{{ asset('assets/storkia-maximized.png') }}" alt="Storkia Logo" class="h-12 sm:h-16 w-auto mx-auto drop-shadow-md hover:drop-shadow-lg transition-all">
                    </a>
                    <h1 class="text-xl sm:text-2xl font-bold text-text-main">Become a Seller</h1>
                    <p class="text-text-muted mt-1 text-xs sm:text-sm">Join Storkia and grow your business.</p>
                </div>

                <livewire:auth.seller-register-wizard />
            </div>
        </div>
    </div>
    
    @livewireScripts
</body>
</html>