@extends('layouts.seller', ['title' => 'Storkia - Store Settings'])

@section('content')
@php
    $initialFullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Seller-' . str_pad($user->id, 6, '0', STR_PAD_LEFT);
    $rawEmail = $user->email ?? '';
    $maskedEmail = $rawEmail;
    if (str_contains($rawEmail, '@')) {
        [$name, $domain] = explode('@', $rawEmail, 2);
        $maskedEmail = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 2, 4)) . substr($name, -1) . '@' . $domain;
    }
    $passwordLastUpdated = $user->formattedPasswordLastUpdated();

    // Phone formatting & initial state
    $contact = $user->contact_no ?: ($profile->contact_no ?? '');
    $cleanContact = preg_replace('/\D/', '', $contact);
    if (str_starts_with($cleanContact, '63')) {
        $cleanContact = substr($cleanContact, 2);
    }
    $cleanContact = ltrim($cleanContact, '0');

    $hasPhone = !empty($cleanContact);
    $maskedContact = 'Not provided';
    $formattedPhone = '';

    if ($hasPhone) {
        $fullDigits = '63' . $cleanContact;
        $maskedContact = strlen($fullDigits) >= 8
            ? substr($fullDigits, 0, 5) . '****' . substr($fullDigits, -3)
            : $fullDigits;

        $formattedPhone = substr($cleanContact, 0, 3);
        if (strlen($cleanContact) > 3) {
            $formattedPhone .= ' ' . substr($cleanContact, 3, 3);
        }
        if (strlen($cleanContact) > 6) {
            $formattedPhone .= ' ' . substr($cleanContact, 6, 4);
        }
    }

    $photoUrl = $profile->logo_path ? asset('storage/' . $profile->logo_path) : '';
    $fullAddress = collect([
        $profile->house_details,
        $profile->street,
        $profile->barangay,
        $profile->municipality,
        $profile->province,
    ])->filter()->implode(', ');

    $idExt = strtolower(pathinfo($profile->id_path ?? '', PATHINFO_EXTENSION));
    $permitExt = strtolower(pathinfo($profile->permit_path ?? '', PATHINFO_EXTENSION));

    
    $sellerData = [
        'business_name' => $profile->business_name ?? '',
        'photo_url' => $photoUrl,
        'first_name' => $user->first_name ?? '',
        'last_name' => $user->last_name ?? '',
        'full_name' => $initialFullName,
        'email' => $rawEmail,
        'masked_email' => $maskedEmail,
        'password_last_updated' => $passwordLastUpdated,
        'phone_raw' => $cleanContact,
        'formatted_phone' => $formattedPhone,
        'masked_phone' => $maskedContact,
        'has_phone' => $hasPhone
    ];
@endphp

