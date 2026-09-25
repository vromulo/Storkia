export default function productTableRow(config) {
    return {
        viewModal: false,
        editModal: false,
        menuOpen: false,
        selectedMain: null,
        selectedSub: null,
        basePrice: config.basePrice || 0,
        discountPercent: config.discountPercent || 0,
        totalStockCount: config.totalStock || 0,
        activeStock: config.totalStock || 0,
        defaultImage: config.defaultImage || '',
        activeImage: config.defaultImage || '',
        activePrice: config.basePrice || 0,
        get editDiscountedPrice() {
            let p = this.basePrice - (this.basePrice * (this.discountPercent / 100));
            return p > 0 ? p.toFixed(2) : 0;
        }
    };
}