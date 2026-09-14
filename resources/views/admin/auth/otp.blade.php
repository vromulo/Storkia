<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Admin OTP Authentication</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        
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

    <!-- Logo Background Overlay[cite: 12] -->
    <div class="absolute inset-0 pointer-events-none z-0 opacity-5 overflow-hidden" aria-hidden="true">
        <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
            <defs>
                <pattern id="stork-pattern-admin-otp" width="120" height="120" patternUnits="userSpaceOnUse" patternTransform="rotate(-15)">
                    <image href="{{ asset('assets/storkia-minimized.png') }}" x="36" y="36" width="48" height="48" style="filter: brightness(0);" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#stork-pattern-admin-otp)" />
        </svg>
    </div>

    <!-- Mobile Restricted View -->
    <div class="mobile-view fixed inset-0 bg-surface/80 backdrop-blur-md z-40"></div>
    
    <div class="mobile-view fixed inset-0 flex-col items-center justify-center px-4 sm:px-6 py-8 z-50 overflow-y-auto">
        <div class="bg-surface/95 backdrop-blur-xl rounded-3xl shadow-2xl shadow-black/10 border border-border-subtle w-full max-w-md p-6 sm:p-8 text-center flex flex-col items-center">
            
            <div class="w-20 h-20 bg-brand-light/40 text-primary-dark rounded-full flex items-center justify-center mb-5 shadow-inner">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>

            <h2 class="text-2xl sm:text-3xl font-extrabold text-primary-dark mb-4 tracking-tight">Desktop Only</h2>
            
            <div class="bg-brand-light/20 border border-border-subtle rounded-2xl p-5 w-full shadow-sm">
                <p class="text-text-main text-sm sm:text-base leading-relaxed font-medium">
                    You are attempting to access the <span class="font-bold text-primary-dark">Administrative Portal</span>. 
                    <br><br>
                    <span class="text-text-muted">Please log in from a computer to access this interface.</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Desktop View (Full Screen OTP) -->
    <div class="desktop-view h-screen w-screen flex items-center justify-center relative overflow-y-auto p-4 sm:p-6 z-10">
        <div class="w-full max-w-md my-auto">
            
            <!-- Header -->
            <div class="text-center mb-8 relative z-20">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-brand-light/40 text-primary-dark mb-4 shadow-inner">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <img src="{{ asset('assets/storkia-maximized.png') }}" alt="Storkia" class="h-10 w-auto mx-auto mb-2" />
                <h3 class="mt-2 text-xl font-medium text-text-main">Two-Factor Authentication</h3>
                <p class="mt-1 text-sm text-text-muted">Enter the 6-digit secure code sent to your device.</p>
            </div>

            <!-- OTP Card -->
            <div class="relative z-20 bg-surface/80 backdrop-blur-xl py-8 px-6 shadow-2xl sm:rounded-3xl sm:px-10 border border-border-subtle hover:border-primary/30 transition-all duration-300">
                
                <form class="space-y-6" action="{{ route('admin.otp.verify') }}" method="POST">
                    @csrf

                    <!-- OTP Input -->
                    <div>
                        <label for="code" class="block text-sm font-bold text-text-main text-center mb-3">Verification Code</label>
                        <div class="mt-2">
                            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus
                                class="block w-full text-center tracking-[0.75em] text-3xl font-extrabold rounded-xl border @error('code') border-red-500 @else border-border-subtle @enderror px-4 py-4 bg-surface-subtle text-primary-dark placeholder-border-subtle focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all duration-200"
                                placeholder="••••••">
                        </div>
                        @error('code')
                            <p class="mt-3 text-sm text-center text-red-500 flex items-center justify-center">
                                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" 
                            class="flex w-full items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-surface shadow-md hover:bg-primary-dark hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-all duration-200 cursor-pointer active:scale-[0.98]">
                            Authenticate
                            <svg class="ml-2 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-6 pt-4 border-t border-border-subtle">
                    <form action="{{ route('admin.otp.resend') }}" method="POST" class="inline m-0">
                        @csrf
                        <p class="text-sm text-text-muted">
                            Didn't receive a code? 
                            <button type="submit" class="font-bold text-primary hover:text-primary-dark transition-colors cursor-pointer focus:outline-none">
                                Resend Secure Code
                            </button>
                        </p>
                    </form>
                </div>
                
            </div>
        </div>
    </div>

</body>
</html>