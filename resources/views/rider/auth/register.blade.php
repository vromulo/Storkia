@extends('layouts.logistics', ['title' => 'Storkia - Create Rider Account'])

@section('content')
<div class="max-w-xl mx-auto select-none space-y-6">
    <div class="flex items-center justify-between gap-4">
        <a href="{{ route('logistics.employees.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-xs font-bold text-text-main transition-all cursor-pointer">
            <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Employee Management
        </a>

        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-brand-light/60 text-primary-dark border border-border-subtle">
            {{ auth()->user()?->logisticsProfile?->business_name ?? 'Logistics Hub' }}
        </span>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs p-8 sm:p-10">
        <livewire:auth.rider-register-wizard />
    </div>
</div>
@endsection