@props(['seller', 'delay' => '0s', 'color' => 'primary', 'isHome' => false, 'animated' => true])

@php
    $hoverColor = $color === 'primary' ? 'group-hover:text-primary' : 'group-hover:text-secondary-dark';
    
    $gradientClass = $color === 'primary' 
        ? 'bg-[conic-gradient(from_0deg,theme(colors.primary)_0%,theme(colors.primary)_20%,#fff_30%,theme(colors.primary)_40%,theme(colors.primary)_70%,#fff_80%,theme(colors.primary)_90%,theme(colors.primary)_100%)]' 
        : 'bg-[conic-gradient(from_0deg,theme(colors.secondary)_0%,theme(colors.secondary)_20%,#fff_30%,theme(colors.secondary)_40%,theme(colors.secondary)_70%,#fff_80%,theme(colors.secondary)_90%,theme(colors.secondary)_100%)]';
@endphp

@if(!$isHome && $animated)
    @once
        <style>
            @keyframes spin-shiny-ring {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .animate-spin-shiny-ring {
                animation: spin-shiny-ring 3.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            }
        </style>
    @endonce
@endif

@if($isHome)
    <a href="{{ route('sellers.show', $seller->id) }}" class="block bg-white rounded-2xl p-3 sm:p-4 shadow-sm border border-border-subtle/50 hover:shadow-md hover:border-primary/30 transition-all duration-300 group cursor-pointer text-center relative overflow-hidden w-full h-full flex flex-col items-center justify-center">
        <div class="relative w-12 h-12 sm:w-14 sm:h-14 rounded-full mb-2 mx-auto flex items-center justify-center p-0.5 overflow-hidden bg-surface-subtle flex-shrink-0 border border-primary/20">
            <div class="relative z-10 w-full h-full bg-white rounded-full flex items-center justify-center overflow-hidden">
                @if($seller->logo_path)
                    <img src="{{ Storage::url($seller->logo_path) }}" alt="{{ $seller->business_name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center">
                        <span class="text-base sm:text-lg font-bold text-primary {{ $hoverColor }}">
                            {{ strtoupper(substr($seller->business_name, 0, 1)) }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
        
        <h3 class="text-xs sm:text-sm font-bold mb-0.5 tracking-tight {{ $hoverColor }} transition-colors line-clamp-1 leading-tight">{{ $seller->business_name }}</h3>
        <p class="text-[10px] sm:text-xs text-text-muted font-medium line-clamp-1">{{ $seller->line_of_business }}</p>
    </a>
@else
    <a href="{{ route('sellers.show', $seller->id) }}" class="block bg-white rounded-[1.5rem] sm:rounded-[2.5rem] p-4 sm:p-6 lg:p-10 shadow-md border border-border-subtle/50 hover:shadow-2xl hover:shadow-primary/10 hover:-translate-y-2 transition-all duration-500 group cursor-pointer text-center relative overflow-hidden w-full h-full flex flex-col items-center justify-center">
        
        <div class="relative w-20 h-20 sm:w-24 sm:h-24 lg:w-32 lg:h-32 rounded-full mb-4 sm:mb-6 mx-auto group-hover:scale-110 transition-transform duration-500 shadow-inner flex items-center justify-center p-[3px] sm:p-[4px] overflow-hidden bg-surface-subtle flex-shrink-0">
            
            @if($animated)
                <div class="absolute w-[200%] h-[200%] {{ $gradientClass }} animate-spin-shiny-ring opacity-80 blur-[8px]" style="animation-delay: {{ $delay }};"></div>
                <div class="absolute w-[200%] h-[200%] {{ $gradientClass }} animate-spin-shiny-ring opacity-100" style="animation-delay: {{ $delay }};"></div>
            @else
                <div class="absolute inset-0 bg-gradient-to-br from-primary via-secondary to-primary/40 opacity-80"></div>
            @endif
            
            <div class="absolute inset-[3px] sm:inset-[4px] bg-white rounded-full"></div>
            
            <div class="relative z-10 w-full h-full bg-white rounded-full flex items-center justify-center shadow-[inset_0_2px_10px_rgba(0,0,0,0.03)] overflow-hidden">
                @if($seller->logo_path)
                    <img src="{{ Storage::url($seller->logo_path) }}" alt="{{ $seller->business_name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full bg-gradient-to-br from-primary/10 to-secondary/10 flex items-center justify-center transition-colors duration-500 group-hover:from-primary/20 group-hover:to-secondary/20">
                        <span class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-primary {{ $hoverColor }} transition-colors duration-500">
                            {{ strtoupper(substr($seller->business_name, 0, 1)) }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
        
        <h3 class="text-base sm:text-xl lg:text-2xl font-bold mb-1 sm:mb-2 tracking-tight {{ $hoverColor }} transition-colors line-clamp-2 leading-tight">{{ $seller->business_name }}</h3>
        <p class="text-xs sm:text-sm text-text-muted font-medium line-clamp-1">{{ $seller->line_of_business }}</p>
    </a>
@endif