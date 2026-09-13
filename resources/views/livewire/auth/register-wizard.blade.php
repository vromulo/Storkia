<div>
    {{-- Step Progress Indicator --}}
    <div class="flex items-center justify-center gap-1 sm:gap-2 mb-6 sm:mb-8 px-2">
        @foreach ([1 => 'Verify Email', 2 => 'Personal Info', 3 => 'Password'] as $num => $label)
            <div class="flex items-center gap-1 sm:gap-2">
                <div class="flex flex-col items-center gap-1">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center text-[10px] sm:text-xs font-bold transition-colors
                        {{ $currentStep === $num ? 'bg-primary text-surface' : ($currentStep > $num ? 'bg-primary-dark text-surface' : 'bg-surface-subtle text-text-muted') }}">
                        @if ($currentStep > $num)
                            <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            {{ $num }}
                        @endif
                    </div>
                    <span class="text-[9px] sm:text-[10px] font-medium text-text-muted whitespace-nowrap">{{ $label }}</span>
                </div>
                @if ($num < 3)
                    <div class="w-4 sm:w-8 h-0.5 {{ $currentStep > $num ? 'bg-primary-dark' : 'bg-border-subtle' }} mb-4"></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- STEP 1: Verify Email --}}
    @if ($currentStep === 1)
        <div class="w-full max-w-md mx-auto space-y-4 sm:space-y-5">
            <div>
                <label class="block text-xs font-bold text-text-main mb-1">E-mail*</label>
                <input type="email" wire:model.live.debounce.500ms="email" @if ($codeSent) disabled @endif
                    class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm disabled:bg-surface-subtle disabled:text-text-muted 
                    @error('email') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                    @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror"
                    placeholder="you@example.com">
                @error('email') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            @if (! $codeSent)
                <button type="button" wire:click="sendCode" wire:loading.attr="disabled" wire:target="sendCode"
                    class="w-full py-2.5 sm:py-3 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl shadow-md transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="sendCode">Send Verification Code</span>
                    <span wire:loading wire:target="sendCode">Sending...</span>
                </button>
            @else
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Verification Code*</label>
                    <input type="text" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                        class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm tracking-[0.5em] text-center font-bold 
                        @error('code') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                        @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror"
                        placeholder="000000">
                    @error('code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    <p class="text-text-muted text-xs mt-1 text-center sm:text-left">We sent a 6-digit code to {{ $email }}.</p>
                </div>

                <button type="button" wire:click="verifyCode" wire:loading.attr="disabled" wire:target="verifyCode"
                    class="w-full py-2.5 sm:py-3 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl shadow-md transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="verifyCode">Verify Code</span>
                    <span wire:loading wire:target="verifyCode">Verifying...</span>
                </button>

                <div class="text-center" x-data="{ seconds: {{ $resendCooldown }} }"
                    x-init="let t = setInterval(() => { if (seconds > 0) seconds--; else clearInterval(t); }, 1000)">
                    <button type="button" wire:click="resendCode" wire:loading.attr="disabled" wire:target="resendCode"
                        x-bind:disabled="seconds > 0"
                        class="text-xs font-bold text-primary hover:text-primary-dark transition-colors cursor-pointer disabled:text-text-muted disabled:cursor-not-allowed">
                        <span x-show="seconds === 0">Resend Code</span>
                        <span x-show="seconds > 0">Resend available in <span x-text="seconds"></span>s</span>
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- STEP 2: Personal Info --}}
    @if ($currentStep === 2)
        <div class="w-full max-w-md mx-auto space-y-4 sm:space-y-5">

            {{-- First Name --}}
            <div>
                <label class="block text-xs font-bold text-text-main mb-1">First Name*</label>
                <input type="text" 
                    wire:model.live.debounce.500ms="first_name"
                    x-on:input="$event.target.value = $event.target.value.replace(/\b\w/g, c => c.toUpperCase())"
                    class="capitalize w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm 
                    @error('first_name') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                    @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror">
                @error('first_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            {{-- Last Name --}}
            <div>
                <label class="block text-xs font-bold text-text-main mb-1">Last Name*</label>
                <input type="text" 
                    wire:model.live.debounce.500ms="last_name"
                    x-on:input="$event.target.value = $event.target.value.replace(/\b\w/g, c => c.toUpperCase())"
                    class="capitalize w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm 
                    @error('last_name') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                    @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror">
                @error('last_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            {{-- Middle Initial --}}
            <div>
                <label class="block text-xs font-bold text-text-main mb-1">M.I.</label>
                <input type="text" 
                    wire:model.live.debounce.500ms="middle_initial" 
                    maxlength="1"
                    x-on:input="$event.target.value = $event.target.value.toUpperCase()"
                    class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm uppercase 
                    @error('middle_initial') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                    @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror"
                    placeholder="M">
                @error('middle_initial') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            {{-- Sex --}}
            <div>
                <label class="block text-xs font-bold text-text-main mb-1">Sex*</label>
                <div class="flex gap-4 mt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="sex" value="male" 
                            class="w-4 h-4 text-text-main focus:ring-text-main cursor-pointer @error('sex') border-danger @else border-border-subtle @enderror">
                        <span class="text-sm font-medium text-text-main">Male</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="sex" value="female" 
                            class="w-4 h-4 text-text-main focus:ring-text-main cursor-pointer @error('sex') border-danger @else border-border-subtle @enderror">
                        <span class="text-sm font-medium text-text-main">Female</span>
                    </label>
                </div>
                @error('sex') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            {{-- Birthday --}}
            <div>
                <label class="block text-xs font-bold text-text-main mb-1">Birthday*</label>
                <input type="date" wire:model.live="birthday"
                    min="{{ now()->subYears(100)->format('Y-m-d') }}"
                    max="{{ now()->subYears(18)->format('Y-m-d') }}"
                    class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm cursor-pointer text-text-main 
                    @error('birthday') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                    @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror">
                @error('birthday') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:gap-3 mt-4">
                <button type="button" wire:click="backToStep(1)"
                    class="flex-1 py-2.5 sm:py-3 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">
                    Back
                </button>
                <button type="button" wire:click="goToPasswordStep" wire:loading.attr="disabled" wire:target="goToPasswordStep"
                    class="flex-[2] py-2.5 sm:py-3 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl shadow-md transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-60">
                    Continue
                </button>
            </div>
        </div>
    @endif

    {{-- STEP 3: Password --}}
    @if ($currentStep === 3)
        <div class="w-full max-w-md mx-auto space-y-4 sm:space-y-5" x-data="{ showPass: false, showConfirm: false }">
            <div>
                <label class="block text-xs font-bold text-text-main mb-1">Password*</label>
                <div class="relative flex items-center">
                    <input :type="showPass ? 'text' : 'password'" wire:model.live.debounce.500ms="password"
                        class="w-full py-2 pl-3 pr-10 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm 
                        @error('password') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                        @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror"
                        placeholder="••••••••">
                    <button type="button" @click="showPass = !showPass" class="absolute right-3 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                        <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="showPass" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                    </button>
                </div>
                @error('password') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-text-main mb-1">Confirm Password*</label>
                <div class="relative flex items-center">
                    <input :type="showConfirm ? 'text' : 'password'" wire:model.live.debounce.500ms="password_confirmation"
                        class="w-full py-2 pl-3 pr-10 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm 
                        @error('password_confirmation') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                        @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror"
                        placeholder="••••••••">
                    <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                        <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="showConfirm" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                    </button>
                </div>
                @error('password_confirmation') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:gap-3 mt-4">
                <button type="button" wire:click="backToStep(2)"
                    class="flex-1 py-2.5 sm:py-3 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">
                    Back
                </button>
                <button type="button" wire:click="register" wire:loading.attr="disabled" wire:target="register"
                    class="flex-[2] py-2.5 sm:py-3 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl shadow-md transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="register">Complete</span>
                    <span wire:loading wire:target="register">Creating...</span>
                </button>
            </div>
        </div>
    @endif
</div>