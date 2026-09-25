@extends('layouts.seller', ['title' => 'Storkia - Seller Dashboard'])

@section('content')
    <div class="max-w-7xl mx-auto">
        @if($latestApp && $latestApp->status === 'pending')
            <div class="mb-8 p-6 bg-yellow-50 border border-yellow-200 rounded-3xl shadow-xs">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-yellow-100 text-yellow-700 rounded-2xl shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-yellow-900">Application Under Review</h3>
                        <p class="text-xs sm:text-sm text-yellow-800 mt-1 leading-relaxed">
                            Your application for <strong>{{ $latestApp->business_name }}</strong> (revision v{{ $latestApp->version }}) is currently being reviewed by the Storkia administration. Seller catalog privileges will be unlocked once approved.
                        </p>
                    </div>
                </div>
            </div>
        @elseif($latestApp && $latestApp->status === 'rejected')
            <div class="mb-8 p-6 bg-red-50 border border-red-200 rounded-3xl shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-red-100 text-danger rounded-2xl shrink-0">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-red-900">Application Not Approved</h3>
                            <p class="text-xs sm:text-sm text-red-800 mt-1">Reason: <strong class="italic">"{{ $latestApp->rejection_reason }}"</strong></p>
                            <p class="text-xs text-text-muted mt-1">You may correct the issues and submit a revised application.</p>
                        </div>
                    </div>
                    <a href="{{ route('seller.reapply') }}" class="px-5 py-3 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-md transition-colors shrink-0 text-center">
                        Update & Re-apply
                    </a>
                </div>
            </div>
        @endif

        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-primary-dark">Seller Portal</h1>
            <p class="text-text-muted">Welcome back, {{ auth()->user()->first_name ?? 'Seller' }}</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="p-6 bg-white rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all duration-300">
                <h3 class="text-text-muted font-bold mb-2">Total Sales</h3>
                <p class="text-2xl text-primary font-serif">₱0.00</p>
            </div>
            <div class="p-6 bg-white rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all duration-300">
                <h3 class="text-text-muted font-bold mb-2">Active Products</h3>
                <p class="text-2xl text-primary font-serif">0</p>
            </div>
            <div class="p-6 bg-white rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all duration-300">
                <h3 class="text-text-muted font-bold mb-2">Pending Orders</h3>
                <p class="text-2xl text-primary font-serif">0</p>
            </div>
        </div>
    </div>
@endsection