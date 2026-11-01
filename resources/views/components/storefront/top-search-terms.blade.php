{{--
    Ranked "Top searches" chips for the Trends page. Each chip opens the real search results
    for that term. Renders nothing until shoppers have actually searched for something.

    $terms comes from SearchLogService::topTerms().
--}}
@props(['terms' => collect()])

@if ($terms->isNotEmpty())
    <section aria-labelledby="top-searches-title" class="mb-7">
        <h2 id="top-searches-title" class="text-base sm:text-lg font-serif font-bold text-text-main tracking-tight mb-3">
            Top searches
        </h2>

        <ol class="flex flex-wrap gap-2">
            @foreach ($terms as $i => $item)
                <li>
                    <a href="{{ route('search.search', ['q' => $item->term]) }}"
                       class="inline-flex items-center gap-2 pl-2 pr-3.5 py-1.5 rounded-full border border-border-subtle bg-surface hover:border-primary/50 hover:bg-brand-light/30 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-extrabold {{ $i < 3 ? 'bg-primary-dark text-white' : 'bg-surface-subtle text-text-muted' }}">
                            {{ $i + 1 }}
                        </span>
                        <span class="text-sm font-semibold text-text-main">{{ $item->term }}</span>
                        <span class="text-[11px] text-text-muted">
                            {{ number_format($item->searchers) }} {{ \Illuminate\Support\Str::plural('shopper', $item->searchers) }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endif
