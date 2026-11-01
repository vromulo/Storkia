{{--
    Product grid that reveals cards in batches as the shopper scrolls, same behaviour and
    "Loaded X of Y" indicator as the home, category and search pages.

    Every card is rendered by the server; each one gets x-show="INDEX < visibleCount"
    (see the Super Deals and Trends pages). Put the cards in the slot.
--}}
@props([
    'total'    => 0,
    'pageSize' => 24,
    'noun'     => 'products',
])

<div
    x-data="{
        pageSize: {{ (int) $pageSize }},
        visibleCount: {{ (int) $pageSize }},
        total: {{ (int) $total }},
        isLoadingMore: false,
        observer: null,
        get hasMore() { return this.visibleCount < this.total; },
        startBatchScroll() {
            this.$nextTick(() => {
                const sentinel = this.$refs.batchSentinel;
                if (!sentinel || typeof IntersectionObserver === 'undefined') {
                    this.visibleCount = this.total;
                    return;
                }
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting || !this.hasMore || this.isLoadingMore) return;
                        this.isLoadingMore = true;
                        setTimeout(() => {
                            this.visibleCount = Math.min(this.total, this.visibleCount + this.pageSize);
                            this.isLoadingMore = false;
                            // Re-check: on tall screens the sentinel can still be in view after a batch.
                            this.observer.unobserve(sentinel);
                            this.observer.observe(sentinel);
                        }, 200);
                    });
                }, { rootMargin: '400px 0px' });
                this.observer.observe(sentinel);
            });
        }
    }"
    x-init="startBatchScroll()"
>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-5 lg:gap-6">
        {{ $slot }}
    </div>

    <div x-ref="batchSentinel"
         x-show="hasMore"
         x-cloak
         class="py-8 flex flex-col items-center justify-center gap-2 text-xs font-bold text-text-muted">
        <div class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-primary animate-bounce"></span>
            <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.15s"></span>
            <span class="w-2 h-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.3s"></span>
        </div>
        <span x-text="'Loaded ' + visibleCount + ' of ' + total + ' {{ $noun }} — Scroll down for next batch'"></span>
    </div>
</div>