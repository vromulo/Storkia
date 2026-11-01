@extends('layouts.app', ['title' => 'Privacy Policy'])

@section('content')
@php
    $requestedSection = request()->query('section', request()->query('role', 'all'));
    $initialTab = in_array($requestedSection, ['all', 'buyer', 'seller', 'logistics', 'security'], true)
        ? $requestedSection
        : 'all';
@endphp

<div class="bg-surface-subtle/50 min-h-screen py-8 sm:py-12 font-sans antialiased text-text-main">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <!-- Breadcrumbs -->
        <x-ui.breadcrumbs :items="['Privacy Policy']" />

        <!-- Hero Header Banner -->
        <div class="relative rounded-3xl bg-gradient-to-r from-primary-dark via-primary to-secondary text-white p-6 sm:p-10 shadow-md overflow-hidden">
            <div class="relative z-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <span class="inline-block px-3 py-1 rounded-full bg-white/20 text-[10px] font-extrabold uppercase tracking-widest">
                        Data Protection &amp; Trust Notice
                    </span>
                    <h1 class="text-2xl sm:text-4xl font-serif font-bold tracking-tight">
                        Storkia Privacy Policy
                    </h1>
                    <p class="text-xs sm:text-sm text-white/90 leading-relaxed">
                        How Storkia collects, protects, masks, and processes personal data, government IDs, payment proofs, and logistics chain-of-custody records in compliance with the Philippine Data Privacy Act of 2012 (RA 10173).
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <a href="{{ route('terms') }}"
                       class="px-4 py-2 rounded-xl bg-black/25 hover:bg-black/40 text-white text-xs font-bold border border-white/20 transition-colors">
                        View Terms &amp; Conditions
                    </a>
                    <button type="button"
                            onclick="window.print()"
                            class="px-4 py-2 rounded-xl bg-white text-primary-dark hover:bg-brand-light text-xs font-bold shadow-xs transition-colors cursor-pointer">
                        Print Policy
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabbed & Sectioned Privacy Component -->
        <x-ui.privacy-content :default-tab="$initialTab" />

    </div>
</div>
@endsection