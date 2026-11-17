<nav class="bg-surface sticky top-0 z-50 font-sans text-text-main border-b border-border-subtle/60">
    @php
        $unreadAnnouncementsCount = $unreadAnnouncementsCount ?? 0;
        $navAnnouncements = $navAnnouncements ?? collect();
        $navAnnouncementReadIds = $navAnnouncementReadIds ?? [];

        $chatBadges = $chatBadges ?? ['messages' => 0, 'support' => 0, 'total' => 0];
        $navMessagesBadge = (int) ($chatBadges['messages'] ?? 0);
        $navSupportBadge = (int) ($chatBadges['support'] ?? 0);
        $navChatTotal = (int) ($chatBadges['total'] ?? 0);
    @endphp
    @guest
        <div class="hidden md:block py-1.5 text-xs bg-gradient-to-r from-primary-dark via-primary to-primary-dark font-sans text-surface">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <a href="{{ route('seller.login') }}" class="text-surface hover:text-brand-light font-semibold hover:underline transition-colors cursor-pointer">Start Selling</a>
                    <span class="text-surface/50">|</span>
                    <a href="{{ route('logistics.login') }}" class="text-surface hover:text-brand-light font-semibold hover:underline transition-colors cursor-pointer">Join the Logistics Team</a>
                </div>
                <div class="flex items-center space-x-3 ml-auto">
                    <a href="{{ route('announcements.index') }}" class="text-surface hover:text-brand-light font-semibold hover:underline transition-colors cursor-pointer">Announcements</a>
                </div>
            </div>
        </div>
    @endguest

    <div class="relative z-30 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14 sm:h-16 items-center">
            <div class="flex-shrink-0 flex items-center gap-4">
                <a href="{{ route('home') }}" class="flex items-center text-primary cursor-pointer">
                    <img src="{{ asset('assets/pink-storkia-minimized.webp') }}" alt="Storkia" class="block md:hidden h-8 w-auto object-contain">
                    <img src="{{ asset('assets/pink-storkia-maximized.webp') }}" alt="Storkia" class="hidden md:block h-9 w-auto object-contain">
                </a>
            </div>

            <div class="flex flex-1 max-w-4xl mx-3 sm:mx-6 md:mx-8" x-data="searchComponent()">
                <div class="relative w-full group" @click.away="showSuggestions = false">
                    <form @submit.prevent="submitSearch" class="relative">
                        <input
                            x-model="query"
                            @input.debounce.300ms="fetchSuggestions"
                            @focus="if(query.length > 0) showSuggestions = true"
                            type="text"
                            name="q"
                            autocomplete="off"
                            placeholder="Search products, categories, or stores..."
                            class="w-full bg-surface-subtle focus:bg-surface border border-border-subtle hover:border-primary/60 focus:border-primary rounded-full py-2 sm:py-2.5 px-4 pl-11 outline-none text-text-main placeholder:text-text-muted text-xs sm:text-sm font-normal transition-all duration-200 shadow-2xs"
                        >
                        <button type="submit" class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted group-hover:text-primary group-focus-within:text-primary transition-colors duration-150 flex items-center justify-center cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <circle cx="11" cy="11" r="7" stroke-linecap="round" stroke-linejoin="round" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 16.5L21 21" />
                            </svg>
                        </button>
                    </form>

                    <div x-show="showSuggestions && suggestions.length > 0"
                         x-transition
                         x-cloak
                         class="absolute w-full mt-1.5 bg-surface border border-border-subtle rounded-2xl shadow-2xl overflow-hidden z-50 max-h-[60vh] overflow-y-auto custom-scrollbar">
                        <ul class="py-1.5 divide-y divide-border-subtle/50">
                            <template x-for="item in suggestions" :key="(item.name || '') + '_' + (item.category || 'all')">
                                <li>
                                    <a :href="'/search?q=' + encodeURIComponent(item.name) + (item.category ? '&category=' + encodeURIComponent(item.category) : '')"
                                       class="flex items-center px-4 py-2.5 hover:bg-brand-light/30 transition-colors cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary mr-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <div class="truncate">
                                            <span class="text-xs sm:text-sm text-text-main font-semibold" x-text="item.name"></span>
                                            <span class="text-[11px] text-text-muted ml-2 font-medium" x-text="item.category ? 'in ' + item.category : 'in All Categories'"></span>
                                        </div>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-4 sm:space-x-6 md:space-x-7">
                <div x-data="navDropdown('account', 'open', 300, true)"
                     @mouseenter="enter()"
                     @mouseleave="leave()"
                     class="relative flex items-center h-full">
                    @guest
                        <a href="{{ route('login') }}"
                           class="flex items-center text-text-main hover:text-primary transition-colors cursor-pointer focus:outline-none"
                           aria-label="Sign in or Register">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </a>
                    @endguest

                    @auth
                        @if(auth()->user()->role === 'Buyer')
                            <a href="{{ route('user.account') }}" class="relative flex items-center text-text-main hover:text-primary transition-colors cursor-pointer focus:outline-none" aria-label="Personal Center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                @if($navChatTotal > 0)
                                    <span class="absolute -top-1.5 -right-2 min-w-4 h-4 px-1 text-[10px] font-bold rounded-full flex items-center justify-center border border-surface bg-danger text-white">{{ $navChatTotal > 9 ? '9+' : $navChatTotal }}</span>
                                @endif
                            </a>
                        @elseif(auth()->user()->role === 'Seller')
                            <a href="{{ route('seller.seller-dashboard') }}" class="relative flex items-center text-text-main hover:text-primary transition-colors cursor-pointer focus:outline-none" title="Seller Portal">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                @if($navChatTotal > 0)
                                    <span class="absolute -top-1.5 -right-2 min-w-4 h-4 px-1 text-[10px] font-bold rounded-full flex items-center justify-center border border-surface bg-danger text-white">{{ $navChatTotal > 9 ? '9+' : $navChatTotal }}</span>
                                @endif
                            </a>
                        @elseif(auth()->user()->role === 'Logistics')
                            <a href="{{ route('logistics.logistics-dashboard') }}" class="relative flex items-center text-text-main hover:text-primary transition-colors cursor-pointer focus:outline-none" title="Logistics Operations">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                @if($navChatTotal > 0)
                                    <span class="absolute -top-1.5 -right-2 min-w-4 h-4 px-1 text-[10px] font-bold rounded-full flex items-center justify-center border border-surface bg-danger text-white">{{ $navChatTotal > 9 ? '9+' : $navChatTotal }}</span>
                                @endif
                            </a>
                        @endif
                    @endauth

                    <div x-show="open" x-cloak class="absolute right-0 top-full mt-2 w-56 bg-surface border border-border-subtle rounded-2xl shadow-2xl z-50 overflow-hidden">
                        <div class="p-2 flex flex-col space-y-1">
                            @guest
                                <a href="{{ route('login') }}" class="block px-3 py-2 text-xs font-bold text-primary rounded-xl hover:bg-brand-light/30 transition-colors cursor-pointer">Sign in / Register</a>
                            @endguest
                            @auth
                                <div class="px-3 py-2.5 bg-surface-subtle rounded-xl border border-border-subtle mb-1 flex items-center justify-between gap-2">
                                    <p class="text-xs font-bold text-text-main truncate">
                                        {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                                    </p>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-primary/10 text-primary uppercase">
                                        {{ auth()->user()->role }}
                                    </span>
                                </div>
                                @if(auth()->user()->role === 'Buyer')
                                    <a href="{{ route('user.account-management') }}" class="block px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">My Account</a>
                                    <a href="{{ route('user.orders') }}" class="block px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">My Orders</a>
                                    <a href="{{ route('user.points') }}" class="flex items-center justify-between px-3 py-2 text-xs font-bold text-primary rounded-xl hover:bg-brand-light/30 transition-colors cursor-pointer">
                                        <span>My Coins</span>
                                        <span class="px-1.5 py-0.5 rounded-full bg-brand-light/60 text-primary-dark text-[10px]">Spin</span>
                                    </a>
                                    <a href="{{ route('user.coupons') }}" class="block px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">My Vouchers</a>
                                    <a href="{{ route('user.messages.buyer-messages') }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">
                                        <span>My Messages</span>
                                        @if($navMessagesBadge > 0)
                                            <span class="min-w-4 h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center">{{ $navMessagesBadge > 9 ? '9+' : $navMessagesBadge }}</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('user.help-center.index') }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">
                                        <span>Help Center</span>
                                        @if($navSupportBadge > 0)
                                            <span class="min-w-4 h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center">{{ $navSupportBadge > 9 ? '9+' : $navSupportBadge }}</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('user.wishlist') }}" class="block px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">Wishlist</a>
                                @elseif(auth()->user()->role === 'Seller')
                                    <a href="{{ route('seller.seller-dashboard') }}" class="block px-3 py-2 text-xs font-bold text-primary rounded-xl hover:bg-brand-light/30 transition-colors cursor-pointer">Seller Dashboard</a>
                                    <a href="{{ route('seller.products.index') }}" class="block px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">Products Catalog</a>
                                    <a href="{{ route('seller.messages.seller-messages') }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">
                                        <span>Messages</span>
                                        @if($navMessagesBadge > 0)
                                            <span class="min-w-4 h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center">{{ $navMessagesBadge > 9 ? '9+' : $navMessagesBadge }}</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('seller.help-center.index') }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">
                                        <span>Help Center</span>
                                        @if($navSupportBadge > 0)
                                            <span class="min-w-4 h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center">{{ $navSupportBadge > 9 ? '9+' : $navSupportBadge }}</span>
                                        @endif
                                    </a>
                                @elseif(auth()->user()->role === 'Logistics')
                                    <a href="{{ route('logistics.logistics-dashboard') }}" class="block px-3 py-2 text-xs font-bold text-primary rounded-xl hover:bg-brand-light/30 transition-colors cursor-pointer">Logistics Operations</a>
                                    <a href="{{ route('logistics.messages.logistics-messages') }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">
                                        <span>Seller Messages</span>
                                        @if($navMessagesBadge > 0)
                                            <span class="min-w-4 h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center">{{ $navMessagesBadge > 9 ? '9+' : $navMessagesBadge }}</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('logistics.help-center.index') }}" class="flex items-center justify-between px-3 py-2 text-xs font-medium text-text-main rounded-xl hover:bg-brand-light/30 hover:text-primary transition-colors cursor-pointer">
                                        <span>Help Center</span>
                                        @if($navSupportBadge > 0)
                                            <span class="min-w-4 h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center">{{ $navSupportBadge > 9 ? '9+' : $navSupportBadge }}</span>
                                        @endif
                                    </a>
                                @endif
                                <hr class="border-border-subtle my-1 mx-2">
                                <form method="POST" action="{{ route('logout') }}" data-no-live class="m-0">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-3 py-2 text-xs font-bold text-danger rounded-xl hover:bg-danger/10 transition-colors cursor-pointer">Logout</button>
                                </form>
                            @endauth
                        </div>
                    </div>
                </div>

                @if(auth()->check() && auth()->user()->role === 'Buyer')
                    @php
                        $unreadCount = auth()->user()->unreadNotifications()->count();
                        $recentNotifications = auth()->user()->notifications()->take(8)->get();
                        $markAllAnnRoute = \Illuminate\Support\Facades\Route::has('announcements.markAllRead')
                            ? route('announcements.markAllRead')
                            : url('/announcements/read-all');
                    @endphp
                    <div data-live-notifications
                         x-data="navDropdown('notif', 'notifOpen', 250, false, { notifTab: 'general', notifLimit: 4 })"
                         @mouseenter="enter()"
                         @mouseleave="leave()"
                         @click.away="close()"
                         @keydown.escape.window="close()"
                         class="relative flex items-center h-full">
                        <button type="button"
                                @click="toggle()"
                                class="relative flex items-center text-text-main hover:text-primary transition-colors cursor-pointer focus:outline-none"
                                aria-label="Notifications">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            @if($unreadCount + $unreadAnnouncementsCount > 0)
                                <span class="absolute -top-1.5 -right-2 text-[10px] font-bold rounded-full h-4 w-4 flex items-center justify-center border border-surface bg-danger text-white">
                                    {{ ($unreadCount + $unreadAnnouncementsCount) > 9 ? '9+' : ($unreadCount + $unreadAnnouncementsCount) }}
                                </span>
                            @endif
                        </button>

                        <div x-show="notifOpen" x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                            class="absolute right-0 top-full mt-2 w-72 sm:w-96 bg-surface border border-border-subtle rounded-2xl shadow-2xl z-50 overflow-hidden">
                            <div class="p-4 pb-0 bg-surface-subtle">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-sm text-primary-dark">Notifications</span>
                                    <div class="flex items-center gap-3">
                                        @if($unreadCount > 0)
                                            <form method="POST" action="{{ route('user.notifications.markAllRead') }}" x-show="notifTab === 'general'">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-xs font-bold text-primary hover:underline cursor-pointer">Mark all read</button>
                                            </form>
                                        @endif
                                        @if($unreadAnnouncementsCount > 0)
                                            <form method="POST" action="{{ $markAllAnnRoute }}" x-show="notifTab === 'announcement'" x-cloak>
                                                @csrf
                                                <button type="submit" class="text-xs font-bold text-primary hover:underline cursor-pointer">Mark all read</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-end gap-1 mt-3" role="tablist" aria-label="Notification categories">
                                    <button type="button" role="tab"
                                            @click="notifTab = 'general'"
                                            :aria-selected="notifTab === 'general'"
                                            :class="notifTab === 'general' ? 'text-primary border-primary' : 'text-text-muted border-transparent hover:text-text-main'"
                                            class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold border-b-2 transition-colors cursor-pointer focus:outline-none">
                                        General
                                        @if($unreadCount > 0)
                                            <span class="min-w-4 h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                                        @endif
                                    </button>
                                    <button type="button" role="tab"
                                            @click="notifTab = 'announcement'"
                                            :aria-selected="notifTab === 'announcement'"
                                            :class="notifTab === 'announcement' ? 'text-primary border-primary' : 'text-text-muted border-transparent hover:text-text-main'"
                                            class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold border-b-2 transition-colors cursor-pointer focus:outline-none">
                                        Announcement
                                        @if($unreadAnnouncementsCount > 0)
                                            <span class="min-w-4 h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center">{{ $unreadAnnouncementsCount > 9 ? '9+' : $unreadAnnouncementsCount }}</span>
                                        @endif
                                    </button>
                                </div>
                            </div>
                            <div class="border-t border-border-subtle"></div>
                            <div x-show="notifTab === 'general'" role="tabpanel" class="max-h-80 overflow-y-auto divide-y divide-border-subtle/60 custom-scrollbar">
                                @forelse($recentNotifications as $nIdx => $notif)
                                    @php
                                        $data = $notif->data;
                                        $isUnread = is_null($notif->read_at);
                                        $nType = $data['type'] ?? '';
                                    @endphp
                                    <div x-show="{{ $nIdx }} < notifLimit" class="p-4 {{ $isUnread ? 'bg-brand-light/15' : 'bg-surface' }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <h4 class="text-xs font-bold {{ $nType === 'danger' ? 'text-danger' : ($nType === 'success' ? 'text-success' : 'text-primary-dark') }}">
                                                {{ $data['title'] ?? 'Notice' }}
                                            </h4>
                                            <span class="text-[10px] text-text-muted shrink-0">{{ $notif->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-xs text-text-main mt-1 leading-relaxed">{{ $data['message'] ?? '' }}</p>
                                        <div class="mt-2 flex items-center justify-between gap-3">
                                            @if(isset($data['verification_id']))
                                                <a href="{{ ($data['status'] ?? '') === 'failed' ? route('user.identity-verification') : route('user.account-management') }}"
                                                   class="text-[11px] font-bold text-primary hover:underline cursor-pointer">
                                                    {{ ($data['status'] ?? '') === 'failed' ? 'Try again' : 'View account' }}
                                                </a>
                                            @elseif(!empty($data['url']))
                                                <a href="{{ route('user.notifications.open', $notif->id) }}"
                                                   class="text-[11px] font-bold text-primary hover:underline cursor-pointer">
                                                    {{ !empty($data['seller_order_id']) ? 'View order' : 'View details' }}
                                                </a>
                                            @else
                                                <span></span>
                                            @endif
                                            @if($isUnread)
                                                <form method="POST" action="{{ route('user.notifications.markRead', $notif->id) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="text-[10px] font-bold text-text-muted hover:text-primary cursor-pointer">Dismiss</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-8 text-center text-text-muted text-xs">No notifications at this time.</div>
                                @endforelse
                            </div>

                            @if($recentNotifications->count() > 4)
                                <div x-show="notifTab === 'general'" class="p-2.5 border-t border-border-subtle bg-surface-subtle text-center">
                                    <button type="button"
                                            @click="notifLimit = notifLimit >= {{ $recentNotifications->count() }} ? 4 : {{ $recentNotifications->count() }}"
                                            class="text-xs font-extrabold text-primary hover:text-primary-dark cursor-pointer"
                                            x-text="notifLimit >= {{ $recentNotifications->count() }} ? 'See Less' : 'See All ({{ $recentNotifications->count() }})'"></button>
                                </div>
                            @endif

                            <div x-show="notifTab === 'announcement'" x-cloak role="tabpanel" class="max-h-80 overflow-y-auto divide-y divide-border-subtle/60 custom-scrollbar">
                                @forelse($navAnnouncements as $ann)
                                    @php
                                        $annRead = in_array((int) $ann->id, $navAnnouncementReadIds, true);
                                        $annBadge = match($ann->category) {
                                            'policy_update' => 'bg-primary/15 text-primary-dark border-primary/30',
                                            'maintenance'   => 'bg-brand-light/70 text-primary-dark border-border-subtle',
                                            'promotion'     => 'bg-success/15 text-success border-success/25',
                                            default         => 'bg-primary/10 text-primary border-primary/20',
                                        };
                                        $markSingleAnnRoute = \Illuminate\Support\Facades\Route::has('announcements.markRead')
                                            ? route('announcements.markRead', $ann->id)
                                            : url('/announcements/' . $ann->id . '/read');
                                    @endphp
                                    <div class="p-4 {{ $annRead ? 'bg-surface' : 'bg-brand-light/15' }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase border {{ $annBadge }}">
                                                {{ str_replace('_', ' ', $ann->category) }}
                                            </span>
                                            <span class="text-[10px] text-text-muted shrink-0">{{ \Carbon\Carbon::parse($ann->created_at)->diffForHumans() }}</span>
                                        </div>
                                        <h4 class="text-xs font-bold text-primary-dark mt-2 flex items-center gap-1.5">
                                            @unless($annRead)
                                                <span class="w-1.5 h-1.5 rounded-full bg-danger shrink-0"></span>
                                            @endunless
                                            {{ $ann->title }}
                                        </h4>
                                        <p class="text-xs text-text-main mt-1 leading-relaxed">{{ \Illuminate\Support\Str::limit(strip_tags((string) $ann->body), 110) }}</p>
                                        <div class="mt-2 flex items-center justify-between gap-3">
                                            <a href="{{ route('announcements.index') }}" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Read more</a>
                                            @unless($annRead)
                                                <form method="POST" action="{{ $markSingleAnnRoute }}">
                                                    @csrf
                                                    <button type="submit" class="text-[10px] font-bold text-text-muted hover:text-primary cursor-pointer">Mark as read</button>
                                                </form>
                                            @endunless
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-8 text-center text-text-muted text-xs">No announcements at this time.</div>
                                @endforelse
                            </div>
                            <div x-show="notifTab === 'announcement'" x-cloak class="p-3 border-t border-border-subtle bg-surface-subtle text-center">
                                <a href="{{ route('announcements.index') }}" class="text-xs font-bold text-primary-dark hover:underline cursor-pointer">View all announcements &rarr;</a>
                            </div>
                        </div>
                    </div>
                @endif

                @if(!auth()->check() || auth()->user()->role === 'Buyer')
                    <div
                        data-cart-dropdown-wrapper
                        x-data="navDropdown('cart', 'cartOpen', 250)"
                        @mouseenter="enter()"
                        @mouseleave="leave()"
                        class="relative flex items-center h-full"
                    >
                        <a href="{{ route('cart.index') }}" data-cart-icon class="relative flex items-center text-text-main hover:text-primary transition-colors cursor-pointer" aria-label="Shopping Cart">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            @auth
                                @php
                                    $cartCount = \App\Models\Cart::where('user_id', auth()->id())->count();
                                @endphp
                                <span data-cart-badge class="{{ $cartCount > 0 ? '' : 'hidden' }} absolute -top-1.5 -right-2 text-[10px] font-bold rounded-full h-4 w-4 flex items-center justify-center border border-surface bg-danger text-white">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
                            @endauth
                        </a>

                        <x-storefront.cart-dropdown />
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('navDropdown', { active: null });

            Alpine.data('navDropdown', (name, key, delay = 250, desktopOnly = false, extra = {}) => {
                const data = {
                    ...extra,
                    hovering: false,
                    timer: null,
                    enter() {
                        if (desktopOnly && window.innerWidth < 768) return;
                        this.hovering = true;
                        clearTimeout(this.timer);
                        const store = Alpine.store('navDropdown');
                        if (store.active === null || store.active === name) store.active = name;
                    },
                    leave() {
                        if (desktopOnly && window.innerWidth < 768) return;
                        this.hovering = false;
                        clearTimeout(this.timer);
                        this.timer = setTimeout(() => this.release(), delay);
                    },
                    release() {
                        const store = Alpine.store('navDropdown');
                        if (store.active === name) store.active = null;
                    },
                    close() {
                        clearTimeout(this.timer);
                        this.hovering = false;
                        this.release();
                    },
                    toggle() {
                        const store = Alpine.store('navDropdown');
                        if (store.active === name) this.close();
                        else store.active = name;
                    },
                    init() {
                        this.$watch('$store.navDropdown.active', (v) => {
                            if (v === null && this.hovering) Alpine.store('navDropdown').active = name;
                        });
                    },
                };
                Object.defineProperty(data, key, {
                    get: () => Alpine.store('navDropdown').active === name,
                    enumerable: true,
                    configurable: true,
                });
                return data;
            });

            Alpine.data('searchComponent', () => ({
                query: new URLSearchParams(window.location.search).get('q') || '',
                suggestions: [],
                showSuggestions: false,
                fetchSuggestions() {
                    if (this.query.length < 2) {
                        this.suggestions = [];
                        this.showSuggestions = false;
                        return;
                    }
                    fetch(`/search/suggestions?q=${encodeURIComponent(this.query)}`)
                        .then(res => res.json())
                        .then(data => {
                            this.suggestions = Array.isArray(data) ? data : (data.data || []);
                            this.showSuggestions = this.suggestions.length > 0;
                        });
                },
                submitSearch() {
                    if (this.query.trim()) {
                        window.location.href = `/search?q=${encodeURIComponent(this.query.trim())}`;
                    }
                }
            }));
        });

        window.addEventListener('storkia:ws-event', (event) => {
            const detail = (event && event.detail) || {};
            if (detail.scope === 'cart' && typeof detail.cart_count !== 'undefined' && typeof window.setCartBadge === 'function') {
                window.setCartBadge(Number(detail.cart_count));
            }
        });
    </script>
</nav>