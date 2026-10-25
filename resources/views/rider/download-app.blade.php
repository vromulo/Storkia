@extends('layouts.auth-split', [
    'title' => 'Storkia - Download Rider App',
    'mobilePrompt' => 'You are trying to access a rider account. Please download the mobile app.'
])

@section('content')
<div class="w-full flex flex-col justify-center items-center min-h-[60vh]">
    <div class="mb-10 text-center">
        <h1 class="text-3xl sm:text-4xl font-serif font-bold tracking-tight text-text-main uppercase">
            Get the Rider App
        </h1>
        <p class="mt-3 text-sm text-text-muted font-bold tracking-wide leading-relaxed max-w-sm mx-auto">
            RIDER ACCOUNTS ARE EXCLUSIVELY ACCESSIBLE VIA OUR MOBILE APPLICATION.
        </p>
    </div>

    <div class="relative flex justify-center mb-12 group cursor-default">
        <div class="relative w-40 h-40 bg-surface border-4 border-text-main flex items-center justify-center transition-transform hover:-translate-y-1 hover:translate-x-1 z-10">
            <svg class="w-16 h-16 text-text-main" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M12 18l-6-6h4V4h4v8h4l-6 6z" />
                <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M4 20h16v2H4z" />
            </svg>
        </div>
        <div class="absolute inset-0 bg-primary-dark translate-x-3 translate-y-3 z-0"></div>
    </div>

    <div class="w-full max-w-xs space-y-4">
        <button 
            type="button" 
            disabled
            class="w-full py-4 px-6 bg-surface text-text-muted text-sm font-bold uppercase tracking-wider border-2 border-border-subtle border-dashed cursor-not-allowed flex items-center justify-center gap-3 transition-colors"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            APK Coming Soon
        </button>
        
        <form action="{{ route('rider.logout') }}" method="POST" class="text-center pt-6">
            @csrf
            <button type="submit" class="text-xs font-bold text-text-main hover:text-primary-dark uppercase tracking-widest underline underline-offset-4 transition-colors cursor-pointer">
                Return to Login
            </button>
        </form>
    </div>
</div>
@endsection