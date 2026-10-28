@extends('layouts.logistics', ['title' => 'Storkia - Add Courier'])

@section('content')
<div class="max-w-xl mx-auto select-none space-y-6">
    <div class="flex items-center justify-between gap-4">
        <a href="{{ route('logistics.couriers.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-xs font-bold text-text-main transition-all cursor-pointer">
            <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to All Couriers
        </a>

        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
            {{ $profile->business_name }}
        </span>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs p-8 sm:p-12">
        <div class="w-full max-w-md mx-auto">
            <livewire:logistics.create-courier-wizard />
        </div>
    </div>
</div>
@endsection