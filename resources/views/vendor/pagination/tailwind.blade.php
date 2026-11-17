@if ($paginator->hasPages())
    @php
        $currentPage = (int) $paginator->currentPage();
        $lastPage = (int) $paginator->lastPage();
        $pageUrls = [];
        for ($p = 1; $p <= $lastPage; $p++) {
            $pageUrls[$p] = $paginator->url($p);
        }
    @endphp
    <nav role="navigation"
         aria-label="{{ __('Pagination Navigation') }}"
         x-data="{
            pickerOpen: false,
            pageSearch: '',
            currentPage: {{ $currentPage }},
            lastPage: {{ $lastPage }},
            pageUrls: @js($pageUrls),
            get filteredPages() {
                const q = String(this.pageSearch || '').trim();
                const list = [];
                for (let i = 1; i <= this.lastPage; i++) {
                    if (!q || String(i).includes(q)) {
                        list.push(i);
                    }
                }
                return list;
            },
            goToPage(page) {
                const url = this.pageUrls[page];
                if (url) {
                    this.pickerOpen = false;
                    window.location.href = url;
                }
            }
         }"
         class="flex flex-col sm:flex-row items-center justify-between gap-4 py-3 select-none">

        <div class="flex flex-wrap items-center gap-2 text-xs text-text-muted">
            @if ($paginator->firstItem())
                <span class="px-3.5 py-1.5 rounded-xl bg-surface-subtle border border-border-subtle font-medium">
                    Showing
                    <strong class="font-extrabold text-primary-dark">{{ number_format($paginator->firstItem()) }}</strong>
                    to
                    <strong class="font-extrabold text-primary-dark">{{ number_format($paginator->lastItem()) }}</strong>
                    of
                    <strong class="font-extrabold text-primary">{{ number_format($paginator->total()) }}</strong>
                    results
                </span>
            @else
                <span class="px-3.5 py-1.5 rounded-xl bg-surface-subtle border border-border-subtle font-medium">
                    {{ number_format($paginator->count()) }} results
                </span>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true"
                      class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-surface-subtle border border-border-subtle text-text-muted/45 text-xs font-bold cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span class="hidden sm:inline">Prev</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                   rel="prev"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-surface hover:bg-brand-light/40 border border-border-subtle hover:border-primary text-primary-dark hover:text-primary text-xs font-bold shadow-2xs transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span class="hidden sm:inline">Prev</span>
                </a>
            @endif

            <div class="hidden md:flex items-center gap-1">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-2.5 py-2 text-xs font-extrabold text-text-muted">
                            {{ $element }}
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page">
                                    <span class="inline-flex items-center justify-center min-w-[36px] h-9 px-3 rounded-xl bg-gradient-to-br from-primary to-primary-dark text-white text-xs font-extrabold shadow-xs border border-primary">
                                        {{ $page }}
                                    </span>
                                </span>
                            @else
                                <a href="{{ $url }}"
                                   aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                   class="inline-flex items-center justify-center min-w-[36px] h-9 px-3 rounded-xl bg-surface hover:bg-brand-light/35 text-text-main hover:text-primary-dark border border-border-subtle hover:border-primary text-xs font-bold transition-all cursor-pointer">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($lastPage > 1)
                <div class="relative" @click.away="pickerOpen = false">
                    <button type="button"
                            @click="pickerOpen = !pickerOpen; if(pickerOpen) $nextTick(() => $refs.pageFilterInput?.focus())"
                            class="inline-flex items-center gap-2 h-9 px-3.5 rounded-xl bg-brand-light/35 hover:bg-brand-light/60 border border-primary/30 hover:border-primary text-primary-dark text-xs font-extrabold transition-all cursor-pointer shadow-2xs">
                        <span>Page {{ $currentPage }} / {{ $lastPage }}</span>
                        <svg class="w-3.5 h-3.5 text-primary transition-transform duration-200"
                             :class="pickerOpen ? 'rotate-180' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="pickerOpen"
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                         class="absolute right-0 bottom-full mb-2 w-48 bg-surface border border-border-subtle rounded-2xl shadow-2xl p-2 z-50">
                        <div class="px-2 py-1.5 border-b border-border-subtle mb-1.5 flex items-center justify-between">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-primary-dark">Jump to Page</span>
                            <span class="text-[10px] font-mono font-bold text-primary">{{ $lastPage }} total</span>
                        </div>
                        @if ($lastPage > 6)
                            <div class="mb-1.5 px-1">
                                <input x-ref="pageFilterInput"
                                       type="text"
                                       x-model="pageSearch"
                                       placeholder="Type page #..."
                                       class="w-full px-2.5 py-1.5 rounded-lg border border-border-subtle bg-surface-subtle text-xs font-mono font-bold text-text-main outline-none focus:border-primary">
                            </div>
                        @endif
                        <div class="max-h-48 overflow-y-auto custom-scrollbar space-y-1 pr-0.5">
                            <template x-for="p in filteredPages" :key="'pg_pick_' + p">
                                <button type="button"
                                        @click="goToPage(p)"
                                        :class="p === currentPage
                                            ? 'bg-primary text-white font-extrabold shadow-2xs'
                                            : 'bg-surface hover:bg-brand-light/35 text-text-main hover:text-primary-dark font-semibold'"
                                        class="w-full px-3 py-1.5 rounded-xl text-xs flex items-center justify-between transition-colors cursor-pointer">
                                    <span x-text="'Page ' + p"></span>
                                    <span x-show="p === currentPage" class="text-[10px] font-extrabold">&#10003;</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                   rel="next"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-surface hover:bg-brand-light/40 border border-border-subtle hover:border-primary text-primary-dark hover:text-primary text-xs font-bold shadow-2xs transition-all cursor-pointer">
                    <span class="hidden sm:inline">Next</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @else
                <span aria-disabled="true"
                      class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-surface-subtle border border-border-subtle text-text-muted/45 text-xs font-bold cursor-not-allowed">
                    <span class="hidden sm:inline">Next</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif