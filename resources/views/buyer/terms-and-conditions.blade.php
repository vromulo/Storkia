@extends('layouts.app', ['title' => 'Terms and Conditions'])

@section('content')
@php
    $requestedTab = request()->query('role', 'all');
    $initialTab = in_array($requestedTab, ['all', 'buyer', 'seller', 'logistics'], true) ? $requestedTab : 'all';
@endphp

<div class="bg-surface-subtle/50 min-h-screen py-8 sm:py-12 font-sans antialiased text-text-main">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <!-- Breadcrumbs -->
        <x-ui.breadcrumbs :items="['Terms and Conditions']" />

        <!-- Hero Header Banner -->
        <div class="relative rounded-3xl bg-gradient-to-r from-primary-dark via-primary to-secondary text-white p-6 sm:p-10 shadow-md overflow-hidden">
            <div class="relative z-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <span class="inline-block px-3 py-1 rounded-full bg-white/20 text-[10px] font-extrabold uppercase tracking-widest">
                        Official Platform Agreement
                    </span>
                    <h1 class="text-2xl sm:text-4xl font-serif font-bold tracking-tight">
                        Storkia Terms &amp; Conditions
                    </h1>
                    <p class="text-xs sm:text-sm text-white/90 leading-relaxed">
                        Unified legal terms governing Buyers, Seller Merchants, Logistics Sorting Hubs, First-Mile Couriers, and Last-Mile Delivery Riders on the Storkia platform.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <span class="px-3.5 py-2 rounded-xl bg-black/25 text-white text-xs font-bold border border-white/20">
                        Effective: {{ now()->format('F Y') }}
                    </span>
                    <button type="button"
                            onclick="window.print()"
                            class="px-4 py-2 rounded-xl bg-white text-primary-dark hover:bg-brand-light text-xs font-bold shadow-xs transition-colors cursor-pointer">
                        Print Terms
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabbed & Sectioned Terms Component -->
        <x-ui.terms-content :default-tab="$initialTab" />

    </div>
</div>
@endsection