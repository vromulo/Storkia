@extends('buyer.personal-center', [
    'pageTitle' => 'My Account'
])

@section('option-content')
<div class="space-y-6">
    <!-- Section Header -->
    <div class="border-b border-border-subtle pb-4">
        <h2 class="text-lg font-bold text-text-main">Account Management</h2>
        <p class="text-xs text-text-muted mt-1">Review your login credentials, role permissions, and active status.</p>
    </div>

    <!-- Account Overview Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 bg-surface-subtle rounded-2xl border border-border-subtle space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Account ID</span>
            <p class="text-sm font-semibold text-text-main">#{{ str_pad($user->id, 6, '0', STR_PAD_LEFT) }}</p>
        </div>

        <div class="p-4 bg-surface-subtle rounded-2xl border border-border-subtle space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Primary Role</span>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20">
                    {{ $user->role ?? 'Buyer' }}
                </span>
            </div>
        </div>

        <div class="p-4 bg-surface-subtle rounded-2xl border border-border-subtle space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Account Status</span>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                <span class="text-sm font-semibold text-green-700">Active & Verified</span>
            </div>
        </div>

        <div class="p-4 bg-surface-subtle rounded-2xl border border-border-subtle space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-text-muted">Member Since</span>
            <p class="text-sm font-semibold text-text-main">{{ $user->created_at ? $user->created_at->format('M d, Y') : 'Recent' }}</p>
        </div>
    </div>

    <!-- Account Security & Credentials Section -->
    <div class="border-t border-border-subtle pt-6 space-y-4">
        <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Account Credentials</h3>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-surface-subtle rounded-2xl border border-border-subtle gap-3">
            <div>
                <p class="text-xs font-bold text-text-main">Email Address</p>
                <p class="text-sm text-text-muted">{{ $user->email }}</p>
            </div>
            <span class="text-xs text-green-700 font-semibold flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Verified
            </span>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-surface-subtle rounded-2xl border border-border-subtle gap-3">
            <div>
                <p class="text-xs font-bold text-text-main">Mobile Number</p>
                <p class="text-sm text-text-muted">{{ $user->contact_no ?? 'No contact number added' }}</p>
            </div>
            <a href="{{ route('user.profile') }}" class="text-xs font-bold text-primary hover:text-primary-dark transition-colors">
                Update
            </a>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-surface-subtle rounded-2xl border border-border-subtle gap-3">
            <div>
                <p class="text-xs font-bold text-text-main">Password</p>
                <p class="text-sm text-text-muted">••••••••••••</p>
            </div>
            <a href="{{ route('user.change-password') }}" class="text-xs font-bold text-primary hover:text-primary-dark transition-colors">
                Change Password
            </a>
        </div>
    </div>

    <!-- Danger Zone / Account Deactivation -->
    <div class="border-t border-border-subtle pt-6">
        <div class="p-5 bg-red-50/60 rounded-2xl border border-red-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h4 class="text-sm font-bold text-red-900">Deactivate or Delete Account</h4>
                <p class="text-xs text-red-800 mt-0.5">Permanently remove your account, active vouchers, and personal purchase history.</p>
            </div>
            <button type="button" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-xs transition-colors shrink-0 cursor-pointer">
                Request Deactivation
            </button>
        </div>
    </div>
</div>
@endsection