<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Seller Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="m-0 p-0 h-screen w-screen flex font-sans antialiased text-text-main overflow-hidden bg-surface relative">
    
    <!-- Logo Background Overlay applied to the Body[cite: 11] -->
    <div class="absolute inset-0 pointer-events-none z-0 opacity-10 overflow-hidden" aria-hidden="true">
        <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
            <defs>
                <pattern id="stork-pattern-seller-login" width="120" height="120" patternUnits="userSpaceOnUse" patternTransform="rotate(-15)">
                    <image href="{{ asset('assets/storkia-minimized.png') }}" x="36" y="36" width="48" height="48" style="filter: brightness(0);" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#stork-pattern-seller-login)" />
        </svg>
    </div>

    <!-- The original form component is left entirely intact to preserve its internal layout[cite: 11] -->
    <x-auth.login-form 
        title="Seller Portal" 
        subtitle="Manage your store and orders"
        :submitRoute="route('login.post')"
        :registerRoute="route('seller.register')"
        registerText="Apply as a Seller"
    />

    @livewireScripts
</body>
</html>