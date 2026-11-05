<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="session-login-url" content="{{ route('login') }}">
    @auth
        <meta name="session-authenticated" content="1">
    @endauth
    <title> 
        {{ isset($title) ? $title . ' | ' . config('app.name') : config('app.name') }}
    </title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0,1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="overflow-x-clip max-w-full flex flex-col min-h-screen">
    <x-ui.preloader />

    <header 
        x-data="{
            showHeader: true,
            lastScroll: 0,
            onScroll() {
                const y = Math.max(window.scrollY, 0);
                const delta = y - this.lastScroll;

                if (y <= 100) {
                    this.showHeader = true;
                } else if (this.$el.matches(':hover') || this.$el.contains(document.activeElement)) {
                    this.showHeader = true;
                } else if (delta > 8) {
                    this.showHeader = false;
                } else if (delta < -8) {
                    this.showHeader = true;
                } else {
                    return;
                }

                this.lastScroll = y;
            }
        }"
        @scroll.window.passive="onScroll()"
        :class="showHeader ? 'translate-y-0' : '-translate-y-full'"
        class="sticky top-0 z-40 md:z-50 flex flex-col w-full transition-transform duration-300 ease-in-out"
    >
        @if(request()->routeIs('landing'))
            @include('components.storefront.landing-page-navbar')
        @else
            @include('components.storefront.navbar')
            <x-storefront.mega-menu />
        @endif
    </header>

    @auth
        <div class="md:hidden"
            x-data="{ mobileDrawerOpen: false }"
            @toggle-account-drawer.window="mobileDrawerOpen = !mobileDrawerOpen"
            @close-account-drawer.window="mobileDrawerOpen = false"
            @keydown.escape.window="mobileDrawerOpen = false">
            <x-buyer.account-sidebar :is-global="true" />
        </div>
    @endauth

    <main class="flex-grow w-full min-w-0 overflow-x-clip">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    @if (!($hideFooter ?? false) && !request()->routeIs('user.*'))
        @include('components.storefront.footer')
    @endif

    <x-ui.scroll-to-top />
    <x-ui.toast-notification />
    @livewireScripts
</body>
</html>