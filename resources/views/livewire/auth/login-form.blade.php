<div class="w-full">
    <!-- Header Copy -->
    <div class="mb-10 text-center">
        <h1 class="text-2xl sm:text-3xl font-serif font-normal tracking-tight text-primary-dark">
            {{ $title }}
        </h1>
        <p class="mt-2 text-sm text-text-muted font-normal leading-relaxed">
            {{ $subtitle }}
        </p>

        @if ($errors->has('email') && !str_contains($errors->first('email'), 'valid') && !str_contains($errors->first('email'), 'required'))
            <div class="mt-4 p-3 bg-danger/10 border-l-2 border-danger rounded-r-lg text-left">
                <p class="text-xs font-bold text-danger">{{ $errors->first('email') }}</p>
            </div>
        @endif
    </div>

    <!-- Form Elements -->
    <form wire:submit="submit" class="space-y-6">
        <!-- Email Field -->
        <div>
            <label for="email" class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5 text-left">
                Email Address
            </label>
            <div class="relative flex items-center border-b {{ $errors->has('email') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                <input 
                    type="email" 
                    id="email" 
                    wire:model.live.debounce.400ms="email"
                    autocomplete="email"
                    placeholder="name@example.com"
                    class="w-full py-3 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                >
            </div>
            @error('email')
                <p class="text-danger text-xs mt-1.5 text-left font-medium">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password Field with Alpine Visibility Toggle -->
        <div x-data="{ showPassword: false }">
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-xs uppercase tracking-wider font-bold text-text-muted">
                    Password
                </label>
            </div>
            <div class="relative flex items-center border-b {{ $errors->has('password') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                <input 
                    :type="showPassword ? 'text' : 'password'" 
                    id="password" 
                    wire:model.live.debounce.400ms="password"
                    placeholder="••••••••"
                    class="w-full py-3 pr-10 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 tracking-wider"
                >
                <button 
                    type="button" 
                    @click="showPassword = !showPassword" 
                    class="absolute right-0 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer"
                >
                    <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="text-danger text-xs mt-1.5 text-left font-medium">{{ $message }}</p>
            @enderror
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
            <button 
                type="submit" 
                wire:loading.attr="disabled"
                class="w-full py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span wire:loading.remove wire:target="submit">Sign In</span>
                <span wire:loading wire:target="submit">Verifying...</span>
            </button>
        </div>
    </form>

    <!-- Sign-up Redirection -->
    @if ($showRegister)
        <div class="mt-8 pt-8 border-t border-border-subtle/50 text-center">
            <p class="text-xs text-text-muted">
                Don't have an account? 
                <a href="{{ $registerRoute }}" wire:navigate class="ml-1 font-bold text-primary hover:text-primary-dark underline underline-offset-4 transition-colors cursor-pointer">
                    {{ $registerText }}
                </a>
            </p>
        </div>
    @endif
</div>