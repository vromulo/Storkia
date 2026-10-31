export default function categoryPageManager(config = {}) {
    return {
        products: config.products || [],
        subcategories: config.subcategories || [],
        selectedSubcategories: config.initialSubcategory && config.initialSubcategory !== 'All'
            ? [config.initialSubcategory]
            : ['All'],

        minAllowed: Number(config.minPrice ?? 0),
        maxAllowed: Number(config.maxPrice ?? 5000),
        minPrice: Number(config.minPrice ?? 0),
        maxPrice: Number(config.maxPrice ?? 5000),

        visibleLimit: 24,
        batchIncrement: 16,
        isFilterModalOpen: false,
        isLoadingMore: false,

        get minPercent() {
            if (this.maxAllowed <= this.minAllowed) return 0;
            return Math.min(100, Math.max(0, ((this.minPrice - this.minAllowed) / (this.maxAllowed - this.minAllowed)) * 100));
        },

        get maxPercent() {
            if (this.maxAllowed <= this.minAllowed) return 100;
            return Math.min(100, Math.max(0, ((this.maxPrice - this.minAllowed) / (this.maxAllowed - this.minAllowed)) * 100));
        },

        toggleSubcategory(subName) {
            if (subName === 'All') {
                this.selectedSubcategories = ['All'];
                this.resetPagination();
                return;
            }

            let list = this.selectedSubcategories.filter(s => s !== 'All');

            if (list.includes(subName)) {
                list = list.filter(s => s !== subName);
            } else {
                list.push(subName);
            }

            if (list.length === 0) {
                list = ['All'];
            }

            this.selectedSubcategories = list;
            this.resetPagination();
        },

        isSubcategoryActive(subName) {
            return this.selectedSubcategories.includes(subName);
        },

        syncMinSlider() {
            this.minPrice = Math.min(Number(this.minPrice), Number(this.maxPrice));
            this.resetPagination();
        },

        syncMaxSlider() {
            this.maxPrice = Math.max(Number(this.maxPrice), Number(this.minPrice));
            this.resetPagination();
        },

        syncMinInput() {
            let val = parseFloat(this.minPrice);
            if (isNaN(val) || val < this.minAllowed) val = this.minAllowed;
            if (val > this.maxPrice) val = this.maxPrice;
            this.minPrice = Math.round(val);
            this.resetPagination();
        },

        syncMaxInput() {
            let val = parseFloat(this.maxPrice);
            if (isNaN(val) || val > this.maxAllowed) val = this.maxAllowed;
            if (val < this.minPrice) val = this.minPrice;
            this.maxPrice = Math.round(val);
            this.resetPagination();
        },

        resetFilters() {
            this.selectedSubcategories = ['All'];
            this.minPrice = this.minAllowed;
            this.maxPrice = this.maxAllowed;
            this.resetPagination();
        },

        resetPagination() {
            this.visibleLimit = 24;
        },

        showMore() {
            this.visibleLimit += this.batchIncrement;
        },

        // Infinite scroll: same sentinel pattern as buyer/home.blade.php
        initBatchScroll() {
            this.$nextTick(() => {
                const sentinel = this.$refs.homeScrollSentinel;
                if (!sentinel || typeof IntersectionObserver === 'undefined') return;

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting && this.hasMore && !this.isLoadingMore) {
                            this.isLoadingMore = true;
                            setTimeout(() => {
                                this.showMore();
                                this.isLoadingMore = false;
                                // If the sentinel is still in range after the new batch renders
                                // (tall screens), re-observing fires the next batch.
                                this.$nextTick(() => {
                                    observer.unobserve(sentinel);
                                    observer.observe(sentinel);
                                });
                            }, 200);
                        }
                    });
                }, { rootMargin: '180px' });

                observer.observe(sentinel);
            });
        },

        get filteredProductIds() {
            const isAll = this.selectedSubcategories.includes('All');
            const min = Number(this.minPrice);
            const max = Number(this.maxPrice);

            const ids = [];
            for (let i = 0; i < this.products.length; i++) {
                const item = this.products[i];
                const matchSub = isAll || this.selectedSubcategories.includes(item.subcategory);
                const matchPrice = item.price >= min && item.price <= max;
                if (matchSub && matchPrice) {
                    ids.push(item.id);
                }
            }
            return ids;
        },

        get visibleProductIdsMap() {
            const visibleSlice = this.filteredProductIds.slice(0, this.visibleLimit);
            const map = {};
            for (let i = 0; i < visibleSlice.length; i++) {
                map[visibleSlice[i]] = true;
            }
            return map;
        },

        isProductVisible(productId) {
            return Boolean(this.visibleProductIdsMap[productId]);
        },

        get totalFilteredCount() {
            return this.filteredProductIds.length;
        },

        get currentVisibleCount() {
            return Math.min(this.visibleLimit, this.totalFilteredCount);
        },

        get hasMore() {
            return this.currentVisibleCount < this.totalFilteredCount;
        },

        get hasActiveFilters() {
            const hasSubFilter = !this.selectedSubcategories.includes('All');
            const hasPriceFilter = this.minPrice > this.minAllowed || this.maxPrice < this.maxAllowed;
            return hasSubFilter || hasPriceFilter;
        }
    };
}