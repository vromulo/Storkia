@extends('buyer.account-settings', [
    'pageTitle' => 'Profile',
    'pageSubtitle' => 'Manage your public username and avatar.'
])

@section('option-content')
@php
    $avatarUrl = $profile->avatar_url ?? '';
    $currentUsername = $profile->username ?? '';
    $initial = strtoupper(substr($currentUsername ?: ($user->first_name ?? 'U'), 0, 1));
    $canChangeUsername = $profile->canChangeUsername();
    $cooldownText = '';
    if (! $canChangeUsername && $profile->username_changed_at) {
        $cooldownText = 'Available ' . $profile->username_changed_at->copy()->addDays(7)->diffForHumans();
    }

    $profileConfig = [
        'avatar_url'    => $avatarUrl,
        'username'      => $currentUsername,
        'can_change'    => $canChangeUsername,
        'cooldown_text' => $cooldownText,
    ];
@endphp

<div x-data="profileManager(null, {{ json_encode($profileConfig) }})" class="space-y-6 pt-0 select-none">

    <!-- Card: Public Profile -->
    <div class="bg-surface rounded-3xl border border-border-subtle p-6 sm:p-8 shadow-xs space-y-6">
        <div class="border-b border-border-subtle pb-4">
            <h2 class="text-lg font-bold text-text-main">Public Profile</h2>
            <p class="text-xs text-text-muted mt-0.5">Customize your public avatar and username handle.</p>
        </div>

        <!-- 1. Avatar Upload -->
        <div class="transition-all duration-200" :class="isDimmed('avatar') ? 'opacity-40' : ''">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                <div class="flex items-center gap-5">
                    <!-- Circle Preview -->
                    <div class="relative w-24 h-24 rounded-full border-2 border-border-subtle bg-brand-light/30 flex items-center justify-center overflow-hidden shadow-inner shrink-0 group">
                        <img x-show="avatarUrl"
                             :src="avatarUrl"
                             src="{{ $avatarUrl }}"
                             alt="Avatar"
                             class="w-full h-full object-cover"
                             @if(!$avatarUrl) style="display: none;" @endif>

                        <span x-show="!avatarUrl"
                              class="text-3xl font-serif font-bold text-primary-dark"
                              x-text="((username || 'U').charAt(0)).toUpperCase()"
                              @if($avatarUrl) style="display: none;" @endif>{{ $initial }}</span>

                        <!-- Uploading Spinner Overlay -->
                        <div x-show="isUploadingAvatar" x-cloak class="absolute inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center">
                            <span class="inline-flex items-center gap-1">
                                <span class="w-1.5 h-1.5 bg-white rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                                <span class="w-1.5 h-1.5 bg-white rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                                <span class="w-1.5 h-1.5 bg-white rounded-full animate-bounce"></span>
                            </span>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-text-main">Avatar Image</h3>
                        <p class="text-xs text-text-muted mt-1 max-w-md leading-relaxed">
                            Displayed on your public profile, reviews, and customer messages. Automatically optimized to WebP (Max 5MB, JPG/PNG/WEBP).
                        </p>
                        <p x-show="avatarError" x-cloak class="text-danger text-xs mt-1.5 font-medium" x-text="avatarError"></p>
                    </div>
                </div>

                <!-- Upload / Remove Actions -->
                <div class="flex items-center gap-3 shrink-0">
                    <label for="buyer_avatar_input"
                           :class="(isEditing() || isUploadingAvatar || isRemovingAvatar) ? 'pointer-events-none opacity-50' : 'cursor-pointer hover:bg-dark-grey active:scale-95'"
                           class="px-4 py-2.5 bg-text-main text-surface text-xs font-semibold rounded-xl transition-all shadow-xs inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span x-text="avatarUrl ? 'Change Avatar' : 'Upload Avatar'">{{ $avatarUrl ? 'Change Avatar' : 'Upload Avatar' }}</span>
                    </label>
                    <input id="buyer_avatar_input"
                           type="file"
                           accept="image/jpeg,image/png,image/jpg,image/webp"
                           @change="handleAvatarSelect($event)"
                           class="hidden">

                    <button type="button"
                            x-show="avatarUrl"
                            x-cloak
                            @click="removeAvatar()"
                            :disabled="isEditing() || isUploadingAvatar || isRemovingAvatar"
                            class="px-3.5 py-2.5 border border-border-subtle text-danger hover:bg-red-50 text-xs font-semibold rounded-xl transition-colors cursor-pointer disabled:opacity-50">
                        Remove
                    </button>
                </div>
            </div>
        </div>

        <hr class="border-border-subtle" />

        <!-- 2. Username Row -->
        <div class="transition-all duration-200" :class="isDimmed('username') ? 'opacity-40' : ''">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base sm:text-lg font-semibold text-text-main leading-none">
                        Username
                    </h3>
                    <p x-show="activeEdit === 'username'" x-cloak class="text-xs sm:text-sm text-text-muted mt-2">
                        Enter between 3 and 30 characters. You can update your username once every 7 days.
                    </p>
                    <div x-show="activeEdit !== 'username'" class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-text-main" x-text="username ? '@' + username : 'Not set'">{{ $currentUsername ? '@' . $currentUsername : 'Not set' }}</span>
                        <span x-show="!canChangeUsername && cooldownText"
                              x-cloak
                              class="text-[11px] font-medium text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200"
                              x-text="cooldownText">{{ $cooldownText }}</span>
                    </div>
                </div>

                <div class="shrink-0 text-right leading-none">
                    <button type="button"
                            @click="toggle('username')"
                            :disabled="isDimmed('username') || isSavingUsername"
                            :class="(isDimmed('username') || isSavingUsername) ? 'pointer-events-none cursor-not-allowed' : 'cursor-pointer'"
                            class="text-sm font-light underline text-text-main hover:opacity-75 transition-opacity inline-block leading-none">
                        <span x-text="activeEdit === 'username' ? 'Cancel' : 'Edit'">Edit</span>
                    </button>
                </div>
            </div>

            <!-- Inline Username Form -->
            <div x-show="activeEdit === 'username'" x-cloak class="mt-4">
                <form action="{{ route('user.profile.update-username') }}"
                      method="POST"
                      @submit.prevent="submitUsername($event)"
                      class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="w-full max-w-lg">
                        <div class="border rounded-xl p-3 bg-surface transition-colors flex items-center"
                             :class="usernameError ? 'border-danger' : 'border-neutral-400 focus-within:border-text-main'">
                            <span class="text-base font-normal text-text-muted pr-1.5 select-none shrink-0">@</span>
                            <input type="text"
                                   id="username"
                                   name="username"
                                   x-model="usernameInput"
                                   value="{{ $currentUsername }}"
                                   minlength="3"
                                   maxlength="30"
                                   required
                                   placeholder="username"
                                   :disabled="isSavingUsername"
                                   class="w-full bg-transparent outline-none text-text-main text-base font-normal placeholder:text-text-muted/40 disabled:opacity-60">
                        </div>

                        <p x-show="usernameError" x-cloak class="text-danger text-xs mt-1.5 font-medium" x-text="usernameError"></p>

                        <div class="flex items-center justify-between text-[11px] text-text-muted mt-1 px-1">
                            <span>3–30 characters (unique & case-insensitive)</span>
                            <span x-text="`${(usernameInput || '').length}/30`">{{ strlen($currentUsername) }}/30</span>
                        </div>
                    </div>

                    <div class="pt-1">
                        <button type="submit"
                                :disabled="isSavingUsername"
                                :class="isSavingUsername ? 'pointer-events-none opacity-80' : 'cursor-pointer active:scale-95'"
                                class="h-10 w-20 flex items-center justify-center bg-text-main hover:bg-dark-grey text-surface font-semibold text-sm rounded-xl transition-colors select-none">
                            <span x-show="!isSavingUsername">Save</span>
                            <span x-show="isSavingUsername" x-cloak class="inline-flex items-center justify-center gap-1">
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

</div>
@endsection