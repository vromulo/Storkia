@extends('buyer.personal-center', [
    'pageTitle' => 'My Account'
])

@section('option-content')
<div class="space-y-6 pt-0">

    <!-- Header Title -->
    <div class="pt-0 pb-2 text-center">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-text-main tracking-tight">
            Manage My Account
        </h2>
    </div>

    <!-- 1. Username -->
    <div class="flex items-center justify-between gap-4">
        <div class="space-y-1">
            <h3 class="text-sm sm:text-base font-bold text-text-main">
                Full Name
            </h3>
            <p class="text-sm text-text-muted font-normal tracking-wide">
                {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'User-' . str_pad($user->id, 6, '0', STR_PAD_LEFT) }}
            </p>
        </div>
        <div class="shrink-0 text-right">
            <a href="{{ route('user.profile') }}" class="text-sm font-thin underline text-text-main hover:opacity-75 transition-opacity">
                Edit
            </a>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 2. Email -->
    <div class="flex items-center justify-between gap-4">
        <div class="space-y-1">
            <h3 class="text-sm sm:text-base font-bold text-text-main">
                Email
            </h3>
            @php
                $email = $user->email ?? '';
                $maskedEmail = $email;
                if (str_contains($email, '@')) {
                    [$name, $domain] = explode('@', $email, 2);
                    $maskedEmail = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 2, 4)) . substr($name, -1) . '@' . $domain;
                }
            @endphp
            <p class="text-sm text-text-muted font-normal tracking-wide">
                {{ $maskedEmail }}
            </p>
        </div>
        <div class="shrink-0 text-right">
            <a href="{{ route('user.profile') }}" class="text-sm font-thin underline text-text-main hover:opacity-75 transition-opacity">
                Edit
            </a>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 3. Phone Number -->
    <div class="flex items-center justify-between gap-4">
        <div class="space-y-1">
            <h3 class="text-sm sm:text-base font-bold text-text-main">
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
            <p class="text-sm text-text-muted font-normal tracking-wide">
                {{ $maskedContact }}
            </p>
        </div>
        <div class="shrink-0 text-right flex items-center gap-2">
            <a href="#" class="text-sm font-thin underline text-text-main hover:opacity-75 transition-opacity">
                Verify
            </a>
            <span class="text-text-main text-xs font-light">|</span>
            <a href="{{ route('user.profile') }}" class="text-sm font-thin underline text-text-main hover:opacity-75 transition-opacity">
                Edit
            </a>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 4. Change Password -->
    <div class="flex items-center justify-between gap-4">
        <div class="space-y-1">
            <h3 class="text-sm sm:text-base font-bold text-text-main">
                Change Password
            </h3>
            <p class="text-sm text-text-muted font-normal tracking-widest">
                ••••••••••
            </p>
        </div>
        <div class="shrink-0 text-right">
            <a href="{{ route('user.change-password') }}" class="text-sm font-thin underline text-text-main hover:opacity-75 transition-opacity">
                Edit
            </a>
        </div>
    </div>

    <hr class="border-border-subtle" />

    <!-- 5. Delete Account (With Chevron Arrow) -->
    <div class="flex items-center justify-between group cursor-pointer hover:opacity-85 transition-opacity">
        <div class="space-y-1">
            <h3 class="text-base font-bold text-text-main">Delete Account</h3>
            <p class="text-xs text-text-muted font-normal">
                NOTE: Account will NOT BE RECOVERABLE once deleted.
            </p>
        </div>
        <div class="text-text-muted shrink-0 pl-4">
            <svg class="w-5 h-5 text-gray-400 group-hover:text-text-main transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </div>

</div>
@endsection