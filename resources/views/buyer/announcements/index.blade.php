@extends('layouts.app', ['title' => 'Announcements & Policy Updates - Storkia'])

@section('content')
@php
    $announcements = $announcements ?? collect();
    $readIds = $readIds ?? [];
    $unreadCount = $unreadCount ?? 0;
    $categoryFilter = $categoryFilter ?? 'all';
@endphp

<div class="bg-surface-subtle/40 min-h-screen py-6 sm:py-10 font-sans antialiased text-text-main">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <x-ui.breadcrumbs :items="['Announcements & Policy Updates']" />

        <!-- Hero Header Banner -->
        <div class="rounded-3xl bg-gradient-to-r from-primary-dark via-primary to-secondary text-white p-6 sm:p-8 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div class="space-y-2 max-w-2xl">
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 rounded-full bg-white/20 text-[10px] font-extrabold uppercase tracking-widest">
                            Official Storkia Bulletin
                        </span>
                        @auth
                            @if($unreadCount > 0)
                                <span class="px-2.5 py-0.5 rounded-full bg-red-500 text-white text-[10px] font-extrabold uppercase">
                                    {{ $unreadCount }} Unread
                                </span>
                            @endif
                        @endauth
                    </div>
                    <h1 class="text-2xl sm:text-4xl font-serif font-bold tracking-tight">
                        Platform Announcements &amp; Policy Updates
                    </h1>
                    <p class="text-xs sm:text-sm text-white/90 leading-relaxed">
                        Stay informed on marketplace policy updates, centralized GCash Escrow advisories, scheduled maintenance, and platform-wide events.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    @auth
                        @if($unreadCount > 0)
                            <form action="{{ route('announcements.mark-all-read') }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="px-4 py-2.5 rounded-xl bg-white text-primary-dark hover:bg-brand-light text-xs font-extrabold shadow-xs transition-colors cursor-pointer">
                                    ✓ Mark All as Read
                                </button>
                            </form>
                        @endif
                    @endauth
                    <a href="{{ route('terms') }}" class="px-4 py-2.5 rounded-xl bg-black/25 hover:bg-black/40 text-white text-xs font-bold border border-white/20 transition-colors">
                        Terms &amp; Conditions
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
                {{ session('success') }}
            </div>
        @endif

        <!-- Category Filter Chips -->
        <div class="flex flex-wrap items-center gap-2">
            @foreach([
                'all'           => 'All Bulletins',
                'announcement'  => 'General Announcements',
                'policy_update' => 'Policy Updates',
                'promotion'     => 'Promos & Events',
                'maintenance'   => 'System Maintenance',
            ] as $cKey => $cLabel)
                <a href="{{ route('announcements.index', $cKey === 'all' ? [] : ['category' => $cKey]) }}"
                   class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $categoryFilter === $cKey ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main' }}">
                    {{ $cLabel }}
                </a>
            @endforeach
        </div>

        <!-- Announcements List -->
        @if($announcements->isEmpty())
            <div class="bg-surface rounded-3xl border border-border-subtle p-14 text-center space-y-2">
                <h2 class="text-base font-bold text-text-main">No platform announcements in this category yet</h2>
                <p class="text-xs text-text-muted">Official platform bulletins and policy updates published by Storkia Admin will appear here.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($announcements as $ann)
                    @php
                        $isRead = auth()->check() ? in_array((int) $ann->id, $readIds, true) : true;
                        $catBadge = match($ann->category) {
                            'policy_update' => 'bg-purple-100 text-purple-800 border-purple-200',
                            'maintenance'   => 'bg-amber-100 text-amber-800 border-amber-300',
                            'promotion'     => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            default         => 'bg-primary/10 text-primary border-primary/20',
                        };
                    @endphp
                    <article class="p-6 rounded-3xl bg-surface border {{ !$isRead ? 'border-primary ring-2 ring-primary/15' : 'border-border-subtle' }} shadow-xs space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border {{ $catBadge }}">
                                        {{ str_replace('_', ' ', $ann->category) }}
                                    </span>
                                    @if(!$isRead)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-danger text-[10px] font-extrabold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                                            New
                                        </span>
                                    @endif
                                    <span class="text-xs text-text-muted">
                                        {{ \Carbon\Carbon::parse($ann->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A') }} (PH Time)
                                    </span>
                                </div>
                                <h2 class="text-lg sm:text-xl font-bold text-primary-dark">{{ $ann->title }}</h2>
                            </div>

                            @auth
                                @if(!$isRead)
                                    <form action="{{ route('announcements.mark-read', $ann->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-surface-subtle hover:bg-brand-light/40 border border-border-subtle text-xs font-bold text-text-main cursor-pointer">
                                            Mark as Read
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[11px] font-semibold text-green-700">✓ Read</span>
                                @endif
                            @endauth
                        </div>

                        <div class="text-xs sm:text-sm text-text-main leading-relaxed whitespace-pre-line">
                            {{ $ann->body }}
                        </div>

                        @if(!empty($ann->attachment_path))
                            <div class="pt-2">
                                <img src="{{ asset('storage/' . $ann->attachment_path) }}"
                                     alt="{{ $ann->title }}"
                                     class="max-h-80 rounded-2xl border border-border-subtle object-contain">
                            </div>
                        @endif

                        @if($ann->category === 'policy_update')
                            <div class="pt-3 border-t border-border-subtle flex flex-wrap items-center gap-3 text-xs">
                                <a href="{{ route('terms') }}" class="font-bold text-primary hover:underline">
                                    Review Unified Terms &amp; Conditions &rarr;
                                </a>
                                <a href="{{ route('privacy') }}" class="font-bold text-text-muted hover:text-primary hover:underline">
                                    Review Privacy Policy &rarr;
                                </a>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection