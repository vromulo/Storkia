<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Storkia - Logistics Hub' }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('assets/pink-storkia-minimized.webp') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="m-0 p-0 h-screen w-screen font-sans antialiased text-text-main overflow-hidden bg-gradient-to-b from-surface via-surface to-brand-light/30 relative">
    <x-ui.preloader />
    
    <!-- Mobile Restricted View -->
    <div class="mobile-view absolute inset-0 bg-surface/80 backdrop-blur-md z-40"></div>
    <div class="mobile-view flex-col items-center justify-center h-screen w-screen px-4 sm:px-6 py-8 z-50 relative overflow-y-auto">
        <div class="bg-surface/95 backdrop-blur-xl rounded-3xl shadow-2xl shadow-black/10 border border-border-subtle w-full max-w-md p-6 sm:p-8 text-center flex flex-col items-center">
            <div class="w-20 h-20 bg-brand-light/40 text-primary-dark rounded-full flex items-center justify-center mb-5 shadow-inner">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-primary-dark mb-4 tracking-tight">Desktop Only</h2>
            <div class="bg-brand-light/20 border border-border-subtle rounded-2xl p-5 mb-8 w-full shadow-sm">
                <p class="text-text-main text-sm sm:text-base leading-relaxed font-medium">
                    You are accessing a <span class="font-bold text-primary-dark">Logistics Account</span>.
                    <br><br>
                    <span class="text-text-muted">Please log in from a computer to access this dashboard interface.</span>
                </p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center px-6 py-4 bg-red-500 text-white text-lg font-bold rounded-2xl shadow-md hover:bg-red-600 transition-all duration-200 cursor-pointer active:scale-95">
                    <svg class="w-6 h-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </div>

    <!-- Alpine Dashboard Shell -->
    <div x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false' }"
         x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))"
         class="desktop-view h-screen w-full relative">
        
        <x-logistics.sidebar />

        <!-- Portal View Wrapper with Top Navigation Bar -->
        <div class="flex-1 flex flex-col h-screen overflow-hidden">
            <!-- Global Top Header Bar -->
            <header x-data="{
                        headerHubName: @js(auth()->user()->logisticsProfile?->business_name ?? 'Logistics Hub'),
                        headerOwnerName: @js(trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? ''))),
                        headerPhotoUrl: @js(auth()->user()->logisticsProfile?->logo_path ? asset('storage/' . auth()->user()->logisticsProfile->logo_path) : '')
                    }"
                    @seller-store-name-updated.window="headerHubName = $event.detail.business_name"
                    @seller-owner-name-updated.window="headerOwnerName = $event.detail.full_name"
                    @seller-photo-updated.window="headerPhotoUrl = $event.detail.photo_url"
                    class="h-20 bg-surface/90 backdrop-blur-md border-b border-border-subtle px-8 flex items-center justify-between shrink-0 z-30">
                <div>
                    <h2 class="text-sm font-bold text-text-muted" x-text="headerHubName"></h2>
                </div>

                <div class="flex items-center gap-4">
                    @php
                        $unreadNotifications = auth()->user()->unreadNotifications;
                        $unreadCount = $unreadNotifications->count();
                        $recentNotifications = auth()->user()->notifications()->take(6)->get();
                    @endphp

                    <!-- Notification Center Dropdown -->
                    <div x-data="{ notifOpen: false }" @click.away="notifOpen = false" class="relative">
                        <button type="button" 
                                @click="notifOpen = !notifOpen" 
                                class="p-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-muted hover:text-primary transition-colors relative cursor-pointer focus:outline-none">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            @if($unreadCount > 0)
                                <span class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full bg-red-500 text-white text-[10px] font-extrabold flex items-center justify-center border-2 border-white">
                                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                </span>
                            @endif
                        </button>

                        <!-- Notification Dropdown Panel -->
                        <div x-show="notifOpen" 
                             x-cloak 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                             class="absolute right-0 mt-3 w-80 sm:w-96 bg-surface border border-border-subtle rounded-2xl shadow-2xl z-50 overflow-hidden">
                            
                            <div class="p-4 border-b border-border-subtle flex items-center justify-between bg-surface-subtle">
                                <span class="font-bold text-sm text-primary-dark">Notifications</span>
                                @if($unreadCount > 0 && auth()->user()->logisticsProfile)
                                    <form method="POST" action="{{ route('logistics.notifications.markAllRead') }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs font-bold text-primary hover:underline cursor-pointer">
                                            Mark all as read
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div class="max-h-80 overflow-y-auto divide-y divide-border-subtle/60 custom-scrollbar">
                                @forelse($recentNotifications as $notif)
                                    @php
                                        $data = $notif->data;
                                        $isUnread = is_null($notif->read_at);
                                    @endphp
                                    <div class="p-4 transition-colors {{ $isUnread ? 'bg-brand-light/10' : 'bg-surface' }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <h4 class="text-xs font-bold {{ ($data['type'] ?? '') === 'danger' ? 'text-danger' : (($data['type'] ?? '') === 'warning' ? 'text-yellow-600' : 'text-primary') }}">
                                                {{ $data['title'] ?? 'Notice' }}
                                            </h4>
                                            <span class="text-[10px] text-text-muted shrink-0">{{ $notif->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-xs text-text-main mt-1 leading-relaxed">{{ $data['message'] ?? '' }}</p>
                                        @if($isUnread && auth()->user()->logisticsProfile)
                                            <div class="mt-2 text-right">
                                                <form method="POST" action="{{ route('logistics.notifications.markRead', $notif->id) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="text-[10px] font-bold text-text-muted hover:text-primary cursor-pointer">
                                                        Dismiss
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="p-8 text-center text-text-muted text-xs">
                                        No notifications at this time.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Logistics Hub Profile Badge -->
                    <a href="{{ auth()->user()->logisticsProfile ? route('logistics.hub-settings') : route('logistics.logistics-dashboard') }}"
                       class="flex items-center gap-3 pl-2 border-l border-border-subtle group cursor-pointer">
                        <div class="w-9 h-9 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center border border-primary/20 overflow-hidden shrink-0">
                            <template x-if="headerPhotoUrl">
                                <img :src="headerPhotoUrl" alt="Hub Logo" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!headerPhotoUrl">
                                <span x-text="(headerHubName || 'L').charAt(0).toUpperCase()"></span>
                            </template>
                        </div>
                        <div class="text-left hidden sm:block">
                            <span class="text-xs font-bold text-text-main group-hover:text-primary transition-colors block leading-tight" x-text="headerOwnerName"></span>
                            <span class="text-[10px] text-text-muted block">Sorting Hub</span>
                        </div>
                    </a>
                </div>
            </header>

            <!-- Scrollable Content Socket -->
            <main class="flex-1 overflow-y-auto custom-scrollbar relative"
                  x-data="{ showContent: false }"
                  x-init="setTimeout(() => showContent = true, 50)">
                <div class="p-8 lg:p-12"
                     x-show="showContent"
                     x-transition:enter="transition ease-out duration-500"
                     x-transition:enter-start="opacity-0 translate-y-4"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-cloak>
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <x-ui.toast-notification />

    @livewireScripts
</body>
</html>