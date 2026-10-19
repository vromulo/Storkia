@extends('layouts.app')

@section('content')
    @php
        $delays = ['0s', '-0.8s', '-1.6s', '-2.4s'];
        $colors = ['primary', 'secondary', 'primary', 'secondary'];
        $numbers = range(0, 9);
        $letters = range('A', 'Z');
    @endphp

    <div class="relative bg-surface-subtle font-sans antialiased text-text-main overflow-x-hidden min-h-screen pb-20" x-data="sellerFilter()">
        
        <!-- Hero Banner Header -->
        <div class="relative bg-gradient-to-b from-white via-white to-surface-subtle border-b border-border-subtle/60 pt-10 pb-14 px-4 sm:px-6 lg:px-8 text-center overflow-hidden">
            <div class="absolute -top-10 left-1/2 -translate-x-1/2 w-96 h-96 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 max-w-4xl mx-auto">
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold font-serif text-text-main tracking-tight uppercase mb-4">
                    Explore Our Stores
                </h1>
                <p class="text-sm sm:text-base lg:text-lg text-text-muted max-w-2xl mx-auto font-medium leading-relaxed">
                    Browse curated storefronts from local merchants. Filter by business category or search directly by name.
                </p>
            </div>
        </div>

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 relative z-20">
            
            <!-- Expandable Filter Console -->
            <div x-data="{ expanded: false }" class="bg-white rounded-3xl shadow-sm border border-border-subtle/80 p-5 sm:p-7 mb-8 transition-all duration-300">
                
                <div class="space-y-6">
                    <!-- Top Row Controls: Category & Expand Switch -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-5 border-b border-border-subtle/60">
                        
                        <!-- Sleek Custom Category Dropdown -->
                        <div class="w-full md:w-80 relative" x-data="{ open: false }">
                            <label class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Filter By Category</label>
                            
                            <div @click="open = !open" @click.away="open = false" 
                                 class="relative w-full bg-surface hover:bg-surface-subtle text-text-main text-sm font-bold rounded-2xl pl-11 pr-10 py-3 cursor-pointer shadow-2xs transition-all flex items-center justify-between select-none border"
                                 :class="open ? 'ring-2 ring-primary/30 border-primary' : 'border-border-subtle'">
                                
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-primary">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                                </div>
                                
                                <span class="truncate" x-text="activeCategory ? activeCategory : 'All Store Categories'"></span>
                                
                                <div class="absolute inset-y-0 right-0 flex items-center px-3.5 text-text-muted">
                                    <svg class="w-4 h-4 transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>

                            <!-- Dropdown Options with Modern Scrollbar -->
                            <div x-show="open" x-cloak
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 translate-y-2"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-2"
                                 class="absolute z-50 w-full mt-2 bg-white border border-border-subtle rounded-2xl shadow-xl py-1.5 max-h-72 overflow-y-auto modern-scrollbar">
                                
                                <button type="button" 
                                        @click="setCategory(''); open = false"
                                        class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                        :class="activeCategory === '' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                    All Store Categories
                                </button>

                                @foreach($mainCategories as $cat)
                                    <button type="button" 
                                            @click="setCategory('{{ addslashes($cat) }}'); open = false"
                                            class="w-full text-left px-4 py-2.5 text-sm transition-colors cursor-pointer"
                                            :class="activeCategory === '{{ addslashes($cat) }}' ? 'text-primary font-bold bg-primary/5' : 'text-text-main font-medium hover:bg-surface-subtle'">
                                        {{ $cat }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Toggle Expand/Collapse Button -->
                        <div class="flex items-center justify-between w-full md:w-auto gap-3 pt-2 md:pt-0">
                            <span class="text-xs font-bold text-text-muted uppercase tracking-wider md:hidden">Character Index</span>
                            <button type="button" @click="expanded = !expanded" 
                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold border border-border-subtle hover:border-primary/40 bg-surface hover:bg-surface-subtle text-text-main transition-all duration-300 shadow-2xs cursor-pointer">
                                <span x-text="expanded ? 'Collapse Filters' : 'Expand All Filters'"></span>
                                <svg class="w-4 h-4 transition-transform duration-300" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Characters Matrix (0-9 and A-Z) -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold uppercase tracking-wider text-text-muted">Index Filter (Numbers & Alphabet)</label>
                            <button x-show="activeLetter" x-cloak type="button" @click="setLetter(null)" class="text-xs font-bold text-primary hover:underline transition-all cursor-pointer">Reset Index</button>
                        </div>

                        <!-- Filter Grid: Collapsible via Alpine -->
                        <div class="transition-all duration-500 overflow-hidden" :class="expanded ? 'max-h-[1000px]' : 'max-h-[110px] sm:max-h-[52px]'">
                            <div class="flex flex-wrap gap-1.5 sm:gap-2">
                                
                                <!-- 'All' Button -->
                                <button type="button" @click="setLetter(null)" 
                                   class="px-3.5 py-2 rounded-xl text-xs font-extrabold cursor-pointer transition-all duration-300"
                                   :class="activeLetter === null ? 'bg-primary text-white shadow-md shadow-primary/20 scale-105' : 'bg-surface hover:bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/50'">
                                    ALL
                                </button>

                                <div class="w-px h-7 bg-border-subtle mx-1 self-center hidden sm:block"></div>

                                <!-- Individual Numbers 0 through 9 -->
                                @foreach($numbers as $num)
                                    @php $numStr = (string)$num; @endphp
                                    <button type="button" @click="setLetter('{{ $numStr }}')" 
                                       class="w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center cursor-pointer rounded-xl text-xs font-extrabold transition-all duration-300"
                                       :class="activeLetter === '{{ $numStr }}' ? 'bg-primary text-white shadow-md shadow-primary/20 scale-105' : 'bg-surface hover:bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/50'">
                                        {{ $numStr }}
                                    </button>
                                @endforeach

                                <div class="w-px h-7 bg-border-subtle mx-1 self-center hidden sm:block"></div>

                                <!-- Alphabet Letters A through Z -->
                                @foreach($letters as $char)
                                    <button type="button" @click="setLetter('{{ $char }}')" 
                                       class="w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center cursor-pointer rounded-xl text-xs font-extrabold transition-all duration-300"
                                       :class="activeLetter === '{{ $char }}' ? 'bg-primary text-white shadow-md shadow-primary/20 scale-105' : 'bg-surface hover:bg-surface-subtle text-text-main border border-border-subtle hover:border-primary/50'">
                                        {{ $char }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Filters Bar -->
            <div x-show="activeLetter !== null || activeCategory !== ''" x-cloak class="flex flex-wrap items-center gap-2.5 mb-8 text-xs sm:text-sm animate-fade-in-up">
                <span class="text-text-muted font-bold uppercase tracking-wider text-xs">Active Filters:</span>
                
                <span x-show="activeLetter !== null" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary font-bold">
                    Starts with '<span x-text="activeLetter"></span>'
                    <button type="button" @click="setLetter(null)" class="hover:bg-primary/20 rounded-full p-0.5 transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </span>

                <span x-show="activeCategory !== ''" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary font-bold">
                    Category: <span x-text="activeCategory"></span>
                    <button type="button" @click="setCategory('')" class="hover:bg-primary/20 rounded-full p-0.5 transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </span>

                <button type="button" @click="clearFilters" class="text-text-muted hover:text-primary font-bold underline underline-offset-4 ml-2 transition-colors cursor-pointer">
                    Clear All
                </button>
            </div>

            <!-- Sellers Grid Presentation -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-8 min-h-[400px]">
                @foreach($sellers as $index => $seller)
                    <div x-show="checkMatch('{{ addslashes(strtoupper($seller->business_name)) }}', '{{ addslashes($seller->line_of_business) }}')"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="animate-fade-in-up" style="animation-delay: {{ $index * 40 }}ms;">
                        <x-storefront.seller-card 
                            :seller="$seller" 
                            :color="$colors[$index % 4]" 
                            :animated="false" 
                        />
                    </div>
                @endforeach

                <!-- Empty State -->
                <div x-show="visibleCount === 0" x-cloak class="col-span-full flex flex-col items-center justify-center py-20 px-4 text-center bg-white rounded-3xl border border-border-subtle shadow-xs">
                    <div class="w-16 h-16 bg-surface-subtle rounded-2xl flex items-center justify-center mb-4 text-text-muted/60 border border-border-subtle">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-extrabold text-text-main mb-1">No Matching Sellers Found</h3>
                    <p class="text-text-muted font-medium text-xs sm:text-sm max-w-sm mx-auto mb-6">We couldn't find any stores matching your current filter selection.</p>
                    <button type="button" @click="clearFilters" class="px-6 py-2.5 bg-primary text-white rounded-full font-bold text-xs hover:bg-primary-dark transition-all duration-300 shadow-md shadow-primary/20 cursor-pointer">
                        Reset Filters
                    </button>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Real-time Alpine Filter Logic -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('sellerFilter', () => ({
                activeLetter: null,
                activeCategory: '',
                sellersData: [
                    @foreach($sellers as $seller)
                    {
                        name: '{!! addslashes(strtoupper($seller->business_name)) !!}',
                        category: '{!! addslashes($seller->line_of_business) !!}'
                    },
                    @endforeach
                ],
                get visibleCount() {
                    return this.sellersData.filter(s => this.checkMatch(s.name, s.category)).length;
                },
                checkMatch(name, category) {
                    let matchLetter = true;
                    let matchCategory = true;
                    
                    if (this.activeLetter) {
                        matchLetter = name.startsWith(this.activeLetter);
                    }
                    
                    if (this.activeCategory) {
                        matchCategory = category === this.activeCategory;
                    }
                    
                    return matchLetter && matchCategory;
                },
                setLetter(letter) {
                    this.activeLetter = (this.activeLetter === letter) ? null : letter;
                },
                setCategory(category) {
                    this.activeCategory = category;
                },
                clearFilters() {
                    this.activeLetter = null;
                    this.activeCategory = '';
                }
            }));
        });
    </script>

    <style>
        [x-cloak] { display: none !important; }
        
        @keyframes fade-in-up {
            0% { opacity: 0; transform: translateY(15px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fade-in-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Modern Custom Scrollbar for Dropdown */
        .modern-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .modern-scrollbar::-webkit-scrollbar-track {
            background: transparent;
            margin-block: 6px;
            border-radius: 10px;
        }
        .modern-scrollbar::-webkit-scrollbar-thumb {
            background-color: #E7E5E4; /* Subtly matches the border-subtle color */
            border-radius: 10px;
            transition: background-color 0.3s ease;
        }
        .modern-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: #CF4173; /* Primary color */
        }
        
        /* Firefox support */
        .modern-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: #E7E5E4 transparent;
        }
    </style>
@endsection