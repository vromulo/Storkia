export default function productTableRow(config = {}) {
    return {
        basePrice: Number(config.basePrice || 0),
        discountPercent: Number(config.discountPercent || 0),
        totalStockCount: Number(config.totalStock || 0),
        defaultImage: config.defaultImage || '',
        galleryImages: Array.isArray(config.galleryImages) ? config.galleryImages : [],
        activeGalleryIndex: 0,
        isViewingVariantImage: false,
        menuOpen: false,
        viewModal: false,
        editModal: false,
        activePrice: Number(config.basePrice || 0),
        activeStock: Number(config.totalStock || 0),
        activeImage: config.defaultImage || '',
        selectedMain: null,
        selectedSub: null,
        showAllVariantRows: false,
        showAllSpecs: false,
        descExpanded: false,
        selectGalleryPic(idx, url) {
            this.activeGalleryIndex = idx;
            this.activeImage = url;
            this.isViewingVariantImage = false;
        },
        selectVariantPic(url) {
            if (!url) return;
            this.activeImage = url;
            this.isViewingVariantImage = true;
        },
        resetGalleryPic() {
            this.activeGalleryIndex = 0;
            this.activeImage = this.defaultImage;
            this.isViewingVariantImage = false;
        },
        get editDiscountedPrice() {
            const p = this.basePrice - (this.basePrice * (this.discountPercent / 100));
            return (p > 0 ? p : 0).toFixed(2);
        }
    };
}