<div x-data="sellerStoreSettingsManager(
    'store',
    null,
    {{ json_encode($sellerData) }}
)" class="max-w-5xl mx-auto select-none">

    <!-- Page Header & Segmented Tabs Navigation -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold text-text-main">Store Settings</h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Verified Merchant
                </span>
            </div>
            <p class="text-sm text-text-muted mt-1">
                Manage your storefront identity, verified business details, and personal login security.
            </p>
        </div>

        <!-- Segmented Pill Switcher -->
        <div class="relative grid grid-cols-2 p-1.5 bg-surface-subtle border border-border-subtle rounded-2xl shadow-xs self-start select-none">
            
            <!-- Animated Sliding Pill Background -->
            <div class="absolute top-1.5 bottom-1.5 left-1.5 w-[calc(50%-0.375rem)] bg-surface rounded-xl shadow-xs border border-border-subtle transition-transform duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] pointer-events-none"
                 :class="activeTab === 'security' ? 'translate-x-full' : 'translate-x-0'">
            </div>

            <!-- Tab 1: Store Profile -->
            <button type="button"
                    @click="setTab('store')"
                    :class="activeTab === 'store' ? 'text-primary-dark font-bold' : 'text-text-muted hover:text-text-main font-semibold'"
                    class="relative z-10 flex items-center justify-center gap-2 px-5 py-2 text-xs sm:text-sm rounded-xl transition-colors duration-200 cursor-pointer whitespace-nowrap focus:outline-none">
                <svg class="w-4 h-4 shrink-0 transition-transform duration-200"
                     :class="activeTab === 'store' ? 'scale-105 text-primary' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <span>Store Profile</span>
            </button>

            <!-- Tab 2: Account & Security -->
            <button type="button"
                    @click="setTab('security')"
                    :class="activeTab === 'security' ? 'text-primary-dark font-bold' : 'text-text-muted hover:text-text-main font-semibold'"
                    class="relative z-10 flex items-center justify-center gap-2 px-5 py-2 text-xs sm:text-sm rounded-xl transition-colors duration-200 cursor-pointer whitespace-nowrap focus:outline-none">
                <svg class="w-4 h-4 shrink-0 transition-transform duration-200"
                     :class="activeTab === 'security' ? 'scale-105 text-primary' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span>Account & Security</span>
            </button>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: STORE PROFILE                       -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'store'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="space-y-6">

        <!-- Card 1: Store Branding (Store Photo & Store Name) -->
        <div class="bg-surface rounded-3xl border border-border-subtle p-6 sm:p-8 shadow-xs space-y-6">
            <div class="border-b border-border-subtle pb-4">
                <h2 class="text-lg font-bold text-text-main">Storefront Identity</h2>
                <p class="text-xs text-text-muted mt-0.5">Customize your public shop avatar and store display name.</p>
            </div>

            <!-- 1A. Seller Store Photo / Logo -->
            <div class="transition-all duration-200" :class="isDimmed('photo') ? 'opacity-40' : ''">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <!-- Avatar Circle Preview -->
                        <div class="relative w-24 h-24 rounded-full border-2 border-border-subtle bg-brand-light/30 flex items-center justify-center overflow-hidden shadow-inner shrink-0 group">
                            <template x-if="photoUrl">
                                <img :src="photoUrl" alt="Store Photo" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!photoUrl">
                                <span class="text-3xl font-serif font-bold text-primary-dark" x-text="(businessName || 'S').charAt(0).toUpperCase()"></span>
                            </template>

                            <!-- Uploading Spinner Overlay -->
                            <div x-show="isUploadingPhoto" x-cloak class="absolute inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center">
                                <span class="inline-flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 bg-white rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                                    <span class="w-1.5 h-1.5 bg-white rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                                    <span class="w-1.5 h-1.5 bg-white rounded-full animate-bounce"></span>
                                </span>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-base font-semibold text-text-main">Store Photo / Logo</h3>
                            <p class="text-xs text-text-muted mt-1 max-w-md leading-relaxed">
                                Displayed on your store header, product listings, and customer chat. Automatically optimized to WebP (Max 5MB, JPG/PNG/WEBP).
                            </p>
                            <template x-if="photoError">
                                <p class="text-danger text-xs mt-1.5 font-medium" x-text="photoError"></p>
                            </template>
                        </div>
                    </div>

                    <!-- Upload / Remove Buttons -->
                    <div class="flex items-center gap-3 shrink-0">
                        <label for="seller_photo_input"
                               :class="(isEditing() || isUploadingPhoto || isRemovingPhoto) ? 'pointer-events-none opacity-50' : 'cursor-pointer hover:bg-dark-grey active:scale-95'"
                               class="px-4 py-2.5 bg-text-main text-surface text-xs font-semibold rounded-xl transition-all shadow-xs inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span x-text="photoUrl ? 'Change Photo' : 'Upload Photo'"></span>
                        </label>
                        <input id="seller_photo_input"
                               type="file"
                               accept="image/jpeg,image/png,image/jpg,image/webp"
                               @change="handlePhotoSelect($event)"
                               class="hidden">

                        <template x-if="photoUrl">
                            <button type="button"
                                    @click="removePhoto()"
                                    :disabled="isEditing() || isUploadingPhoto || isRemovingPhoto"
                                    class="px-3.5 py-2.5 border border-border-subtle text-danger hover:bg-red-50 text-xs font-semibold rounded-xl transition-colors cursor-pointer disabled:opacity-50">
                                Remove
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <hr class="border-border-subtle" />

            <!-- 1B. Store / Business Name (Editable) -->
            <div class="transition-all duration-200" :class="isDimmed('store_name') ? 'opacity-40' : ''">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                            Store Name
                        </h3>
                        <p x-show="activeEdit === 'store_name'" x-cloak class="text-xs sm:text-sm text-text-muted mt-2">
                            Choose a recognizable store name for buyers browsing your catalog.
                        </p>
                        <p x-show="activeEdit !== 'store_name'" class="text-sm text-text-muted font-normal tracking-wide mt-2">
                            <span x-text="businessName"></span>
                        </p>
                    </div>

                    <div class="shrink-0 text-right leading-none">
                        <button type="button"
                                @click="toggle('store_name')"
                                :disabled="isDimmed('store_name') || isAnySaving()"
                                :class="(isDimmed('store_name') || isAnySaving()) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                                class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                            <span x-text="activeEdit === 'store_name' ? 'Cancel' : 'Edit'"></span>
                        </button>
                    </div>
                </div>

                <!-- Inline Edit Form for Store Name -->
                <div x-show="activeEdit === 'store_name'" x-cloak class="mt-4">
                    <form action="{{ route('seller.store-settings.update-store-name') }}"
                          method="POST"
                          @submit.prevent="submitStoreName($event)"
                          class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="w-full max-w-lg">
                            <div class="border rounded-xl p-3 bg-surface transition-colors"
                                 :class="storeNameError ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                                <label for="business_name" class="block text-xs font-normal text-text-muted">
                                    Official Store / Business Name
                                </label>
                                <input type="text"
                                       id="business_name"
                                       name="business_name"
                                       x-model="businessNameInput"
                                       maxlength="80"
                                       required
                                       placeholder="Enter store name"
                                       :disabled="isSavingStoreName"
                                       class="w-full bg-transparent outline-none text-text-main text-base font-normal pt-1 placeholder:text-text-muted/40 disabled:opacity-60">
                            </div>
                            <template x-if="storeNameError">
                                <p class="text-danger text-xs mt-1.5 font-medium" x-text="storeNameError"></p>
                            </template>
                        </div>

                        <div class="pt-1">
                            <button type="submit"
                                    :disabled="isSavingStoreName"
                                    :class="isSavingStoreName ? 'pointer-events-none opacity-80' : 'cursor-pointer active:scale-95'"
                                    class="h-10 w-20 flex items-center justify-center bg-text-main hover:bg-dark-grey text-surface font-semibold text-sm rounded-xl transition-colors select-none">
                                <span x-show="!isSavingStoreName">Save</span>
                                <span x-show="isSavingStoreName" x-cloak class="inline-flex items-center justify-center gap-1">
                                    <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                                    <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                                    <span class="w-1.5 h-1.5 bg-surface rounded-full animate-bounce"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Card 2: Read-Only Verified Business Compliance Details -->
        <div class="bg-surface rounded-3xl border border-border-subtle p-6 sm:p-8 shadow-xs space-y-6">
            <div class="border-b border-border-subtle pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-bold text-primary-dark">Verified Business Information</h2>
                    <p class="text-xs text-text-muted mt-0.5">
                        Approved during merchant onboarding. Locked for marketplace compliance.
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold bg-surface-subtle text-text-muted border border-border-subtle self-start sm:self-auto">
                    <svg class="w-3.5 h-3.5 text-primary" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    Read-Only
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Line of Business / Category -->
                <div class="p-4 bg-surface-subtle rounded-2xl border border-border-subtle flex items-start justify-between gap-3">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted block">Line of Business / Primary Category</span>
                        <p class="text-sm font-bold text-text-main mt-1">{{ $profile->line_of_business }}</p>
                        <p class="text-[11px] text-text-muted mt-0.5">Determines eligible product subcategories.</p>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-green-100 text-green-700 border border-green-200 shrink-0">
                        Verified
                    </span>
                </div>

                <!-- Business / Pickup Address -->
                <div class="p-4 bg-surface-subtle rounded-2xl border border-border-subtle flex items-start justify-between gap-3">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted block">Registered Business Address</span>
                        <p class="text-sm font-bold text-text-main mt-1 leading-snug">{{ $fullAddress ?: 'Not specified' }}</p>
                        <p class="text-[11px] text-text-muted mt-0.5">Official pickup & return facility location.</p>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-green-100 text-green-700 border border-green-200 shrink-0">
                        Verified
                    </span>
                </div>
            </div>

            <!-- Legal Documents Previews -->
            <div class="pt-2">
                <span class="text-xs font-bold uppercase tracking-wider text-text-muted block mb-3">Submitted Legal Documents</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Valid Government ID -->
                    <div class="p-4 rounded-2xl border border-border-subtle bg-surface flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <rect x="3" y="4" width="18" height="16" rx="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h4m-4 4h8m-8 4h6" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-text-main truncate">Valid Government ID</p>
                                <span class="inline-flex items-center gap-1 text-[11px] text-green-700 font-semibold">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    Approved & On File
                                </span>
                            </div>
                        </div>
                        <button type="button"
                                @click="openDocPreview('Valid Government ID', '{{ route('seller.store-settings.document', 'id') }}', '{{ $idExt }}')"
                                class="px-3 py-1.5 bg-surface-subtle hover:bg-brand-light/40 text-text-main text-xs font-bold rounded-xl border border-border-subtle transition-colors cursor-pointer shrink-0">
                            Preview
                        </button>
                    </div>

                    <!-- Business Permit -->
                    <div class="p-4 rounded-2xl border border-border-subtle bg-surface flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-text-main truncate">Business / Mayor's Permit</p>
                                <span class="inline-flex items-center gap-1 text-[11px] text-green-700 font-semibold">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    Approved & On File
                                </span>
                            </div>
                        </div>
                        <button type="button"
                                @click="openDocPreview('Business / Mayor\'s Permit', '{{ route('seller.store-settings.document', 'permit') }}', '{{ $permitExt }}')"
                                class="px-3 py-1.5 bg-surface-subtle hover:bg-brand-light/40 text-text-main text-xs font-bold rounded-xl border border-border-subtle transition-colors cursor-pointer shrink-0">
                            Preview
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: ACCOUNT & SECURITY                  -->
    <!-- ========================================== -->
    <div x-show="activeTab === 'security'"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="bg-surface rounded-3xl border border-border-subtle p-6 sm:p-8 shadow-xs space-y-6">

        <div class="border-b border-border-subtle pb-4">
            <h2 class="text-lg font-bold text-primary-dark">Personal & Security Credentials</h2>
            <p class="text-xs text-text-muted mt-0.5">
                Manage your owner identity, contact number, and OTP-protected login credentials.
            </p>
        </div>

        <!-- 1. Full Name Row -->
        <div class="transition-all duration-200" :class="isDimmed('username') ? 'opacity-40' : ''">
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
                            :disabled="isDimmed('username') || isAnySaving()"
                            :class="(isDimmed('username') || isAnySaving()) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                            class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                        <span x-text="activeEdit === 'username' ? 'Cancel' : 'Edit'"></span>
                    </button>
                </div>
            </div>

            <div x-show="activeEdit === 'username'" x-cloak class="mt-4">
                <form action="{{ route('seller.store-settings.update-name') }}"
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
        <div class="transition-all duration-200" :class="isDimmed('email') ? 'opacity-40' : ''">
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
                            :disabled="isDimmed('email') || isAnySaving()"
                            :class="(isDimmed('email') || isAnySaving()) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                            class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                        <span x-text="activeEdit === 'email' ? 'Cancel' : 'Edit'"></span>
                    </button>
                </div>
            </div>

            <div x-show="activeEdit === 'email'" x-cloak class="mt-4">
                <form action="{{ route('seller.store-settings.request-email-otp') }}"
                      method="POST"
                      @submit.prevent="submitEmail($event)"
                      class="space-y-4">
                    @csrf

                    <div class="w-full">
                        <div class="border rounded-xl p-3 bg-surface transition-colors"
                             :class="emailErrors.email ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                            <input type="email"
                                   id="seller_email"
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

        <!-- 3. Phone Number Row (Syncs both users.contact_no and seller_profiles.contact_no) -->
        <div class="transition-all duration-200" :class="isDimmed('phone') ? 'opacity-40' : ''">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                        Phone Number
                    </h3>
                    <p x-show="activeEdit === 'phone'" x-cloak class="text-xs sm:text-sm text-text-muted mt-2">
                        Updates both your personal login security and official store contact number.
                    </p>
                    <p x-show="activeEdit !== 'phone'" class="text-sm text-text-muted font-normal tracking-wide mt-2">
                        <span x-text="hasPhone ? maskedPhone : 'Not provided'"></span>
                    </p>
                </div>

                <div class="shrink-0 text-right flex items-center gap-2 leading-none">
                    <template x-if="hasPhone && activeEdit !== 'phone'">
                        <div class="flex items-center gap-2 leading-none">
                            <button type="button"
                                    tabindex="-1"
                                    :disabled="true"
                                    class="text-sm font-light underline text-text-main opacity-50 cursor-not-allowed pointer-events-none transition-opacity leading-none">
                                Verify
                            </button>
                            <span class="text-text-muted text-xs font-light">|</span>
                        </div>
                    </template>

                    <button type="button"
                            @click="toggle('phone')"
                            :disabled="isDimmed('phone') || isAnySaving()"
                            :class="(isDimmed('phone') || isAnySaving()) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                            class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                        <span x-text="activeEdit === 'phone' ? 'Cancel' : (hasPhone ? 'Edit' : 'Add')"></span>
                    </button>
                </div>
            </div>

            <div x-show="activeEdit === 'phone'" x-cloak class="mt-4">
                <form action="{{ route('seller.store-settings.update-phone') }}"
                      method="POST"
                      @submit.prevent="submitPhone($event)"
                      class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="w-full">
                        <div class="border rounded-xl p-3 bg-surface transition-colors flex items-center"
                             :class="phoneErrors.phone ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                            <span class="text-base font-normal text-text-muted pr-2 select-none shrink-0">+63</span>
                            <input type="tel"
                                   id="seller_phone_number"
                                   name="contact_no"
                                   x-model="formattedPhone"
                                   @input="formatPhoneInput($event)"
                                   placeholder="9XX XXX XXXX"
                                   maxlength="12"
                                   required
                                   :disabled="isSavingPhone"
                                   class="w-full bg-transparent outline-none text-text-main text-base font-normal placeholder:text-text-muted/40 disabled:opacity-60">
                        </div>
                        <template x-if="phoneErrors.phone">
                            <p class="text-danger text-xs mt-1.5 font-medium" x-text="phoneErrors.phone"></p>
                        </template>
                    </div>

                    <div class="pt-1">
                        <button type="submit"
                                :disabled="isSavingPhone"
                                :class="isSavingPhone ? 'pointer-events-none opacity-80' : 'cursor-pointer active:scale-95'"
                                class="h-10 w-20 flex items-center justify-center bg-text-main hover:bg-dark-grey text-surface font-semibold text-sm rounded-xl transition-colors select-none">
                            <span x-show="!isSavingPhone">Save</span>
                            <span x-show="isSavingPhone" x-cloak class="inline-flex items-center justify-center gap-1">
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

        <!-- 4. Password Row -->
        <div class="transition-all duration-200" :class="isDimmed('password') ? 'opacity-40' : ''">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                        Password
                    </h3>
                    <p class="text-sm text-text-muted font-normal tracking-wide mt-2">
                        <span x-text="passwordLastUpdated"></span>
                    </p>
                </div>

                <div class="shrink-0 text-right leading-none">
                    <button type="button"
                            @click="handlePasswordClick()"
                            :disabled="isDimmed('password') || isAnySaving()"
                            :class="(isDimmed('password') || isAnySaving()) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                            class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                        <span x-text="activeEdit === 'password' ? 'Cancel' : 'Update'"></span>
                    </button>
                </div>
            </div>

            <div x-show="activeEdit === 'password'" x-cloak class="mt-4">
                <form action="{{ route('seller.store-settings.update-password') }}"
                      method="POST"
                      @submit.prevent="submitPassword($event)"
                      class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" x-data="{ showNewPass: false, showConfirmPass: false }">
                        <div>
                            <div class="relative border rounded-xl p-3 bg-surface transition-colors"
                                 :class="passwordErrors.password ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                                <label for="seller_new_password" class="block text-xs font-normal text-text-muted">
                                    New password
                                </label>
                                <input :type="showNewPass ? 'text' : 'password'"
                                       id="seller_new_password"
                                       name="password"
                                       x-model="newPassword"
                                       @input="validatePasswordClientSide()"
                                       required
                                       placeholder="New password"
                                       :disabled="isSavingPassword"
                                       class="w-full bg-transparent outline-none text-text-main text-base font-normal pt-1 pr-8 placeholder:text-text-muted/40 disabled:opacity-60">
                                <button type="button"
                                        @click="showNewPass = !showNewPass"
                                        class="absolute right-3 top-6 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                                    <svg x-show="!showNewPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg x-show="showNewPass" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                </button>
                            </div>
                            <template x-if="passwordErrors.password">
                                <p class="text-danger text-xs mt-1.5 font-medium" x-text="passwordErrors.password"></p>
                            </template>

                            <div class="mt-2 grid grid-cols-2 gap-1.5 text-[11px] text-text-muted">
                                <span class="flex items-center gap-1" :class="newPassword.length >= 8 ? 'text-success font-semibold' : ''">
                                    <span x-text="newPassword.length >= 8 ? '✓' : '•'"></span> 8+ characters
                                </span>
                                <span class="flex items-center gap-1" :class="/[A-Z]/.test(newPassword) ? 'text-success font-semibold' : ''">
                                    <span x-text="/[A-Z]/.test(newPassword) ? '✓' : '•'"></span> 1 uppercase
                                </span>
                                <span class="flex items-center gap-1" :class="/[0-9]/.test(newPassword) ? 'text-success font-semibold' : ''">
                                    <span x-text="/[0-9]/.test(newPassword) ? '✓' : '•'"></span> 1 number
                                </span>
                                <span class="flex items-center gap-1" :class="/[\W_]/.test(newPassword) ? 'text-success font-semibold' : ''">
                                    <span x-text="/[\W_]/.test(newPassword) ? '✓' : '•'"></span> 1 special symbol
                                </span>
                            </div>
                        </div>

                        <div>
                            <div class="relative border rounded-xl p-3 bg-surface transition-colors"
                                 :class="passwordErrors.password_confirmation ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                                <label for="seller_confirm_password" class="block text-xs font-normal text-text-muted">
                                    Confirm password
                                </label>
                                <input :type="showConfirmPass ? 'text' : 'password'"
                                       id="seller_confirm_password"
                                       name="password_confirmation"
                                       x-model="confirmPassword"
                                       @input="validatePasswordClientSide()"
                                       required
                                       placeholder="Confirm password"
                                       :disabled="isSavingPassword"
                                       class="w-full bg-transparent outline-none text-text-main text-base font-normal pt-1 pr-8 placeholder:text-text-muted/40 disabled:opacity-60">
                                <button type="button"
                                        @click="showConfirmPass = !showConfirmPass"
                                        class="absolute right-3 top-6 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                                    <svg x-show="!showConfirmPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg x-show="showConfirmPass" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                </button>
                            </div>
                            <template x-if="passwordErrors.password_confirmation">
                                <p class="text-danger text-xs mt-1.5 font-medium" x-text="passwordErrors.password_confirmation"></p>
                            </template>
                        </div>
                    </div>

                    <div class="pt-1">
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
    </div>

    <!-- ========================================== -->
    <!-- MODALS: DOCUMENT PREVIEW & OTP             -->
    <!-- ========================================== -->

    <!-- Inline Verified Document Preview Modal -->
    <template x-teleport="body">
        <div x-show="docModalOpen"
             x-cloak
             x-transition.opacity
             class="fixed inset-0 z-[190] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
            <div @click.away="docModalOpen = false"
                 class="bg-surface rounded-3xl max-w-4xl w-full p-4 sm:p-6 shadow-2xl border border-border-subtle flex flex-col h-[82vh]">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-sm text-primary-dark truncate" x-text="docModalTitle"></h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">Verified</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <a :href="docModalUrl" target="_blank" class="text-xs font-bold text-primary hover:underline">Open in New Tab</a>
                        <button type="button" @click="docModalOpen = false" class="p-1 rounded-lg hover:bg-surface-subtle text-text-muted hover:text-text-main cursor-pointer">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto p-2 flex items-center justify-center bg-surface-subtle rounded-2xl mt-3">
                    <template x-if="docModalType === 'image'">
                        <img :src="docModalUrl" alt="Verified Document" class="max-h-full max-w-full object-contain rounded-lg shadow-sm">
                    </template>
                    <template x-if="docModalType === 'pdf'">
                        <iframe :src="docModalUrl" class="w-full h-full rounded-lg border-0"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- Reusable Email OTP Modal -->
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
        inputId="seller_otp_hidden_input"
    />

    <!-- Reusable Password OTP Modal -->
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
        inputId="seller_password_otp_hidden_input"
    />

</div>
@endsection