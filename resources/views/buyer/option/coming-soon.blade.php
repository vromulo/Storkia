@extends('buyer.personal-center', [
    'pageTitle' => 'My Account'
])

@section('option-content')
<div class="text-center flex flex-col items-center justify-center py-10 min-h-[350px]">
    <div class="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-4">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
    </div>
    <h3 class="text-lg font-bold text-text-main mb-1">{{ $title ?? 'Coming Soon' }}</h3>
    <p class="text-xs text-text-muted max-w-sm leading-relaxed mb-6">
        This feature is currently under preparation. You can manage your security credentials under Account Management.
    </p>
    <a href="{{ route('user.account-management') }}" class="px-5 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-primary-dark transition-colors shadow-xs">
        Return to Account Management
    </a>
</div>
@endsection