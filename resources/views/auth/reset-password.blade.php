@extends('layouts.auth-split', [
    'title' => 'Storkia - Reset Password',
    'restrictMobile' => false
])

@section('content')
    <x-auth.recovery-panel
        title="Choose a new password"
        subtitle="Use at least 8 characters with an uppercase letter, a number and a special character."
    >
        <form method="POST" action="{{ route('password.update') }}" class="space-y-6">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5 text-left">
                    Email Address
                </label>
                <div class="relative flex items-center border-b {{ $errors->has('email') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $email) }}"
                        required
                        autocomplete="email"
                        class="w-full py-3 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                    >
                </div>
                @error('email')
                    <p class="text-danger text-xs mt-1.5 text-left font-medium">{{ $message }}</p>
                @enderror
            </div>

            <div x-data="{ showPassword: false }">
                <label for="password" class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5 text-left">
                    New Password
                </label>
                <div class="relative flex items-center border-b {{ $errors->has('password') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                    <input
                        :type="showPassword ? 'text' : 'password'"
                        id="password"
                        name="password"
                        required
                        autofocus
                        autocomplete="new-password"
                        placeholder="••••••••"
                        class="w-full py-3 pr-10 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 tracking-wider"
                    >
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-0 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer"
                        aria-label="Show or hide password"
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

                <div class="mt-6">
                    <label for="password_confirmation" class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5 text-left">
                        Confirm New Password
                    </label>
                    <div class="relative flex items-center border-b border-border-subtle focus-within:border-text-main transition-colors">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="••••••••"
                            class="w-full py-3 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 tracking-wider"
                        >
                    </div>
                </div>
            </div>

            <div class="pt-2">
                <button
                    type="submit"
                    class="w-full py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]"
                >
                    Reset Password
                </button>
            </div>
        </form>

        <div class="mt-8 pt-8 border-t border-border-subtle/50 text-center">
            <p class="text-xs text-text-muted leading-relaxed">
                Signing in again is required after a reset. Every device that was signed in to this account is signed out.
            </p>
            <a href="{{ route('password.request') }}" class="mt-2.5 inline-block text-xs font-bold text-primary hover:text-primary-dark underline underline-offset-4 transition-colors cursor-pointer">
                Request a new link
            </a>
        </div>
    </x-auth.recovery-panel>
@endsection