@props(['products'])

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pb-12 relative">
    @foreach($products as $index => $product)
        <div x-show="selectedCategory === 'All' || selectedCategory === '{{ addslashes($product['category']) }}'"
             x-transition:enter="transition ease-out duration-500"
             class="group flex flex-col relative"
             x-cloak>
            
            <!-- Click Overlay for Auth / Guest Handling -->
            @guest
                <a href="{{ route('login') }}" class="absolute inset-0 z-20 cursor-pointer rounded-2xl"></a>
            @endguest
            @auth
                <div @click="activeProduct = {{ json_encode($product) }}; modalOpen = true" class="absolute inset-0 z-20 cursor-pointer rounded-2xl"></div>
            @endauth

            <div class="relative bg-surface-subtle rounded-2xl aspect-[4/3] flex items-center justify-center p-6 mb-4 overflow-hidden border border-border-subtle group-hover:shadow-md transition-shadow duration-300">
                <button class="absolute top-3 right-3 bg-surface p-2 rounded-full shadow-sm text-text-muted hover:text-primary transition-colors z-30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </button>
                
                <div class="w-full h-full flex items-center justify-center text-border-subtle group-hover:scale-105 transition-transform duration-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>

            <div class="flex flex-col flex-grow">
                <div class="flex justify-between items-start mb-1 gap-2">
                    <h3 class="font-bold text-text-main line-clamp-1 flex-grow">{{ $product['name'] }}</h3>
                    <span class="font-bold text-text-main shrink-0">${{ number_format($product['price'], 2) }}</span>
                </div>
                <p class="text-sm text-text-muted mb-3 line-clamp-1">{{ $product['desc'] }}</p>
                
                <div class="flex items-center space-x-1 mb-4 mt-auto">
                    <div class="flex text-success">
                        @for($i = 0; $i < $product['rating']; $i++)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        @endfor
                    </div>
                    <span class="text-xs text-text-muted">({{ $product['reviews'] }})</span>
                </div>

                <button class="w-full py-2 px-4 border-2 border-text-main rounded-full text-sm font-bold text-text-main hover:bg-primary-dark hover:border-primary-dark hover:text-surface transition-all duration-200 active:scale-[0.98] relative z-30">
                    Add to Cart
                </button>
            </div>
        </div>
    @endforeach
</div>