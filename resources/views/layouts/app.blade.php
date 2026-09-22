<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- set the brand name in the .env file at APP_NAME -->
    <title> 
        {{ isset($title) ? $title . ' | ' . config('app.name') : config('app.name') }}
    </title>

    <!-- Google Fonts: DM Serif Display & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0,1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="">

    <!-- Smart Navigation Bar -->
    <header 
        x-data="{ 
            showHeader: true, 
            lastScroll: 0 
        }" 
        @scroll.window="
            let currentScroll = window.scrollY;
            if (currentScroll > lastScroll && currentScroll > 100) {
                showHeader = false; // Scrolling down (and past the very top)
            } else if (currentScroll < lastScroll) {
                showHeader = true;  // Scrolling up
            }
            lastScroll = currentScroll;
        "
        :class="showHeader ? 'translate-y-0' : '-translate-y-full'"
        class="sticky top-0 z-50 flex flex-col w-full transition-transform duration-300 ease-in-out"
    >
        @include('components.storefront.navbar')
        <x-storefront.mega-menu />
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- Footer -->
    @if (!($hideFooter ?? false)) <!-- if $hideFooter is set to True, footer will be hidden -->
        @include('components.storefront.footer')
    @endif

    <!-- Scroll to Top Component (z-[60]) -->
    <x-ui.scroll-to-top />

    <!-- Global Minimalist Toast (z-[100], layers over scroll-to-top) -->
    <x-ui.toast-notification />

    @livewireScripts

</body>
</html>