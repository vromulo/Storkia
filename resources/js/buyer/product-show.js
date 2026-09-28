window.productApp = function(initialImage, basePrice, discount, maxStock) {
    return {
        defaultImage: initialImage,
        mainImage: initialImage,
        imageModalOpen: false,

        basePrice: basePrice,
        currentPrice: basePrice,
        discount: discount,
        maxStock: maxStock,
        quantity: 1,
        
        selectedMain: null,
        selectedSub: null,

        setMainImage(url) {
            if(url) this.mainImage = url;
        },

        get discountedPrice() {
            if (this.discount > 0) {
                return this.currentPrice - (this.currentPrice * (this.discount / 100));
            }
            return this.currentPrice;
        },

        formatMoney(amount) {
            return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
}