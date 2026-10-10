@props([
    'show'            => 'showOtpModal',
    'close'           => 'closeOtpModal()',
    'title'           => 'Confirm it’s you',
    'maskedTarget'    => 'otpMaskedTarget',
    'codeModel'       => 'otpCode',
    'onInput'         => 'onOtpInput($event)',
    'errorMessage'    => 'otpError',
    'isVerifying'     => 'isVerifyingOtp',
    'verifyingText'   => 'Verifying code...',
    'resendAction'    => 'resendOtp()',
    'cooldownModel'   => 'resendCooldown',
    'isResending'     => 'isResendingOtp',
    'inputId'         => 'otp_hidden_input'
])

<template x-teleport="body">
    <div x-show="{{ $show }}"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4">

        <div @click.away="{{ $close }}"
             class="relative w-full max-w-md bg-surface border border-neutral-300 rounded-[28px] p-8 sm:p-10 text-center select-none shadow-xl">

            <!-- Close Button -->
            <button type="button"
                    @click="{{ $close }}"
                    class="absolute top-6 right-6 text-neutral-500 hover:text-text-main transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Title & Subtitle -->
            <h3 class="text-2xl font-bold text-text-main">
                {{ $title }}
            </h3>
            <p class="text-sm text-text-muted mt-2">
                We sent a code to <span class="text-text-main font-medium" x-text="{{ $maskedTarget }}"></span>.
            </p>

            <!-- 6-Digit Container -->
            <div class="mt-8 mb-4 relative flex items-center justify-center">
                <div class="relative w-full border border-neutral-400 focus-within:border-text-main rounded-2xl py-4 px-6 flex items-center justify-between cursor-text"
                     @click="document.getElementById('{{ $inputId }}')?.focus()">

                    <input type="text"
                           id="{{ $inputId }}"
                           inputmode="numeric"
                           maxlength="6"
                           autocomplete="one-time-code"
                           @input="{{ $onInput }}"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">

                    <template x-for="idx in [0, 1, 2, 3, 4, 5]" :key="idx">
                        <span class="w-8 text-center text-xl font-medium text-text-main tracking-widest select-none pointer-events-none"
                              x-text="{{ $codeModel }}[idx] ? {{ $codeModel }}[idx] : '—'">
                        </span>
                    </template>
                </div>
            </div>

            <!-- Error Notice -->
            <template x-if="{{ $errorMessage }}">
                <p class="text-danger text-xs font-medium mb-3" x-text="{{ $errorMessage }}"></p>
            </template>

            <!-- Verification Loading State -->
            <div x-show="{{ $isVerifying }}" class="mb-3 text-xs text-text-muted flex items-center justify-center gap-1.5 font-medium">
                <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce"></span>
                <span class="ml-1">{{ $verifyingText }}</span>
            </div>

            <!-- Resend Link -->
            <div class="mt-6 text-sm text-text-main font-normal">
                <span>Didn’t get it? </span>
                <button type="button"
                        @click="{{ $resendAction }}"
                        :disabled="{{ $cooldownModel }} > 0 || {{ $isResending }}"
                        :class="{{ $cooldownModel }} > 0 ? 'text-text-muted cursor-not-allowed opacity-60' : 'text-text-main underline font-semibold cursor-pointer hover:opacity-80'"
                        class="transition-opacity">
                    <span x-show="{{ $cooldownModel }} === 0 && !{{ $isResending }}">Send a new code</span>
                    <span x-show="{{ $isResending }}">Sending...</span>
                    <span x-show="{{ $cooldownModel }} > 0 && !{{ $isResending }}" x-text="'Resend in ' + {{ $cooldownModel }} + 's'"></span>
                </button>
            </div>
        </div>
    </div>
</template>