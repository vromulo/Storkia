@extends('layouts.logistics', ['title' => 'Storkia - Logistics Dashboard'])

@section('content')
    <div class="max-w-7xl mx-auto">
        @if($latestApp && $latestApp->status === 'pending')
            <div class="mb-8 p-6 bg-yellow-50 border border-yellow-200 rounded-3xl shadow-xs">
                <h3 class="text-base font-bold text-yellow-900">Hub Application Under Review</h3>
                <p class="text-xs sm:text-sm text-yellow-800 mt-1">
                    Your application for <strong>{{ $latestApp->business_name }}</strong> (revision v{{ $latestApp->version }}) is pending administrative approval. Dispatch and operational routing will unlock once verified.
                </p>
            </div>
        @elseif($latestApp && $latestApp->status === 'rejected')
            <div class="mb-8 p-6 bg-red-50 border border-red-200 rounded-3xl shadow-xs flex justify-between items-center">
                <div>
                    <h3 class="text-base font-bold text-red-900">Application Rejected</h3>
                    <p class="text-xs sm:text-sm text-red-800 mt-1">Reason: <em>"{{ $latestApp->rejection_reason }}"</em></p>
                </div>
                <a href="{{ route('logistics.reapply') }}" class="px-5 py-2.5 bg-primary text-white text-xs font-bold rounded-xl shadow-md">
                    Update & Re-apply
                </a>
            </div>
        @endif

        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-primary-dark">Logistics Operations</h1>
                <p class="text-primary mt-1 font-medium">Driver/Handler: {{ auth()->user()->first_name ?? 'User' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="p-8 bg-surface/80 backdrop-blur-md rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all duration-300 group cursor-pointer">
                <div class="flex items-center mb-4">
                    <div class="p-3 bg-brand-light/40 rounded-xl text-primary mr-4 group-hover:scale-110 group-hover:bg-brand-light/60 transition-transform duration-300">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-text-main">Parcels to Pick Up</h2>
                </div>
                <p class="text-text-muted">No pending pickups at this time.</p>
            </div>

            <div class="p-8 bg-surface/80 backdrop-blur-md rounded-2xl shadow-sm border border-border-subtle hover:border-primary/50 hover:shadow-md transition-all duration-300 group cursor-pointer">
                <div class="flex items-center mb-4">
                    <div class="p-3 bg-brand-light/40 rounded-xl text-primary mr-4 group-hover:scale-110 group-hover:bg-brand-light/60 transition-transform duration-300">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-text-main">In Transit</h2>
                </div>
                <p class="text-text-muted">No active deliveries.</p>
            </div>
        </div>
    </div>
@endsection