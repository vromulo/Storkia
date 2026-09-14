@props(['product'])

<a href="{{ route('product.show', $product->id) }}" class="group block bg-[#5a2e3f] rounded-2xl p-4 shadow-md hover:shadow-xl border border-[#5a2e3f]/50 transition-all duration-300 cursor-pointer">
    <!-- Image -->
    <div class="relative w-full h-48 mb-4 overflow-hidden rounded-xl bg-gray-50 flex items-center justify-center">
        @if(!empty($product->pictures) && is_array($product->pictures))
            <img src="{{ asset('storage/' . $product->pictures[0]) }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
        @else
            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        @endif
        
        @if($product->discount > 0)
            <!-- Updated discount background color to match the magenta image -->
            <span class="absolute top-2 left-2 bg-[#b8007a] text-white text-xs font-bold px-2 py-1 rounded shadow-sm border border-white/20">
                -{{ number_format($product->discount, 0) }}%
            </span>
        @endif
    </div>
    
    <!-- Info -->
    <div class="space-y-1">
        <h3 class="font-medium text-white truncate" title="{{ $product->name }}">{{ $product->name }}</h3>
        
        <div class="flex items-center space-x-2">
            @if($product->discount > 0)
                @php
                    $discountedPrice = $product->price - ($product->price * ($product->discount / 100));
                @endphp
                <span class="text-lg font-bold text-white">₱{{ number_format($discountedPrice, 2) }}</span>
                <span class="text-sm text-gray-300 line-through">₱{{ number_format($product->price, 2) }}</span>
            @else
                <span class="text-lg font-bold text-white">₱{{ number_format($product->price, 2) }}</span>
            @endif
        </div>
        
        <div class="flex items-center text-xs text-gray-300">
            <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
            <span>5.0 (0 Sold)</span>
        </div>
    </div>
</a>