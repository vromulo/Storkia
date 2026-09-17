<div class="w-full">
    <!-- Header Copy -->
    <div class="mb-8 text-center">
        <h1 class="text-2xl sm:text-3xl font-serif font-normal tracking-tight text-primary-dark">
            Create an Account
        </h1>
        <p class="mt-2 text-sm text-text-muted font-normal leading-relaxed">
            @if ($currentStep === 1)
                Verify your email address to get started
            @elseif ($currentStep === 2)
                Tell us a little bit about yourself
            @elseif ($currentStep === 3)
                Set up a secure password for your account
            @endif
        </p>
    </div>

    <!-- Step Indicator -->
    <div class="flex items-center justify-center gap-2 mb-10">
        @foreach ([1 => 'Verify Email', 2 => 'Personal Info', 3 => 'Password'] as $num => $label)
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold transition-all duration-300
                        {{ $currentStep === $num ? 'bg-primary-dark text-surface' : ($currentStep > $num ? 'bg-brand-light text-primary-dark' : 'bg-surface-subtle text-text-muted/60') }}">
                        @if ($currentStep > $num)
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            {{ $num }}
                        @endif
                    </div>
                    <span class="text-[11px] uppercase tracking-wider font-semibold {{ $currentStep === $num ? 'text-primary-dark' : 'text-text-muted/60' }} hidden sm:inline">
                        {{ $label }}
                    </span>
                </div>
                @if ($num < 3)
                    <div class="w-6 sm:w-8 h-[1px] {{ $currentStep > $num ? 'bg-primary-dark/40' : 'bg-border-subtle' }}"></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- STEP 1: Verify Email --}}
    @if ($currentStep === 1)
        <div class="space-y-6">
            <div>
                <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                    Email Address
                </label>
                <div class="relative flex items-center border-b {{ $errors->has('email') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                    <input 
                        type="email" 
                        wire:model.live.debounce.500ms="email" 
                        @if ($codeSent) disabled @endif
                        class="w-full py-3 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 disabled:text-text-muted/50"
                        placeholder="name@example.com"
                    >
                </div>
                @error('email') 
                    <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> 
                @enderror
            </div>

            @if (! $codeSent)
                <div class="pt-2">
                    <button 
                        type="button" 
                        wire:click="sendCode" 
                        wire:loading.attr="disabled" 
                        wire:target="sendCode"
                        class="w-full py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="sendCode">Send Verification Code</span>
                        <span wire:loading wire:target="sendCode">Sending Code...</span>
                    </button>
                </div>
            @else
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                        Verification Code
                    </label>
                    <div class="relative flex items-center border-b {{ $errors->has('code') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model="code" 
                            inputmode="numeric" 
                            maxlength="6" 
                            autocomplete="one-time-code"
                            class="w-full py-3 bg-transparent text-text-main text-lg tracking-[0.4em] font-semibold text-center outline-none placeholder:text-text-muted/40 placeholder:tracking-normal"
                            placeholder="000000"
                        >
                    </div>
                    @error('code') 
                        <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> 
                    @enderror
                    <p class="text-text-muted text-xs mt-2 text-center">
                        Code sent to <span class="font-semibold text-text-main">{{ $email }}</span>
                    </p>
                </div>

                <div class="space-y-3 pt-2">
                    <button 
                        type="button" 
                        wire:click="verifyCode" 
                        wire:loading.attr="disabled" 
                        wire:target="verifyCode"
                        class="w-full py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="verifyCode">Verify & Continue</span>
                        <span wire:loading wire:target="verifyCode">Verifying...</span>
                    </button>

                    <div class="text-center" x-data="{ seconds: {{ $resendCooldown }} }"
                         x-init="let t = setInterval(() => { if (seconds > 0) seconds--; else clearInterval(t); }, 1000)">
                        <button 
                            type="button" 
                            wire:click="resendCode" 
                            wire:loading.attr="disabled" 
                            wire:target="resendCode"
                            x-bind:disabled="seconds > 0"
                            class="text-xs text-text-muted hover:text-primary transition-colors cursor-pointer disabled:text-text-muted/40 disabled:cursor-not-allowed font-medium"
                        >
                            <span x-show="seconds === 0">Resend Code</span>
                            <span x-show="seconds > 0">Resend available in <span x-text="seconds"></span>s</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- STEP 2: Personal Info --}}
    @if ($currentStep === 2)
        <div class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                {{-- First Name --}}
                <div class="sm:col-span-3">
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                        First Name*
                    </label>
                    <div class="relative flex items-center border-b {{ $errors->has('first_name') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.500ms="first_name"
                            x-on:input="$event.target.value = $event.target.value.replace(/^\s+/, '').replace(/ {2,}/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"
                            class="capitalize w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                            placeholder="John"
                        >
                    </div>
                    @error('first_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Middle Initial --}}
                <div class="sm:col-span-1">
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                        M.I.
                    </label>
                    <div class="relative flex items-center border-b {{ $errors->has('middle_initial') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.500ms="middle_initial" 
                            maxlength="1"
                            x-on:input="$event.target.value = $event.target.value.replace(/\s+/g, '').toUpperCase()"
                            class="uppercase w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 text-center sm:text-left"
                            placeholder="D"
                        >
                    </div>
                    @error('middle_initial') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Last Name --}}
            <div>
                <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                    Last Name*
                </label>
                <div class="relative flex items-center border-b {{ $errors->has('last_name') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                    <input 
                        type="text" 
                        wire:model.live.debounce.500ms="last_name"
                        x-on:input="$event.target.value = $event.target.value.replace(/^\s+/, '').replace(/ {2,}/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"
                        class="capitalize w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                        placeholder="Doe"
                    >
                </div>
                @error('last_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            {{-- Sex --}}
            <div>
                <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-2">
                    Sex*
                </label>
                <div class="flex gap-6 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input 
                            type="radio" 
                            wire:model.live="sex" 
                            value="male" 
                            class="w-4 h-4 text-primary-dark focus:ring-primary-dark cursor-pointer border-border-subtle"
                        >
                        <span class="text-sm font-medium text-text-main group-hover:text-primary-dark transition-colors">Male</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input 
                            type="radio" 
                            wire:model.live="sex" 
                            value="female" 
                            class="w-4 h-4 text-primary-dark focus:ring-primary-dark cursor-pointer border-border-subtle"
                        >
                        <span class="text-sm font-medium text-text-main group-hover:text-primary-dark transition-colors">Female</span>
                    </label>
                </div>
                @error('sex') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            {{-- Birthday --}}
            <div>
                <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                    Birthday*
                </label>
                <div class="relative flex items-center border-b {{ $errors->has('birthday') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                    <input 
                        type="date" 
                        wire:model.live="birthday"
                        min="{{ now()->subYears(100)->format('Y-m-d') }}"
                        max="{{ now()->subYears(18)->format('Y-m-d') }}"
                        class="w-full py-2.5 bg-transparent text-text-main text-base outline-none cursor-pointer"
                    >
                </div>
                @error('birthday') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-4">
                <button 
                    type="button" 
                    wire:click="backToStep(1)"
                    class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer"
                >
                    Back
                </button>
                <button 
                    type="button" 
                    wire:click="goToPasswordStep" 
                    wire:loading.attr="disabled" 
                    wire:target="goToPasswordStep"
                    class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60"
                >
                    Continue
                </button>
            </div>
        </div>
    @endif

    {{-- STEP 3: Password --}}
    @if ($currentStep === 3)
        <div class="space-y-5" x-data="{ showPass: false, showConfirm: false }">
            {{-- Password --}}
            <div>
                <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                    Password*
                </label>
                <div class="relative flex items-center border-b {{ $errors->has('password') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                    <input 
                        :type="showPass ? 'text' : 'password'" 
                        wire:model.live.debounce.500ms="password"
                        class="w-full py-2.5 pr-8 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 tracking-wider"
                        placeholder="••••••••"
                    >
                    <button 
                        type="button" 
                        @click="showPass = !showPass" 
                        class="absolute right-0 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer"
                    >
                        <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showPass" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                        </svg>
                    </button>
                </div>
                @error('password') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                    Confirm Password*
                </label>
                <div class="relative flex items-center border-b {{ $errors->has('password_confirmation') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                    <input 
                        :type="showConfirm ? 'text' : 'password'" 
                        wire:model.live.debounce.500ms="password_confirmation"
                        class="w-full py-2.5 pr-8 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 tracking-wider"
                        placeholder="••••••••"
                    >
                    <button 
                        type="button" 
                        @click="showConfirm = !showConfirm" 
                        class="absolute right-0 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer"
                    >
                        <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg x-show="showConfirm" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                        </svg>
                    </button>
                </div>
                @error('password_confirmation') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
            </div>

            <!-- Password Requirements Indicator -->
            <div class="pt-2 grid grid-cols-2 gap-1.5 text-[11px] text-text-muted">
                <span class="flex items-center gap-1 {{ strlen($password) >= 8 ? 'text-success font-semibold' : '' }}">
                    <span>{{ strlen($password) >= 8 ? '✓' : '•' }}</span> 8+ characters
                </span>
                <span class="flex items-center gap-1 {{ preg_match('/[A-Z]/', $password) ? 'text-success font-semibold' : '' }}">
                    <span>{{ preg_match('/[A-Z]/', $password) ? '✓' : '•' }}</span> 1 uppercase
                </span>
                <span class="flex items-center gap-1 {{ preg_match('/[0-9]/', $password) ? 'text-success font-semibold' : '' }}">
                    <span>{{ preg_match('/[0-9]/', $password) ? '✓' : '•' }}</span> 1 number
                </span>
                <span class="flex items-center gap-1 {{ preg_match('/[\W_]/', $password) ? 'text-success font-semibold' : '' }}">
                    <span>{{ preg_match('/[\W_]/', $password) ? '✓' : '•' }}</span> 1 special symbol
                </span>
            </div>

            <div class="flex items-center gap-3 pt-4">
                <button 
                    type="button" 
                    wire:click="backToStep(2)"
                    class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer"
                >
                    Back
                </button>
                <button 
                    type="button" 
                    wire:click="register" 
                    wire:loading.attr="disabled" 
                    wire:target="register"
                    class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="register">Complete Registration</span>
                    <span wire:loading wire:target="register">Creating Account...</span>
                </button>
            </div>
        </div>
    @endif

    <!-- Sign-in Link -->
    <div class="mt-8 pt-8 border-t border-border-subtle/50 text-center">
        <p class="text-xs text-text-muted">
            Already have an account? 
            <a href="{{ route('login') }}" wire:navigate class="ml-1 font-bold text-primary hover:text-primary-dark underline underline-offset-4 transition-colors cursor-pointer">
                Sign in here
            </a>
        </p>
    </div>
</div>