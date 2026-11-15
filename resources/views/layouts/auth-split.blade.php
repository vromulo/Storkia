<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Storkia' }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0,1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="m-0 p-0 h-screen w-screen font-sans antialiased text-text-main overflow-hidden bg-surface relative">
    <x-ui.preloader />

    @php
        $restrictMobile = $restrictMobile ?? true;
    @endphp

    @if($restrictMobile)
        <div class="mobile-view fixed inset-0 bg-surface/90 backdrop-blur-xl z-40"></div>
        <div class="mobile-view fixed inset-0 flex-col items-center justify-center h-screen w-screen px-5 py-8 z-50 overflow-y-auto select-none">
            <div class="bg-surface rounded-3xl shadow-2xl border border-border-subtle w-full max-w-md p-7 text-center flex flex-col items-center">
                <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center mb-4 border border-primary/20">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <span class="px-3 py-1 rounded-full bg-brand-light/60 text-primary-dark text-[10px] font-extrabold uppercase tracking-wider mb-2">
                    Viewport Restricted
                </span>
                <h2 class="text-2xl font-serif font-bold text-primary-dark mb-3">Tablet or Desktop Required</h2>
                <div class="bg-surface-subtle border border-border-subtle rounded-2xl p-4 mb-6 w-full">
                    <p class="text-text-main text-xs sm:text-sm leading-relaxed font-medium">
                        {{ $mobilePrompt ?? 'Please access this partner portal from a Tablet (768px+) or Desktop computer.' }}
                    </p>
                </div>
                <a href="/shop" class="w-full flex items-center justify-center px-6 py-3.5 bg-primary hover:bg-primary-dark text-white text-sm font-bold rounded-2xl shadow-md transition-all duration-200 cursor-pointer active:scale-95">
                    Return to Storefront
                </a>
            </div>
        </div>
    @endif

    <div class="{{ $restrictMobile ? 'desktop-view' : 'flex' }} h-full w-full flex">
        @yield('content')
    </div>
    <x-ui.toast-notification />
    @livewireScripts
</body>
</html>