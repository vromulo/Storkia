<nav class="bg-surface sticky top-0 z-50 font-sans text-text-main">
    @guest
    <!-- Top notification bar -->
    <div class="hidden md:block py-1 text-xs bg-primary font-sans text-surface">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <a href="{{ route('seller.login') }}" class="text-surface hover:text-surface/80 font-medium hover:underline transition-colors">Start Selling</a>
                <span class="text-surface/60">|</span>
                <a href="{{ route('logistics.login') }}" class="text-surface hover:text-surface/80 font-medium hover:underline transition-colors">Join the Logistics Team</a>
            </div>
            <div class="flex items-center space-x-3">
                <a href="#" class="text-surface hover:text-surface/80 font-medium hover:underline transition-colors">Help</a>
                <span class="text-surface/60">|</span>
                <a href="#" class="text-surface hover:text-surface/80 font-medium hover:underline transition-colors">Contact</a>
            </div>
        </div>
    </div>
    @endguest

    <!-- Main Nav Container -->
    <div class="relative z-30 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14 sm:h-16 items-center">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
                <a href="/" class="flex items-center text-primary">
                    <img src="{{ asset('assets/pink-storkia-minimized.webp') }}" alt="Storkia" class="block md:hidden h-8 w-auto object-contain">
                    <img src="{{ asset('assets/pink-storkia-maximized.webp') }}" alt="Storkia" class="hidden md:block h-9 w-auto object-contain">
                </a>
            </div>

            <!-- Search Bar: Expandable wide search with Alpine.js -->
            <div class="flex flex-1 max-w-4xl mx-3 sm:mx-6 md:mx-10" x-data="searchComponent()">
                <div class="relative w-full group" @click.away="showSuggestions = false">
                    <!-- Search Form -->
                    <form @submit.prevent="submitSearch" class="relative">
                        <input 
                            x-model="query"
                            @input.debounce.300ms="fetchSuggestions"
                            @focus="if(query.length > 0) showSuggestions = true"
                            type="text" 
                            name="q"
                            autocomplete="off"
                            placeholder="Search products..." 
                            class="w-full bg-surface-subtle focus:bg-surface border border-transparent hover:border-text-main focus:border-text-main rounded-full py-2 sm:py-2.5 px-4 pl-11 outline-none text-text-main placeholder:text-text-muted text-sm font-normal transition-all duration-200"
                        >

                        <!-- Search Icon -->
                        <button type="submit" class="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted group-hover:text-text-main group-focus-within:text-text-main transition-colors duration-150 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <circle cx="11" cy="11" r="7" stroke-linecap="round" stroke-linejoin="round" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 16.5L21 21" />
                            </svg>
                        </button>
                    </form>

                    <!-- Suggestions Dropdown -->
                    <div x-show="showSuggestions && suggestions.length > 0" 
                         x-transition
                         x-cloak
                         class="absolute w-full mt-1 bg-surface border border-border-subtle rounded-xl shadow-xl overflow-hidden z-50 max-h-[60vh] overflow-y-auto">
                        <ul class="py-1">
                            <template x-for="item in suggestions" :key="item.category || 'all'">
                                <li>
                                    <!-- Dynamic URL mapped to category selection -->
                                    <a :href="'/search?q=' + encodeURIComponent(item.name) + (item.category ? '&category=' + encodeURIComponent(item.category) : '')" 
                                       class="flex items-center px-4 py-2 hover:bg-surface-subtle transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-text-muted mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <div>
                                            <span class="text-sm text-text-main font-medium" x-text="item.name"></span>
                                            <!-- Dynamically display category or 'All Categories' fallback -->
                                            <span class="text-xs text-text-muted ml-2" x-text="item.category ? 'in ' + item.category : 'in All Categories'"></span>
                                        </div>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Action Icons -->
            <div class="flex items-center space-x-5 md:space-x-8">
                <!-- Profile Menu -->
                <div x-data="{ open: false, timer: null }" 
                    @mouseenter="if (window.innerWidth >= 768) { clearTimeout(timer); open = true; }" 
                    @mouseleave="if (window.innerWidth >= 768) { timer = setTimeout(() => { open = false; }, 300); }" 
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
                        <button type="button" 
                                @click="window.innerWidth < 768 ? $dispatch('toggle-account-drawer') : (open = !open)" 
                                class="flex items-center text-text-main hover:text-primary transition-colors cursor-pointer focus:outline-none"
                                aria-label="User account menu">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </button>
                    @endauth
                    
                    <div x-show="open" x-cloak class="absolute right-0 top-full mt-2 w-48 bg-surface border border-border-subtle rounded-xl shadow-xl z-50 overflow-hidden">
                        <div class="p-2 flex flex-col space-y-1">
                            @guest
                                <a href="{{ route('login') }}" class="block px-3 py-2 text-xs font-bold text-primary rounded-lg hover:bg-surface-subtle transition-colors">Sign in / Register</a>
                            @endguest
                            @auth
                                <div class="px-3 py-2.5 bg-surface-subtle rounded-lg border border-border-subtle mb-1 flex items-center justify-between gap-2">
                                    <p class="text-xs font-bold text-text-main truncate">
                                        {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                                    </p>
                                    <div class="shrink-0 text-warning" title="Notice">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 fill-none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z" />
                                        </svg>
                                    </div>
                                </div>

                                <a href="{{ route('user.account') }}" class="block px-3 py-2 text-xs font-medium text-text-main rounded-lg hover:bg-surface-subtle hover:text-primary transition-colors">My Account</a>
                                <a href="#" class="block px-3 py-2 text-xs font-medium text-text-muted rounded-lg hover:bg-surface-subtle hover:text-primary transition-colors">My Orders</a>
                                <a href="#" class="block px-3 py-2 text-xs font-medium text-text-muted rounded-lg hover:bg-surface-subtle hover:text-primary transition-colors">My Messages</a>
                                <a href="#" class="block px-3 py-2 text-xs font-medium text-text-muted rounded-lg hover:bg-surface-subtle hover:text-primary transition-colors">My Vouchers</a>
                                <a href="#" class="block px-3 py-2 text-xs font-medium text-text-muted rounded-lg hover:bg-surface-subtle hover:text-primary transition-colors">Wishlist</a>

                                <hr class="border-border-subtle my-1 mx-2">

                                <form method="POST" action="{{ route('logout') }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-3 py-2 text-xs font-bold text-danger rounded-lg hover:bg-danger/10 transition-colors">Logout</button>
                                </form>
                            @endauth
                        </div>
                    </div>
                </div>

                <!-- Cart Icon Wrapper with Hover Dropdown -->
                <div 
                    x-data="{ cartOpen: false, cartTimer: null }"
                    @mouseenter="clearTimeout(cartTimer); cartOpen = true"
                    @mouseleave="cartTimer = setTimeout(() => { cartOpen = false }, 250)"
                    class="relative flex items-center h-full"
                >
                    <a href="{{ route('cart.index') }}" class="relative flex items-center text-text-main hover:text-primary transition-colors" aria-label="Shopping Cart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        
                        @auth
                            @php
                                $cartCount = \App\Models\Cart::where('user_id', auth()->id())->count();
                            @endphp
                            
                            @if($cartCount > 0)
                                <span class="absolute -top-1.5 -right-2 text-[10px] font-bold rounded-full h-4 w-4 flex items-center justify-center border border-surface bg-primary text-surface">
                                    {{ $cartCount > 99 ? '99+' :$cartCount }}
                                </span>
                            @endif
                        @endauth
                    </a>

                    <!-- Render Popover -->
                    <x-storefront.cart-dropdown />
                </div>

            </div>
        </div>
    </div>

    <!-- Alpine Search Script -->
    <script>
        document.addEventListener('alpine:init', () => {
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
                            this.suggestions = data;
                            this.showSuggestions = data.length > 0;
                        });
                },
                submitSearch() {
                    if (this.query.trim()) {
                        window.location.href = `/search?q=${encodeURIComponent(this.query.trim())}`;
                    }
                }
            }));
        });
    </script>
</nav>