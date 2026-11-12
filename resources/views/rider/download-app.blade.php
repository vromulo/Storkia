@extends('layouts.app', [
    'title' => 'Download Storkia Mobile Apps (APK)',
])

@section('content')
@php
    $apps = $apps ?? \App\Http\Controllers\Rider\AuthController::getApkCatalog();
@endphp

<div x-data="{
        activeFilter: 'all',
        qrModalOpen: false,
        qrApp: null,
        openQrModal(appData) {
            this.qrApp = appData;
            this.qrModalOpen = true;
        }
     }"
     class="min-h-screen bg-surface text-text-main select-none relative overflow-hidden">

    <div class="pointer-events-none absolute -top-24 -left-24 w-96 h-96 rounded-full bg-brand-light/45 blur-3xl"></div>
    <div class="pointer-events-none absolute top-1/3 -right-24 w-96 h-96 rounded-full bg-secondary/25 blur-3xl"></div>

    <section class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-20 space-y-12">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('landing') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white border border-border-subtle hover:border-primary text-xs sm:text-sm font-bold text-text-main shadow-2xs transition-all">
                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Landing Page
            </a>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('home') }}"
                   class="px-4 py-2.5 rounded-2xl bg-brand-light/50 hover:bg-brand-light text-primary-dark text-xs sm:text-sm font-bold border border-border-subtle transition-colors">
                    Open Web Storefront
                </a>
                @if(Auth::guard('rider')->check())
                    <form action="{{ route('rider.logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit"
                                class="px-4 py-2.5 rounded-2xl bg-primary-dark text-white text-xs sm:text-sm font-bold cursor-pointer">
                            Sign Out Rider Session
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="text-center max-w-3xl mx-auto space-y-4">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-extrabold uppercase tracking-widest">
                <span class="w-2 h-2 rounded-full bg-primary animate-ping"></span>
                Official Android APK Distribution Suite
            </span>
            <h1 class="text-3xl sm:text-5xl font-serif font-bold text-primary-dark tracking-tight leading-tight">
                Download the Storkia Mobile Ecosystem
            </h1>
            <p class="text-sm sm:text-base text-text-muted leading-relaxed">
                Choose the dedicated Android application for your role—whether you are shopping for exclusive local deals on <strong>Storkia</strong>, delivering last-mile parcels with <strong>Storkia Rider</strong>, or hauling first-mile manifests with <strong>Storkia Express</strong>.
            </p>

            <div class="pt-2 inline-flex flex-wrap items-center justify-center gap-1.5 p-1.5 rounded-2xl bg-white border border-border-subtle shadow-xs text-xs">
                <button type="button"
                        @click="activeFilter = 'all'"
                        :class="activeFilter === 'all' ? 'bg-primary-dark text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="px-4 py-2 rounded-xl transition-all cursor-pointer">
                    All 3 Mobile Apps
                </button>
                <button type="button"
                        @click="activeFilter = 'buyer'"
                        :class="activeFilter === 'buyer' ? 'bg-primary text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="px-4 py-2 rounded-xl transition-all cursor-pointer">
                    Storkia (Buyer)
                </button>
                <button type="button"
                        @click="activeFilter = 'rider'"
                        :class="activeFilter === 'rider' ? 'bg-primary-dark text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="px-4 py-2 rounded-xl transition-all cursor-pointer">
                    Storkia Rider
                </button>
                <button type="button"
                        @click="activeFilter = 'express'"
                        :class="activeFilter === 'express' ? 'bg-teal-700 text-white font-bold shadow-2xs' : 'text-text-muted hover:text-text-main font-semibold'"
                        class="px-4 py-2 rounded-xl transition-all cursor-pointer">
                    Storkia Express (Courier)
                </button>
            </div>
        </div>

        @if(session('apk_notice'))
            <div class="max-w-3xl mx-auto p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 text-xs sm:text-sm font-semibold flex items-start gap-3 shadow-xs">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <div class="font-extrabold uppercase tracking-wider text-[11px] text-amber-950">APK File Placement Notice</div>
                    <p class="mt-0.5">{{ session('apk_notice') }}</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-7 items-stretch">
            @foreach($apps as $key => $app)
                @php
                    $isPrimaryCard = $key === 'buyer';
                    $badgeTheme = match($key) {
                        'buyer'   => 'bg-primary/10 text-primary border-primary/25',
                        'rider'   => 'bg-brand-light/70 text-primary-dark border-primary-dark/20',
                        'express' => 'bg-teal-50 text-teal-800 border-teal-200',
                        default   => 'bg-surface-subtle text-text-main border-border-subtle',
                    };
                    $btnTheme = match($key) {
                        'buyer'   => 'bg-primary hover:bg-primary-dark text-white shadow-primary/25',
                        'rider'   => 'bg-primary-dark hover:bg-black text-white shadow-primary-dark/25',
                        'express' => 'bg-teal-700 hover:bg-teal-800 text-white shadow-teal-700/25',
                        default   => 'bg-primary text-white',
                    };
                    $ringTheme = match($key) {
                        'buyer'   => 'hover:border-primary/60',
                        'rider'   => 'hover:border-primary-dark/60',
                        'express' => 'hover:border-teal-600/60',
                        default   => 'hover:border-primary/50',
                    };
                @endphp

                <div x-show="activeFilter === 'all' || activeFilter === '{{ $key }}'"
                     x-transition
                     class="group relative bg-white rounded-[2rem] border-2 border-border-subtle {{ $ringTheme }} p-7 sm:p-8 shadow-sm hover:shadow-2xl transition-all duration-300 flex flex-col justify-between overflow-hidden">

                    <div class="space-y-6">
                        <div class="flex items-start justify-between gap-3">
                            <span class="px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider border {{ $badgeTheme }}">
                                {{ $app['badge'] }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold {{ $app['is_available'] ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-amber-100 text-amber-800 border border-amber-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $app['is_available'] ? 'bg-green-600' : 'bg-amber-500' }}"></span>
                                {{ $app['is_available'] ? 'APK Ready' : 'Awaiting APK File' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-5">
                            <div class="w-24 h-24 rounded-3xl bg-surface-subtle border-2 border-border-subtle p-2.5 flex items-center justify-center shadow-inner shrink-0 group-hover:scale-105 transition-transform duration-300 overflow-hidden">
                                <img src="{{ $app['logo_url'] }}"
                                     alt="{{ $app['name'] }} Logo"
                                     class="w-full h-full object-contain rounded-2xl">
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-2xl font-serif font-extrabold text-primary-dark truncate">
                                    {{ $app['name'] }}
                                </h2>
                                <p class="text-xs font-bold text-primary mt-0.5">{{ $app['tagline'] }}</p>
                                <div class="flex flex-wrap items-center gap-2 mt-2 text-[11px] font-mono text-text-muted">
                                    <span class="px-2 py-0.5 rounded-md bg-surface-subtle border border-border-subtle font-bold text-text-main">{{ $app['version'] }}</span>
                                    <span>&bull;</span>
                                    <span class="font-bold text-primary-dark">{{ $app['file_size_label'] }}</span>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs sm:text-sm text-text-muted leading-relaxed">
                            {{ $app['description'] }}
                        </p>

                        <div class="space-y-2.5 pt-2 border-t border-border-subtle">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-text-muted block">
                                Key Application Features
                            </span>
                            <ul class="space-y-2 text-xs text-text-main">
                                @foreach($app['features'] as $feat)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 text-primary shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span class="font-medium">{{ $feat }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="pt-6 mt-6 border-t border-border-subtle space-y-3">
                        <div class="flex items-center justify-between text-[11px] text-text-muted font-mono">
                            <span>{{ $app['min_android'] }}</span>
                            <span class="truncate max-w-[180px]" title="{{ $app['updated_at_label'] }}">{{ $app['updated_at_label'] }}</span>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <a href="{{ $app['download_url'] }}"
                               class="flex-1 py-3.5 px-5 rounded-2xl font-extrabold text-xs sm:text-sm flex items-center justify-center gap-2.5 shadow-lg transition-all active:scale-95 {{ $btnTheme }}">
                                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Download {{ $app['name'] }} APK</span>
                            </a>

                            <button type="button"
                                    @click="openQrModal(@js($app))"
                                    title="Scan QR Code on Phone"
                                    class="p-3.5 rounded-2xl bg-surface-subtle hover:bg-brand-light/50 border border-border-subtle text-primary-dark transition-colors cursor-pointer shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                            </button>
                        </div>

                        <div class="text-[10px] text-center font-mono text-text-muted">
                            File: <strong>{{ $app['download_name'] }}</strong>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-[2rem] border border-border-subtle p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border-subtle pb-4">
                <div>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-primary">Quick Setup Guide</span>
                    <h3 class="text-lg font-bold text-primary-dark">How to Install Storkia APK Files on Your Android Phone</h3>
                </div>
                <span class="px-3 py-1 rounded-full bg-surface-subtle border border-border-subtle text-xs font-semibold text-text-muted">
                    Works on Android 8.0+
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 text-xs">
                <div class="p-5 rounded-2xl bg-surface-subtle/60 border border-border-subtle space-y-2">
                    <div class="w-8 h-8 rounded-xl bg-primary text-white font-extrabold flex items-center justify-center text-sm">1</div>
                    <h4 class="font-bold text-sm text-primary-dark">Download Your Role APK</h4>
                    <p class="text-text-muted leading-relaxed">
                        Tap the <strong>Download APK</strong> button for <strong>Storkia</strong>, <strong>Storkia Rider</strong>, or <strong>Storkia Express</strong> above, or scan the QR code from your phone camera.
                    </p>
                </div>

                <div class="p-5 rounded-2xl bg-surface-subtle/60 border border-border-subtle space-y-2">
                    <div class="w-8 h-8 rounded-xl bg-primary-dark text-white font-extrabold flex items-center justify-center text-sm">2</div>
                    <h4 class="font-bold text-sm text-primary-dark">Allow "Install Unknown Apps"</h4>
                    <p class="text-text-muted leading-relaxed">
                        When prompted by your browser (Chrome / Files), tap <strong>Settings</strong> and toggle on <strong>Allow from this source</strong> for direct APK installation.
                    </p>
                </div>

                <div class="p-5 rounded-2xl bg-surface-subtle/60 border border-border-subtle space-y-2">
                    <div class="w-8 h-8 rounded-xl bg-teal-700 text-white font-extrabold flex items-center justify-center text-sm">3</div>
                    <h4 class="font-bold text-sm text-primary-dark">Sign In & Start</h4>
                    <p class="text-text-muted leading-relaxed">
                        Open the installed app. Buyers can sign in with their Storkia account, while Riders and Couriers sign in using their <strong>@storkia.shop</strong> fleet credentials.
                    </p>
                </div>
            </div>
        </div>

    </section>

    <template x-teleport="body">
        <div x-show="qrModalOpen && qrApp"
             x-cloak
             class="fixed inset-0 z-[200] flex items-center justify-center p-4">
            <div @click="qrModalOpen = false" class="absolute inset-0 bg-black/65 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-white rounded-3xl max-w-sm w-full p-6 sm:p-7 shadow-2xl border border-border-subtle text-center space-y-4">
                <div class="flex items-center justify-between border-b border-border-subtle pb-3">
                    <div class="flex items-center gap-2.5 text-left">
                        <img :src="qrApp?.logo_url" class="w-9 h-9 rounded-xl object-contain border border-border-subtle p-1 bg-surface-subtle">
                        <div>
                            <h3 class="text-sm font-extrabold text-primary-dark" x-text="qrApp?.name"></h3>
                            <p class="text-[11px] text-text-muted" x-text="qrApp?.tagline"></p>
                        </div>
                    </div>
                    <button type="button" @click="qrModalOpen = false" class="p-1.5 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <div class="p-4 rounded-2xl bg-surface-subtle border border-border-subtle inline-block mx-auto">
                    <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=${encodeURIComponent(qrApp?.download_url || '')}`"
                         alt="Download QR Code"
                         class="w-48 h-48 mx-auto rounded-xl bg-white p-2 border border-border-subtle">
                </div>

                <p class="text-xs text-text-muted leading-relaxed">
                    Scan this QR code with your Android phone camera to download <strong class="text-text-main" x-text="qrApp?.download_name"></strong> directly.
                </p>

                <div class="pt-2">
                    <a :href="qrApp?.download_url"
                       class="w-full py-3 px-4 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold inline-flex items-center justify-center gap-2">
                        Direct Download Link
                    </a>
                </div>
            </div>
        </div>
    </template>

</div>
@endsection