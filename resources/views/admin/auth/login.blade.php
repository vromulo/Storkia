<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Admin Login</title>
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

    <!-- Logo Background Overlay[cite: 13] -->
    <div class="absolute inset-0 pointer-events-none z-0 opacity-5 overflow-hidden" aria-hidden="true">
        <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
            <defs>
                <pattern id="stork-pattern-admin-login" width="120" height="120" patternUnits="userSpaceOnUse" patternTransform="rotate(-15)">
                    <image href="{{ asset('assets/storkia-minimized.png') }}" x="36" y="36" width="48" height="48" style="filter: brightness(0);" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#stork-pattern-admin-login)" />
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

    <!-- Desktop View (Full Screen Login) -->
    <div class="desktop-view h-screen w-screen flex items-center justify-center relative overflow-y-auto p-4 sm:p-6 z-10">
        <div class="w-full max-w-md my-auto">
            
            <!-- Branding -->
            <div class="text-center mb-8 relative z-20">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-brand-light/40 text-primary-dark mb-4 shadow-inner">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <img src="{{ asset('assets/storkia-maximized.png') }}" alt="Storkia" class="h-10 w-auto mx-auto mb-2" />
                <h3 class="mt-2 text-xl font-medium text-text-main">Admin Portal Access</h3>
                <p class="mt-1 text-sm text-text-muted">Please sign in with your administrator credentials.</p>
            </div>

            <!-- Login Card -->
            <div class="relative z-20 bg-surface/80 backdrop-blur-xl py-8 px-6 shadow-2xl sm:rounded-3xl sm:px-10 border border-border-subtle hover:border-primary/30 transition-all duration-300">
                
                <form class="space-y-6" action="{{ route('admin.login') }}" method="POST">
                    @csrf

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-bold text-text-main mb-1.5">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </div>
                            <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"
                                class="block w-full pl-10 pr-4 py-3 appearance-none rounded-xl border @error('email') border-red-500 @else border-border-subtle @enderror bg-surface-subtle text-text-main placeholder-text-muted focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/20 sm:text-sm transition-all duration-200">
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-500 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-bold text-text-main mb-1.5">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input id="password" name="password" type="password" autocomplete="current-password" required 
                                class="block w-full pl-10 pr-4 py-3 appearance-none rounded-xl border @error('password') border-red-500 @else border-border-subtle @enderror bg-surface-subtle text-text-main placeholder-text-muted focus:border-primary focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary/20 sm:text-sm transition-all duration-200">
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-500 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between mt-4">
                        <div class="flex items-center">
                            <input id="remember_me" name="remember" type="checkbox" 
                                class="h-4 w-4 rounded border-border-subtle text-primary focus:ring-primary/50 bg-surface-subtle cursor-pointer transition-colors">
                            <label for="remember_me" class="ml-2 block text-sm text-text-muted cursor-pointer hover:text-text-main transition-colors">
                                Remember me
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="#" class="font-bold text-primary hover:text-primary-dark transition-colors cursor-pointer">
                                Forgot your password?
                            </a>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" 
                            class="flex w-full items-center justify-center rounded-xl bg-primary-dark px-4 py-3 text-sm font-bold text-surface shadow-md hover:bg-text-main hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-dark focus:ring-offset-2 transition-all duration-200 cursor-pointer active:scale-[0.98]">
                            Verify Credentials
                            <svg class="ml-2 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>