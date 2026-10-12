export default function cartPageManager(initialItems = []) {
    return {
        items: initialItems,

        get selectedItems() {
            return this.items.filter(i => i.selected);
        },

        get selectedCount() {
            return this.selectedItems.length;
        },

        get isAllSelected() {
            return this.items.length > 0 && this.items.every(i => i.selected);
        },

        isStoreSelected(storeName) {
            const storeItems = this.items.filter(i => i.store === storeName);
            return storeItems.length > 0 && storeItems.every(i => i.selected);
        },

        toggleSelectAll(checked) {
            this.items.forEach(i => i.selected = checked);
        },

        toggleStore(storeName, checked) {
            this.items.forEach(i => {
                if (i.store === storeName) {
                    i.selected = checked;
                }
            });
        },

        get retailTotal() {
            return this.selectedItems.reduce((acc, i) => acc + (i.originalPrice * i.quantity), 0);
        },

        get estimatedTotal() {
            return this.selectedItems.reduce((acc, i) => acc + (i.discountedPrice * i.quantity), 0);
        },

        get savingsTotal() {
            return Math.max(0, this.retailTotal - this.estimatedTotal);
        },

        async syncDatabase(cartId, quantity, index) {
            if (this.items[index].isSyncing) return;
            this.items[index].isSyncing = true;

            try {
                const updateUrl = `/cart/${cartId}`;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || document.querySelector('input[name="_token"]')?.value;

                const res = await fetch(updateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        _method: 'PATCH',
                        quantity: parseInt(quantity)
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    window.dispatchEvent(new CustomEvent('cart-updated', {
                        detail: { id: data.id, user_id: data.user_id, quantity: data.quantity }
                    }));
                }
            } catch (e) {
                console.error('Failed to sync cart quantity:', e);
            } finally {
                this.items[index].isSyncing = false;
            }
        },

        increment(idx) {
            const item = this.items[idx];
            if (item.quantity < item.maxStock) {
                item.quantity++;
                this.syncDatabase(item.id, item.quantity, idx);
            }
        },

        decrement(idx) {
            const item = this.items[idx];
            if (item.quantity > 1) {
                item.quantity--;
                this.syncDatabase(item.id, item.quantity, idx);
            }
        }
    };
}