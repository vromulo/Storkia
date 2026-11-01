@extends('layouts.app', ['title' => 'Trends'])

@section('content')
    <div class="bg-surface font-sans antialiased text-text-main overflow-x-hidden min-h-screen">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6">

            <x-ui.breadcrumbs :items="['Trends']" class="mb-3" />

            <header class="rounded-2xl border border-brand-light bg-brand-light/30 px-5 py-5 sm:px-7 sm:py-6 mb-6">
                <div class="max-w-xl">
                    <div class="flex items-center gap-2.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        <h1 class="text-2xl sm:text-3xl font-serif font-bold text-text-main tracking-tight leading-none">
                            Trends
                        </h1>
                    </div>
                    <p class="mt-2 text-sm text-text-muted leading-relaxed">
                        What shoppers on Storkia are searching for, ordering and saving the most over the last {{ $windowDays }} days, with new arrivals mixed in.
                    </p>
                </div>
            </header>

            <x-storefront.top-search-terms :terms="$topSearches" />

            @if ($totalAll > 0)
                <x-storefront.promo-filter-bar
                    :categories="$categories"
                    :selected-category="$category"
                    :total="$totalAll"
                    :sorts="$sorts"
                    :current-sort="$sort"
                    default-sort="trending"
                />
            @endif

            @if ($trends->count() > 0)
                <x-storefront.batch-grid :total="$trends->count()" noun="products">
                    @foreach ($trends as $i => $item)
                        @php $rank = $i + 1; @endphp
                        <div class="flex flex-col" x-show="{{ $i }} < visibleCount">
                            <x-storefront.seller-product-card :product="$item->product" :is-wishlisted="(bool) $item->product->is_wishlisted" />

                            <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                {{-- Rank only makes sense when the list is ordered by trending score --}}
                                @if ($sort === 'trending' && $item->score > 0)
                                    <span class="inline-flex items-center justify-center min-w-[1.75rem] px-1.5 py-0.5 rounded-md text-[10px] font-extrabold {{ $rank <= 3 ? 'bg-primary-dark text-white' : 'bg-surface-subtle text-text-main border border-border-subtle' }}">
                                        #{{ $rank }}
                                    </span>
                                @endif

                                @if ($item->ordered > 0)
                                    <span class="text-[11px] font-semibold text-primary">{{ number_format($item->ordered) }} ordered this month</span>
                                @elseif ($item->saves > 0)
                                    <span class="text-[11px] font-semibold text-primary">{{ number_format($item->saves) }} {{ \Illuminate\Support\Str::plural('save', $item->saves) }} this month</span>
                                @elseif ($item->is_new)
                                    <span class="text-[11px] font-semibold text-text-muted">New arrival</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </x-storefront.batch-grid>
            @elseif ($totalAll > 0)
                <div class="py-16 text-center text-text-muted">
                    <h2 class="text-base font-bold text-text-main">Nothing is trending in {{ $category }} yet</h2>
                    <p class="text-xs mt-1 max-w-sm mx-auto">Other categories have popular picks right now.</p>
                    <a href="{{ route('trends.index') }}" class="inline-flex mt-4 px-5 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
                        Show all trends
                    </a>
                </div>
            @else
                <div class="py-20 text-center text-text-muted">
                    <svg class="w-12 h-12 mx-auto mb-3 text-text-muted/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    <h2 class="text-base font-bold text-text-main">Trends will show up here soon</h2>
                    <p class="text-xs mt-1 max-w-sm mx-auto">As shoppers order and save products, the most popular ones appear on this page.</p>
                    <a href="{{ route('home') }}" class="inline-flex mt-4 px-5 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
                        Browse all products
                    </a>
                </div>
            @endif

        </div>
    </div>
@endsection