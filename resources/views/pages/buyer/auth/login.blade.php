<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="m-0 p-0 h-screen w-screen flex font-sans antialiased text-text-main overflow-hidden bg-surface relative">
    
    <!-- Logo Background Overlay (10% Opacity, Black, Original Reference Spacing) -->
    <div class="absolute inset-0 pointer-events-none z-0 opacity-10 overflow-hidden" aria-hidden="true">
        <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
            <defs>
                <pattern id="stork-pattern" width="120" height="120" patternUnits="userSpaceOnUse" patternTransform="rotate(-15)">
                    <image href="{{ asset('assets/storkia-minimized.png') }}" x="36" y="36" width="48" height="48" style="filter: brightness(0);" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#stork-pattern)" />
        </svg>
    </div>

    <!-- Removed the wrapping div here so the two-column layout isn't broken -->
    <x-auth.login-form />

    @livewireScripts
</body>
</html>