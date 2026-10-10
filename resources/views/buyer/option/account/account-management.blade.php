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
    $passwordLastUpdated = $user->formattedPasswordLastUpdated();
@endphp

<div x-data="accountManager(
    {{ $errors->hasAny(['first_name', 'last_name']) ? "'username'" : ($errors->has('email') ? "'email'" : 'null') }},
    {
        first_name: @js($user->first_name ?? ''),
        last_name: @js($user->last_name ?? ''),
        full_name: @js($initialFullName),
        email: @js($rawEmail),
        masked_email: @js($maskedEmail),
        password_last_updated: @js($passwordLastUpdated)
    }
)" class="space-y-6 pt-0 select-none">

    <!-- Header Title -->
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
                        :disabled="isDimmed('username') || isSavingName || isSavingEmail || isSavingPassword"
                        :class="(isDimmed('username') || isSavingName || isSavingEmail || isSavingPassword) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
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
                        :disabled="isDimmed('email') || isSavingEmail || isSavingName || isSavingPassword"
                        :class="(isDimmed('email') || isSavingEmail || isSavingName || isSavingPassword) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
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
                    :disabled="isEditing() || isSavingName || isSavingEmail || isSavingPassword"
                    :class="(isEditing() || isSavingName || isSavingEmail || isSavingPassword) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer hover:opacity-75'"
                    class="text-sm font-light underline text-text-main transition-opacity leading-none">
                Verify
            </button>
            <span class="text-text-muted text-xs font-light">|</span>
            <button type="button"
                    @click="toggle('phone')"
                    :disabled="isDimmed('phone') || isSavingName || isSavingEmail || isSavingPassword"
                    :class="(isDimmed('phone') || isSavingName || isSavingEmail || isSavingPassword) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
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
                    :disabled="isDimmed('identity') || isSavingName || isSavingEmail || isSavingPassword"
                    :class="(isDimmed('identity') || isSavingName || isSavingEmail || isSavingPassword) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                    class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                <span x-text="activeEdit === 'identity' ? 'Cancel' : 'Start'"></span>
            </button>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 5. Change Password -->
    <div class="transition-all duration-200"
         :class="isDimmed('password') ? 'opacity-40' : ''">

        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                    Password
                </h3>
                <!-- Status text hidden when editing/creating new password -->
                <p x-show="activeEdit !== 'password'" class="text-sm text-text-muted font-normal tracking-wide mt-2">
                    <span x-text="passwordLastUpdated"></span>
                </p>
            </div>

            <div class="shrink-0 text-right leading-none">
                <button type="button"
                        @click="handlePasswordClick()"
                        :disabled="isDimmed('password') || isSendingPasswordCode || isSavingName || isSavingEmail || isSavingPassword"
                        :class="(isDimmed('password') || isSendingPasswordCode || isSavingName || isSavingEmail || isSavingPassword) 
                            ? 'opacity-60 cursor-not-allowed pointer-events-none' 
                            : 'cursor-pointer hover:opacity-75'"
                        class="text-sm font-light underline text-text-main transition-all inline-flex items-center gap-1.5 leading-none">
                    
                    <!-- Normal Update / Cancel Text -->
                    <span x-show="!isSendingPasswordCode" x-text="activeEdit === 'password' ? 'Cancel' : 'Update'"></span>

                    <!-- Sending Code State  -->
                    <span x-show="isSendingPasswordCode" x-cloak class="no-underline">
                        Sending code...
                    </span>
                </button>
            </div>
        </div>

        <!-- Vertically Stacked New Password & Confirm Password Form -->
        <div x-show="activeEdit === 'password'" x-cloak class="mt-5">
            <form action="{{ route('user.account-management.update-password') }}"
                  method="POST"
                  @submit.prevent="submitPassword($event)"
                  class="space-y-4 max-w-xl"
                  x-data="{ showNewPass: false, showConfirmPass: false }">
                @csrf
                @method('PATCH')

                <!-- 1. New Password Field (Stacked with Label Outside) -->
                <div>
                    <label for="new_password" class="block text-xs font-semibold text-text-main mb-1.5">
                        New password
                    </label>
                    <div class="relative border border-neutral-400 focus-within:border-text-main rounded-xl p-3 bg-surface transition-colors flex items-center"
                         :class="passwordErrors.password ? 'border-danger' : ''">
                        <input :type="showNewPass ? 'text' : 'password'"
                               id="new_password"
                               name="password"
                               x-model="newPassword"
                               @input="validatePasswordClientSide()"
                               required
                               :disabled="isSavingPassword"
                               class="w-full bg-transparent outline-none text-text-main text-base font-normal pr-8 disabled:opacity-60">
                        <button type="button" 
                                @click="showNewPass = !showNewPass" 
                                class="absolute right-3.5 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                            <svg x-show="!showNewPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showNewPass" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <template x-if="passwordErrors.password">
                        <p class="text-danger text-xs mt-1.5 font-medium" x-text="passwordErrors.password"></p>
                    </template>
                </div>

                <!-- 2. Confirm Password Field (Stacked with Label Outside) -->
                <div>
                    <label for="confirm_password" class="block text-xs font-semibold text-text-main mb-1.5">
                        Confirm password
                    </label>
                    <div class="relative border border-neutral-400 focus-within:border-text-main rounded-xl p-3 bg-surface transition-colors flex items-center"
                         :class="passwordErrors.password_confirmation ? 'border-danger' : ''">
                        <input :type="showConfirmPass ? 'text' : 'password'"
                               id="confirm_password"
                               name="password_confirmation"
                               x-model="confirmPassword"
                               @input="validatePasswordClientSide()"
                               required
                               :disabled="isSavingPassword"
                               class="w-full bg-transparent outline-none text-text-main text-base font-normal pr-8 disabled:opacity-60">
                        <button type="button" 
                                @click="showConfirmPass = !showConfirmPass" 
                                class="absolute right-3.5 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                            <svg x-show="!showConfirmPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showConfirmPass" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <template x-if="passwordErrors.password_confirmation">
                        <p class="text-danger text-xs mt-1.5 font-medium" x-text="passwordErrors.password_confirmation"></p>
                    </template>
                </div>

                <!-- Update Password Button with 3 Bouncing Dots -->
                <div class="pt-2">
                    <button type="submit"
                            :disabled="isSavingPassword"
                            :class="isSavingPassword ? 'pointer-events-none opacity-80' : 'cursor-pointer active:scale-95'"
                            class="h-10 px-5 min-w-[150px] flex items-center justify-center bg-text-main hover:bg-dark-grey text-surface font-semibold text-sm rounded-xl transition-colors select-none">
                        <span x-show="!isSavingPassword">Update password</span>
                        <span x-show="isSavingPassword" x-cloak class="inline-flex items-center justify-center gap-1">
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

    <!-- 6. Delete Account Row -->
    <div class="flex items-start justify-between transition-all duration-200"
         :class="(isEditing() || isSavingName || isSavingEmail || isSavingPassword) ? 'opacity-40 pointer-events-none cursor-not-allowed' : 'group cursor-pointer hover:opacity-80'">
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

    <!-- Reusable OTP Modal: Used for Email Verification -->
    <x-ui.otp-modal 
        show="showOtpModal" 
        close="closeOtpModal()" 
        title="Confirm it’s you" 
        maskedTarget="otpMaskedEmail" 
        codeModel="otpCode" 
        onInput="onOtpInput($event)" 
        errorMessage="otpError" 
        isVerifying="isVerifyingOtp" 
        verifyingText="Verifying code..." 
        resendAction="resendOtp()" 
        cooldownModel="resendCooldown" 
        isResending="isResendingOtp" 
        inputId="otp_hidden_input" 
    />

    <!-- Reusable OTP Modal: Used for Password Change Verification -->
    <x-ui.otp-modal 
        show="showPasswordOtpModal" 
        close="closePasswordOtpModal()" 
        title="Confirm it’s you" 
        maskedTarget="passwordOtpMaskedEmail" 
        codeModel="passwordOtpCode" 
        onInput="onPasswordOtpInput($event)" 
        errorMessage="passwordOtpError" 
        isVerifying="isVerifyingPasswordOtp" 
        verifyingText="Verifying code..." 
        resendAction="resendPasswordOtp()" 
        cooldownModel="passwordResendCooldown" 
        isResending="isResendingPasswordOtp" 
        inputId="password_otp_hidden_input" 
    />

</div>
@endsection