{{--
    Delete Account modal (Account Management).

    Place this inside an element that has x-data="deleteAccountManager(...)":
    every binding below reads that component's state. The modal itself is
    teleported to <body>, like x-ui.otp-modal, so no parent can clip it.
--}}
<template x-teleport="body">
    <div x-show="showModal"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="showModal && closeModal()"
         @mousedown.self="closeModal()"
         role="dialog"
         aria-modal="true"
         aria-labelledby="delete_account_title"
         class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4">

        <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto bg-surface border border-neutral-300 rounded-[28px] p-6 sm:p-8 shadow-xl">

            <!-- Close Button -->
            <button type="button"
                    @click="closeModal()"
                    :disabled="isDeleting"
                    aria-label="Close"
                    class="absolute top-5 right-5 text-neutral-500 hover:text-text-main transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Title -->
            <h3 id="delete_account_title"
                class="pr-8 text-xl sm:text-2xl font-bold text-text-main"
                x-text="modalTitle()"></h3>

            <!-- A. Checking eligibility -->
            <div x-show="step === 'checking'"
                 class="py-10 flex items-center justify-center gap-1.5 text-sm text-text-muted font-medium">
                <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce"></span>
                <span class="ml-1">Checking your account...</span>
            </div>

            <!-- B. Could not check (network / server) -->
            <div x-show="step === 'error'" x-cloak class="mt-3">
                <p class="text-sm text-text-muted" x-text="loadError"></p>

                <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button type="button"
                            @click="closeModal()"
                            class="h-11 px-5 rounded-xl border border-neutral-400 text-sm font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer">
                        Close
                    </button>
                    <button type="button"
                            @click="checkEligibility()"
                            class="h-11 px-5 rounded-xl bg-text-main hover:bg-dark-grey text-surface text-sm font-semibold transition-colors cursor-pointer active:scale-95">
                        Try again
                    </button>
                </div>
            </div>

            <!-- C. Blocked: open orders, unresolved dispute or pending refund -->
            <div x-show="step === 'blocked'" x-cloak class="mt-3">
                <p class="text-sm text-text-muted">
                    Please sort out the following first, then come back to delete your account.
                </p>

                <ul class="mt-4 space-y-2.5">
                    <template x-for="(blocker, index) in blockers" :key="index">
                        <li class="flex items-start gap-3 rounded-2xl border border-danger/30 bg-danger/10 p-3.5">
                            <svg class="w-5 h-5 shrink-0 text-danger mt-px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            <span class="text-sm text-text-main" x-text="blocker"></span>
                        </li>
                    </template>
                </ul>

                <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button type="button"
                            @click="closeModal()"
                            class="h-11 px-5 rounded-xl border border-neutral-400 text-sm font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer">
                        Close
                    </button>
                    <a x-show="ordersUrl"
                       :href="ordersUrl"
                       class="h-11 px-5 inline-flex items-center justify-center rounded-xl bg-text-main hover:bg-dark-grey text-surface text-sm font-semibold transition-colors cursor-pointer active:scale-95">
                        View my orders
                    </a>
                </div>
            </div>

            <!-- D. Confirmation form -->
            <form id="delete_account_form"
                  x-show="step === 'form'"
                  x-cloak
                  @submit.prevent="submit()"
                  novalidate
                  class="mt-3">

                <!-- What deleting means -->
                <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4">
                    <p class="text-sm font-semibold text-text-main">Before you go</p>
                    <ul class="mt-2 space-y-1.5 text-sm text-text-main list-disc pl-5">
                        <li>You will be signed out on all your devices right away.</li>
                        <li>You won’t be able to log in to this account again.</li>
                        <li>Your order history is kept for Storkia and seller records.</li>
                        <li>Only Storkia support can bring a deleted account back.</li>
                    </ul>
                </div>

                <!-- Form-level error -->
                <template x-if="formError">
                    <div role="alert"
                         class="mt-4 rounded-xl border border-danger/30 bg-danger/10 px-3.5 py-3 text-sm text-danger font-medium"
                         x-text="formError"></div>
                </template>

                <!-- Reason -->
                <fieldset class="mt-5">
                    <legend class="text-sm font-semibold text-text-main">Why are you leaving?</legend>

                    <div class="mt-3 space-y-2">
                        <template x-for="option in reasonOptions" :key="option.value">
                            <label class="flex items-center gap-3 border rounded-xl px-3.5 py-3 cursor-pointer transition-colors"
                                   :class="reason === option.value ? 'border-text-main bg-surface-subtle' : 'border-neutral-300 hover:border-neutral-400'">
                                <input type="radio"
                                       name="reason"
                                       :value="option.value"
                                       x-model="reason"
                                       @change="errors.reason = null"
                                       :disabled="isDeleting"
                                       class="w-4 h-4 shrink-0 accent-text-main cursor-pointer">
                                <span class="text-sm text-text-main" x-text="option.label"></span>
                            </label>
                        </template>
                    </div>

                    <template x-if="errors.reason">
                        <p class="text-danger text-xs mt-1.5 font-medium" x-text="errors.reason"></p>
                    </template>
                </fieldset>

                <!-- Optional note -->
                <div class="mt-5">
                    <div class="border rounded-xl p-3 bg-surface transition-colors"
                         :class="errors.note ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                        <label for="delete_account_note"
                               class="flex items-center justify-between gap-3 text-xs font-normal text-text-muted">
                            <span>Anything you’d like to tell us? (optional)</span>
                            <span x-text="note.length + '/' + noteMaxLength"></span>
                        </label>
                        <textarea id="delete_account_note"
                                  rows="3"
                                  :maxlength="noteMaxLength"
                                  :value="note"
                                  @input="onNoteInput($event)"
                                  :disabled="isDeleting"
                                  placeholder="Your feedback helps us improve Storkia."
                                  class="w-full bg-transparent outline-none resize-none text-text-main text-base font-normal pt-1 placeholder:text-text-muted/40 disabled:opacity-60"></textarea>
                    </div>
                    <template x-if="errors.note">
                        <p class="text-danger text-xs mt-1.5 font-medium" x-text="errors.note"></p>
                    </template>
                </div>

                <!-- Acknowledgement -->
                <div class="mt-5">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox"
                               x-model="acknowledged"
                               @change="errors.acknowledged = null"
                               :disabled="isDeleting"
                               class="mt-0.5 w-4 h-4 shrink-0 accent-text-main cursor-pointer">
                        <span class="text-sm text-text-main">
                            I understand that I’ll be signed out everywhere and won’t be able to use this account again.
                        </span>
                    </label>
                    <template x-if="errors.acknowledged">
                        <p class="text-danger text-xs mt-1.5 font-medium" x-text="errors.acknowledged"></p>
                    </template>
                </div>

                <!-- Password confirmation -->
                <div class="mt-5">
                    <div class="relative border rounded-xl p-3 bg-surface transition-colors"
                         :class="errors.password ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                        <label for="delete_account_password" class="block text-xs font-normal text-text-muted">
                            Enter your password to confirm
                        </label>
                        <input :type="showPassword ? 'text' : 'password'"
                               id="delete_account_password"
                               x-model="password"
                               @input="errors.password = null"
                               autocomplete="current-password"
                               placeholder="Password"
                               :disabled="isDeleting"
                               class="w-full bg-transparent outline-none text-text-main text-base font-normal pt-1 pr-8 placeholder:text-text-muted/40 disabled:opacity-60">
                        <button type="button"
                                @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                class="absolute right-3 top-6 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <template x-if="errors.password">
                        <p class="text-danger text-xs mt-1.5 font-medium" x-text="errors.password"></p>
                    </template>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button type="button"
                            @click="closeModal()"
                            :disabled="isDeleting"
                            class="h-11 px-5 rounded-xl border border-neutral-400 text-sm font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                        Keep my account
                    </button>

                    <button type="submit"
                            :disabled="isDeleting"
                            :class="isDeleting ? 'pointer-events-none opacity-80' : 'cursor-pointer active:scale-95'"
                            class="h-11 min-w-[160px] px-5 flex items-center justify-center bg-danger hover:opacity-90 text-surface font-semibold text-sm rounded-xl transition-all select-none">
                        <span x-show="!isDeleting">Delete account</span>
                        <span x-show="isDeleting" x-cloak class="inline-flex items-center justify-center gap-1">
                            <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                            <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                            <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>