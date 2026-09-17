@props([
    'title' => 'Sign In',
    'subtitle' => 'Enter your credentials to access your account',
    'submitRoute' => route('login.post'),
    'registerRoute' => route('register'),
    'registerText' => 'Create an account',
    'showRegister' => true
])

<!-- Split Layout Canvas -->
<div class="hidden lg:block lg:w-1/2 h-full">
    <x-carousel />
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

    <!-- Center Form Body -->
    <div class="w-full max-w-md mx-auto my-auto py-8">
        
        <!-- Header Copy -->
        <div class="mb-10 text-center">
            <h1 class="text-2xl sm:text-3xl font-serif font-normal tracking-tight text-primary-dark">
                {{ $title }}
            </h1>
            <p class="mt-2 text-sm text-text-muted font-normal leading-relaxed">
                {{ $subtitle }}
            </p>

            @if ($errors->any())
                <div class="mt-4 p-3 bg-danger/10 border-l-2 border-danger rounded-r-lg text-left">
                    <p class="text-xs font-bold text-danger">{{ $errors->first() }}</p>
                </div>
            @endif
        </div>

        <!-- Form Elements -->
        <form action="{{ $submitRoute }}" method="POST" class="space-y-6">
            @csrf

            <!-- Email Field -->
            <div>
                <label for="email" class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5 text-left">
                    Email Address
                </label>
                <div class="relative flex items-center border-b border-border-subtle focus-within:border-text-main transition-colors">
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="{{ old('email') }}"
                        required 
                        autocomplete="email"
                        placeholder="name@example.com"
                        class="w-full py-3 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                    >
                </div>
            </div>

            <!-- Password Field with Alpine Visibility Toggle -->
            <div x-data="{ showPassword: false }">
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs uppercase tracking-wider font-bold text-text-muted">
                        Password
                    </label>
                    <a href="#" class="text-xs text-text-muted hover:text-primary transition-colors font-medium">
                        Forgot?
                    </a>
                </div>
                <div class="relative flex items-center border-b border-border-subtle focus-within:border-text-main transition-colors">
                    <input 
                        :type="showPassword ? 'text' : 'password'" 
                        id="password" 
                        name="password" 
                        required 
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full py-3 pr-10 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 tracking-wider"
                    >
                    <button 
                        type="button" 
                        @click="showPassword = !showPassword" 
                        class="absolute right-0 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer"
                        aria-label="Toggle password visibility"
                    >
                        <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
                <button 
                    type="submit" 
                    class="w-full py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]"
                >
                    Continue
                </button>
            </div>
        </form>

        <!-- Dynamic Registration Prompt -->
        @if($showRegister)
            <div class="mt-8 pt-8 border-t border-border-subtle/50 text-center">
                <p class="text-xs text-text-muted">
                    New to Storkia? 
                    <a href="{{ $registerRoute }}" wire:navigate class="ml-1 font-bold text-primary hover:text-primary-dark underline underline-offset-4 transition-colors cursor-pointer">
                        {{ $registerText }}
                    </a>
                </p>
            </div>
        @endif
    </div>

    <!-- Minimalist Bottom Footer -->
    <div class="w-full max-w-md mx-auto text-center pb-2">
        <p class="text-[11px] text-text-muted/70">
            &copy; {{ date('Y') }} Storkia. All rights reserved.
        </p>
    </div>
</div>