{{--
    Category chips + sort dropdown shared by the Super Deals and Trends pages.
    Everything is plain links / a GET form, so filters work without JS and stay bookmarkable.
--}}
@props([
    'categories'       => [],   // ['Electronics' => 12, ...]
    'selectedCategory' => null,
    'total'            => 0,    // unfiltered total, shown on the "All" chip
    'sorts'            => [],   // ['value' => 'Label', ...]
    'currentSort'      => null,
    'defaultSort'      => null,
])

@php
    $baseUrl = url()->current();
    $chipUrl = function (?string $cat) use ($baseUrl, $currentSort, $defaultSort) {
        $query = array_filter([
            'category' => $cat,
            'sort'     => $currentSort !== $defaultSort ? $currentSort : null,
        ]);
        return $baseUrl . ($query ? '?' . http_build_query($query) : '');
    };
    $chipBase   = 'shrink-0 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold border transition-colors whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';
    $chipActive = 'bg-primary-dark border-primary-dark text-white';
    $chipIdle   = 'bg-surface border-border-subtle text-text-main hover:border-text-main/40';
@endphp

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
    @if (count($categories) > 0)
        <nav aria-label="Filter by category" class="flex items-center gap-2 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <a href="{{ $chipUrl(null) }}"
               @if ($selectedCategory === null) aria-current="page" @endif
               class="{{ $chipBase }} {{ $selectedCategory === null ? $chipActive : $chipIdle }}">
                All
                <span class="{{ $selectedCategory === null ? 'text-white/70' : 'text-text-muted' }}">{{ $total }}</span>
            </a>

            @foreach ($categories as $name => $count)
                <a href="{{ $chipUrl($name) }}"
                   @if ($selectedCategory === $name) aria-current="page" @endif
                   class="{{ $chipBase }} {{ $selectedCategory === $name ? $chipActive : $chipIdle }}">
                    {{ $name }}
                    <span class="{{ $selectedCategory === $name ? 'text-white/70' : 'text-text-muted' }}">{{ $count }}</span>
                </a>
            @endforeach
        </nav>
    @else
        <div></div>
    @endif

    <form method="GET" action="{{ $baseUrl }}" class="shrink-0 flex items-center gap-2 self-end sm:self-auto">
        @if ($selectedCategory !== null)
            <input type="hidden" name="category" value="{{ $selectedCategory }}">
        @endif

        <label for="promo-sort" class="text-xs font-medium text-text-muted">Sort by</label>
        <select id="promo-sort" name="sort" onchange="this.form.submit()"
                class="bg-surface border border-border-subtle rounded-xl pl-3 pr-8 py-1.5 text-xs font-semibold text-text-main cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
            @foreach ($sorts as $value => $label)
                <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <noscript>
            <button type="submit" class="px-3 py-1.5 text-xs font-bold bg-primary text-white rounded-xl">Apply</button>
        </noscript>
    </form>
</div>