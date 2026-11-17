@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-4 py-3 select-none">
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-surface-subtle border border-border-subtle text-text-muted/45 text-xs font-bold cursor-not-allowed">
                &larr; {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               rel="prev"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-surface hover:bg-brand-light/40 border border-border-subtle hover:border-primary text-primary-dark hover:text-primary text-xs font-bold shadow-2xs transition-all cursor-pointer">
                &larr; {!! __('pagination.previous') !!}
            </a>
        @endif

        <span class="px-3.5 py-1.5 rounded-xl bg-brand-light/35 border border-primary/25 text-xs font-extrabold text-primary-dark">
            Page {{ $paginator->currentPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               rel="next"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-surface hover:bg-brand-light/40 border border-border-subtle hover:border-primary text-primary-dark hover:text-primary text-xs font-bold shadow-2xs transition-all cursor-pointer">
                {!! __('pagination.next') !!} &rarr;
            </a>
        @else
            <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-surface-subtle border border-border-subtle text-text-muted/45 text-xs font-bold cursor-not-allowed">
                {!! __('pagination.next') !!} &rarr;
            </span>
        @endif
    </nav>
@endif