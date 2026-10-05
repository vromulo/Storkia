@extends('buyer.personal-center', [
    'pageTitle' => 'My Account',
    'pageSubtitle' => 'Manage your personal settings, security, and account status.'
])

@section('option-content')
@php
    $initialFullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'User-' . str_pad($user->id, 6, '0', STR_PAD_LEFT);
    $rawEmail = $user->email ?? '';
    $maskedEmail = $rawEmail;
    if (str_contains($rawEmail, '@')) {
        [$name, $domain] = explode('@', $rawEmail, 2);
        $maskedEmail = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 2, 4)) . substr($name, -1) . '@' . $domain;
    }
@endphp

<div x-data="accountManager(
    {{ $errors->hasAny(['first_name', 'last_name']) ? "'username'" : ($errors->has('email') ? "'email'" : 'null') }},
    {
        first_name: @js($user->first_name ?? ''),
        last_name: @js($user->last_name ?? ''),
        full_name: @js($initialFullName),
        email: @js($rawEmail),
        masked_email: @js($maskedEmail)
    }
)" class="space-y-6 pt-0 select-none">

    <!-- Header Title (Static) -->
    <div class="pt-0 pb-2">
        <h2 class="text-3xl sm:text-4xl font-semibold text-text-main tracking-tight">
            Manage My Account
        </h2>
    </div>

    <!-- 1. Full Name Row -->
    <div class="transition-all duration-200"
         :class="isDimmed('username') ? 'opacity-40' : ''">

        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                    Full Name
                </h3>
                <p x-show="activeEdit === 'username'" x-cloak class="text-xs sm:text-sm text-text-muted mt-2">
                    Make sure this matches the name on your government ID.
                </p>
                <p x-show="activeEdit !== 'username'" class="text-sm text-text-muted font-normal tracking-wide mt-2">
                    <span x-text="fullName"></span>
                </p>
            </div>

            <div class="shrink-0 text-right leading-none">
                <button type="button"
                        @click="toggle('username')"
                        :disabled="isDimmed('username') || isSavingName || isSavingEmail"
                        :class="(isDimmed('username') || isSavingName || isSavingEmail) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                        class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                    <span x-text="activeEdit === 'username' ? 'Cancel' : 'Edit'"></span>
                </button>
            </div>
        </div>

        <div x-show="activeEdit === 'username'" x-cloak class="mt-4">
            <form action="{{ route('user.account-management.update-name') }}"
                  method="POST"
                  @submit.prevent="submitName($event)"
                  class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <div class="border rounded-xl p-3 bg-surface transition-colors"
                             :class="nameErrors.first_name ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                            <label for="first_name" class="block text-xs font-normal text-text-muted">
                                First name on ID
                            </label>
                            <input type="text"
                                   id="first_name"
                                   name="first_name"
                                   x-model="firstName"
                                   required
                                   placeholder="First name"
                                   :disabled="isSavingName"
                                   class="w-full bg-transparent outline-none text-text-main text-base font-normal pt-1 placeholder:text-text-muted/40 disabled:opacity-60">
                        </div>
                        <template x-if="nameErrors.first_name">
                            <p class="text-danger text-xs mt-1.5 font-medium" x-text="nameErrors.first_name"></p>
                        </template>
                    </div>

                    <div>
                        <div class="border rounded-xl p-3 bg-surface transition-colors"
                             :class="nameErrors.last_name ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                            <label for="last_name" class="block text-xs font-normal text-text-muted">
                                Last name on ID
                            </label>
                            <input type="text"
                                   id="last_name"
                                   name="last_name"
                                   x-model="lastName"
                                   required
                                   placeholder="Last name"
                                   :disabled="isSavingName"
                                   class="w-full bg-transparent outline-none text-text-main text-base font-normal pt-1 placeholder:text-text-muted/40 disabled:opacity-60">
                        </div>
                        <template x-if="nameErrors.last_name">
                            <p class="text-danger text-xs mt-1.5 font-medium" x-text="nameErrors.last_name"></p>
                        </template>
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit"
                            :disabled="isSavingName"
                            :class="isSavingName ? 'pointer-events-none opacity-80' : 'cursor-pointer active:scale-95'"
                            class="h-10 w-20 flex items-center justify-center bg-text-main hover:bg-dark-grey text-surface font-semibold text-sm rounded-xl transition-colors select-none">
                        <span x-show="!isSavingName">Save</span>
                        <span x-show="isSavingName" x-cloak class="inline-flex items-center justify-center gap-1">
                            <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                            <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                            <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 2. Email Address Row -->
    <div class="transition-all duration-200"
         :class="isDimmed('email') ? 'opacity-40' : ''">

        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                    Email address
                </h3>
                <p x-show="activeEdit === 'email'" x-cloak class="text-xs sm:text-sm text-text-muted mt-2">
                    Use an address you’ll always have access to.
                </p>
                <p x-show="activeEdit !== 'email'" class="text-sm text-text-muted font-normal tracking-wide mt-2">
                    <span x-text="maskedEmail"></span>
                </p>
            </div>

            <div class="shrink-0 text-right leading-none">
                <button type="button"
                        @click="toggle('email')"
                        :disabled="isDimmed('email') || isSavingEmail || isSavingName"
                        :class="(isDimmed('email') || isSavingEmail || isSavingName) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                        class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                    <span x-text="activeEdit === 'email' ? 'Cancel' : 'Edit'"></span>
                </button>
            </div>
        </div>

        <div x-show="activeEdit === 'email'" x-cloak class="mt-4">
            <form action="{{ route('user.account-management.request-email-otp') }}"
                  method="POST"
                  @submit.prevent="submitEmail($event)"
                  class="space-y-4">
                @csrf

                <div class="w-full">
                    <div class="border rounded-xl p-3 bg-surface transition-colors"
                         :class="emailErrors.email ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                        <input type="email"
                               id="email"
                               name="email"
                               x-model="email"
                               required
                               placeholder="Email address"
                               :disabled="isSavingEmail"
                               class="w-full bg-transparent outline-none text-text-main text-base font-normal placeholder:text-text-muted/40 disabled:opacity-60">
                    </div>
                    <template x-if="emailErrors.email">
                        <p class="text-danger text-xs mt-1.5 font-medium" x-text="emailErrors.email"></p>
                    </template>
                </div>

                <div class="pt-1">
                    <button type="submit"
                            :disabled="isSavingEmail"
                            :class="isSavingEmail ? 'pointer-events-none opacity-80' : 'cursor-pointer active:scale-95'"
                            class="h-10 w-20 flex items-center justify-center bg-text-main hover:bg-dark-grey text-surface font-semibold text-sm rounded-xl transition-colors select-none">
                        <span x-show="!isSavingEmail">Save</span>
                        <span x-show="isSavingEmail" x-cloak class="inline-flex items-center justify-center gap-1">
                            <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                            <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                            <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 3. Phone Number -->
    <div class="flex items-start justify-between gap-4 transition-all duration-200"
         :class="isDimmed('phone') ? 'opacity-40' : ''">
        <div>
            <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                Phone Number
            </h3>
            @php
                $contact = $user->contact_no ?? '';
                $maskedContact = 'Not linked';
                if ($contact) {
                    $cleanContact = preg_replace('/\D/', '', $contact);
                    if (strlen($cleanContact) >= 8) {
                        $maskedContact = substr($cleanContact, 0, 5) . '****' . substr($cleanContact, -3);
                    } else {
                        $maskedContact = $contact;
                    }
                }
            @endphp
            <p class="text-sm text-text-muted font-normal tracking-wide mt-2">
                {{ $maskedContact }}
            </p>
        </div>
        <div class="shrink-0 text-right flex items-center gap-2 leading-none">
            <button type="button"
                    :disabled="isEditing() || isSavingName || isSavingEmail"
                    :class="(isEditing() || isSavingName || isSavingEmail) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer hover:opacity-75'"
                    class="text-sm font-light underline text-text-main transition-opacity leading-none">
                Verify
            </button>
            <span class="text-text-muted text-xs font-light">|</span>
            <button type="button"
                    @click="toggle('phone')"
                    :disabled="isDimmed('phone') || isSavingName || isSavingEmail"
                    :class="(isDimmed('phone') || isSavingName || isSavingEmail) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                    class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                <span x-text="activeEdit === 'phone' ? 'Cancel' : 'Edit'"></span>
            </button>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 4. Identity Verification -->
    <div class="flex items-start justify-between gap-4 transition-all duration-200"
         :class="isDimmed('identity') ? 'opacity-40' : ''">
        <div>
            <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                Identity Verification
            </h3>
            <p class="text-sm text-text-muted font-normal tracking-wide mt-2">
                Not started
            </p>
        </div>
        <div class="shrink-0 text-right leading-none">
            <button type="button"
                    @click="toggle('identity')"
                    :disabled="isDimmed('identity') || isSavingName || isSavingEmail"
                    :class="(isDimmed('identity') || isSavingName || isSavingEmail) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                    class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                <span x-text="activeEdit === 'identity' ? 'Cancel' : 'Start'"></span>
            </button>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 5. Change Password -->
    <div class="flex items-start justify-between gap-4 transition-all duration-200"
         :class="isDimmed('password') ? 'opacity-40' : ''">
        <div>
            <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                Change Password
            </h3>
            <p class="text-sm text-text-muted font-normal tracking-widest mt-2">
                ••••••••••
            </p>
        </div>
        <div class="shrink-0 text-right leading-none">
            <button type="button"
                    @click="toggle('password')"
                    :disabled="isDimmed('password') || isSavingName || isSavingEmail"
                    :class="(isDimmed('password') || isSavingName || isSavingEmail) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                    class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                <span x-text="activeEdit === 'password' ? 'Cancel' : 'Edit'"></span>
            </button>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 6. Delete Account Row -->
    <div class="flex items-start justify-between transition-all duration-200"
         :class="(isEditing() || isSavingName || isSavingEmail) ? 'opacity-40 pointer-events-none cursor-not-allowed' : 'group cursor-pointer hover:opacity-80'">
        <div>
            <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                Delete Account
            </h3>
            <p class="text-xs text-text-muted font-normal mt-2">
                NOTE: Account will NOT BE RECOVERABLE once deleted.
            </p>
        </div>
        <div class="text-text-muted shrink-0 pl-4 leading-none">
            <svg class="w-5 h-5 text-neutral-400 group-hover:text-text-main transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </div>

    <!-- 7. Confirm it's you - OTP Modal -->
    <template x-teleport="body">
        <div x-show="showOtpModal"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4">

            <!-- Modal Panel (Shadow removed, border styled, large rounded corners) -->
            <div @click.away="closeOtpModal()"
                 class="relative w-full max-w-md bg-surface border border-neutral-300 rounded-[28px] p-8 sm:p-10 text-center">

                <!-- Close Button -->
                <button type="button"
                        @click="closeOtpModal()"
                        class="absolute top-6 right-6 text-neutral-500 hover:text-text-main transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Header Title & Subtitle -->
                <h3 class="text-2xl font-bold text-text-main">
                    Confirm it’s you
                </h3>
                <p class="text-sm text-text-muted mt-2">
                    We sent a code to <span class="text-text-main font-medium" x-text="otpMaskedEmail"></span>.
                </p>

                <!-- Single Box Overlaid 6-Digit Container -->
                <div class="mt-8 mb-4 relative flex items-center justify-center">
                    <div class="relative w-full border border-neutral-400 focus-within:border-text-main rounded-2xl py-4 px-6 flex items-center justify-between cursor-text"
                         @click="document.getElementById('otp_hidden_input')?.focus()">

                        <!-- Hidden real input capturing user digits -->
                        <input type="text"
                               id="otp_hidden_input"
                               inputmode="numeric"
                               maxlength="6"
                               autocomplete="one-time-code"
                               @input="onOtpInput($event)"
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">

                        <!-- 6 Dash / Digit Slots -->
                        <template x-for="idx in [0, 1, 2, 3, 4, 5]" :key="idx">
                            <span class="w-8 text-center text-xl font-medium text-text-main tracking-widest select-none pointer-events-none"
                                  x-text="otpCode[idx] ? otpCode[idx] : '—'">
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Error Notice -->
                <template x-if="otpError">
                    <p class="text-danger text-xs font-medium mb-3" x-text="otpError"></p>
                </template>

                <!-- Verification Loading State -->
                <div x-show="isVerifyingOtp" class="mb-3 text-xs text-text-muted flex items-center justify-center gap-1.5 font-medium">
                    <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                    <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                    <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce"></span>
                    <span class="ml-1">Verifying code...</span>
                </div>

                <!-- Didn't get it? Send a new code -->
                <div class="mt-6 text-sm text-text-main font-normal">
                    <span>Didn’t get it? </span>
                    <button type="button"
                            @click="resendOtp()"
                            :disabled="resendCooldown > 0 || isResendingOtp"
                            :class="resendCooldown > 0 ? 'text-text-muted cursor-not-allowed opacity-60' : 'text-text-main underline font-semibold cursor-pointer hover:opacity-80'"
                            class="transition-opacity">
                        <span x-show="resendCooldown === 0 && !isResendingOtp">Send a new code</span>
                        <span x-show="isResendingOtp">Sending...</span>
                        <span x-show="resendCooldown > 0 && !isResendingOtp" x-text="'Resend in ' + resendCooldown + 's'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>
@endsection