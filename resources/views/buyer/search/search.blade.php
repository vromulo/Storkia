<x-app>
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 font-sans">
        
        <!-- Search Header & Filters -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 border-b border-border-subtle pb-6">
            <div>
                <h1 class="text-2xl font-bold text-text-main">
                    Search Results for <span class="text-primary">"{{ $query }}"</span>
                </h1>
                <p class="text-sm text-text-muted mt-1">{{ $products->total() }} items found</p>
            </div>

            <!-- Modern Dropdown Filters -->
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('search.search') }}" class="flex items-center gap-2" id="filterForm">
                    @if($query)
                        <input type="hidden" name="q" value="{{ $query }}">
                    @endif
                    
                    <!-- Category Dropdown (Allows clearing/changing category even if set via search suggestion) -->
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" @click.away="open = false" class="flex items-center justify-between min-w-[160px] px-4 py-2 text-sm bg-surface border border-border-subtle rounded-lg text-text-main hover:border-primary transition-colors focus:outline-none">
                            <span class="truncate">{{ $category ?: 'All Categories' }}</span>
                            <svg class="w-4 h-4 ml-2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute right-0 z-10 w-full mt-1 bg-surface border border-border-subtle rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <!-- Option to clear the category and revert to All -->
                            <a href="{{ request()->fullUrlWithQuery(['category' => null, 'subcategory' => null]) }}" class="block px-4 py-2 text-sm text-text-main hover:bg-surface-subtle hover:text-primary transition-colors">All Categories</a>
                            
                            <!-- Available Categories list to change search scope dynamically -->
                            @foreach($categories as $cat)
                                <a href="{{ request()->fullUrlWithQuery(['category' => $cat, 'subcategory' => null]) }}" class="block px-4 py-2 text-sm text-text-main hover:bg-surface-subtle hover:text-primary transition-colors {{ $category == $cat ? 'bg-surface-subtle text-primary font-medium' : '' }}">
                                    {{ $cat }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Subcategory Dropdown -->
                    @if(count($subcategories) > 0 && $category)
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" @click.away="open = false" class="flex items-center justify-between min-w-[160px] px-4 py-2 text-sm bg-surface border border-border-subtle rounded-lg text-text-main hover:border-primary transition-colors focus:outline-none">
                            <span class="truncate">{{ $subcategory ?: 'All Subcategories' }}</span>
                            <svg class="w-4 h-4 ml-2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute right-0 z-10 w-full mt-1 bg-surface border border-border-subtle rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <a href="{{ request()->fullUrlWithQuery(['subcategory' => null]) }}" class="block px-4 py-2 text-sm text-text-main hover:bg-surface-subtle hover:text-primary transition-colors">All Subcategories</a>
                            @foreach($subcategories as $sub)
                                <a href="{{ request()->fullUrlWithQuery(['subcategory' => $sub]) }}" class="block px-4 py-2 text-sm text-text-main hover:bg-surface-subtle hover:text-primary transition-colors {{ $subcategory == $sub ? 'bg-surface-subtle text-primary font-medium' : '' }}">
                                    {{ $sub }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </form>
            </div>
        </div>

        <!-- Product Grid -->
        @if($products->count() > 0)
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                @foreach($products as $product)
                    <!-- Component Call mapping to seller-product-card.blade.php -->
                    <x-storefront.seller-product-card :product="$product" />
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-8">
                {{ $products->links() }}
            </div>
        @else
            <div class="text-center py-16 bg-surface border border-border-subtle rounded-xl">
                <svg class="mx-auto h-12 w-12 text-text-muted mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-lg font-medium text-text-main">No products found</h3>
                <p class="text-sm text-text-muted mt-1">We couldn't find anything matching "{{ $query }}"{{ $category ? ' in ' . $category : '' }}. Try adjusting your search or filters.</p>
                <a href="{{ route('home') }}" class="mt-6 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-surface bg-primary hover:bg-primary-dark transition-colors">
                    Back to Home
                </a>
            </div>
        @endif
    </div>
</x-app>