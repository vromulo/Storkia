export default function variantManager() {
    return {
        variantTitle: '',
        subVariantTitle: '',
        variants: [{ id: Date.now(), name: '', subs: [], imagePreview: null, stock: '', isDragging: false }],
        isCopied: false,
        
        get priceDependsOn() {
            return this.subVariantTitle.trim() !== '' ? 'sub' : 'main';
        },
        get totalStock() {
            let total = 0;
            if (this.priceDependsOn === 'main') {
                this.variants.forEach(v => {
                    const val = parseInt(v.stock, 10);
                    if (!isNaN(val) && val > 0) total += val;
                });
            } else {
                this.variants.forEach(v => {
                    v.subs.forEach(s => {
                        const val = parseInt(s.stock, 10);
                        if (!isNaN(val) && val > 0) total += val;
                    });
                });
            }
            return total;
        },
        get duplicateVariants() {
            const names = this.variants.map(v => v.name.trim().toLowerCase()).filter(n => n !== '');
            return names.filter((item, index) => names.indexOf(item) !== index);
        },
        isSubVariantDuplicate(variant, subName) {
            if (!subName.trim()) return false;
            const names = variant.subs.map(s => s.name.trim().toLowerCase());
            const count = names.filter(n => n === subName.trim().toLowerCase()).length;
            return count > 1;
        },
        get hasValidationErrors() {
            if (this.duplicateVariants.length > 0) return true;
            for (const v of this.variants) {
                const subNames = v.subs.map(s => s.name.trim().toLowerCase()).filter(n => n !== '');
                const hasDupes = subNames.filter((item, index) => subNames.indexOf(item) !== index).length > 0;
                if (hasDupes) return true;
            }
            return false;
        },
        
        addMainVariant() {
            this.variants.push({ id: Date.now(), name: '', subs: [], imagePreview: null, stock: '', isDragging: false });
            this.isCopied = false;
        },
        removeMainVariant(id) {
            this.variants = this.variants.filter(v => v.id !== id);
        },
        addSubVariant(vId) {
            const variant = this.variants.find(v => v.id === vId);
            if (variant && variant.name.trim() !== '') {
                variant.subs.push({ id: Date.now(), name: '', price: '', weight: '', stock: '', isCopied: false });
            }
        },
        removeSubVariant(vId, sId) {
            const variant = this.variants.find(v => v.id === vId);
            if (variant) variant.subs = variant.subs.filter(s => s.id !== sId);
        },
        copyFirstSubValues(vId, sIndex) {
            const variant = this.variants.find(v => v.id === vId);
            if (variant && variant.subs.length > 0 && variant.subs[0]) {
                const first = variant.subs[0];
                variant.subs[sIndex].price = first.price;
                variant.subs[sIndex].weight = first.weight;
                variant.subs[sIndex].stock = first.stock;
                variant.subs[sIndex].isCopied = true;
            }
        },
        handleVariantFileProcess(file, variant, inputEl) {
            if (!file) return;
            if (!file.type || !file.type.startsWith('image/')) {
                alert('Invalid file format. Only image files (PNG, JPG, WEBP, GIF, SVG) are allowed! Documents and PDFs are rejected.');
                if (inputEl) inputEl.value = '';
                variant.imagePreview = null;
                return;
            }
            if (inputEl && inputEl.files[0] !== file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                inputEl.files = dt.files;
            }
            const reader = new FileReader();
            reader.onload = (e) => variant.imagePreview = e.target.result;
            reader.readAsDataURL(file);
        },
        handleVariantDrop(event, vId) {
            const variant = this.variants.find(v => v.id === vId);
            if (!variant) return;
            variant.isDragging = false;
            const file = event.dataTransfer?.files[0];
            const inputEl = document.getElementById(`variant_picture_input_${vId}`);
            this.handleVariantFileProcess(file, variant, inputEl);
        },
        handleVariantImage(event, vId) {
            const variant = this.variants.find(v => v.id === vId);
            if (!variant) return;
            const file = event.target.files[0];
            this.handleVariantFileProcess(file, variant, event.target);
        },
        copySubVariants() {
            if (this.variants.length <= 1) return;
            const firstVariantSubs = this.variants[0].subs;
            if (firstVariantSubs.length === 0) {
                alert("Please add at least one sub-variant to the first item before copying.");
                return;
            }
            for (let i = 1; i < this.variants.length; i++) {
                if (this.variants[i].name.trim() !== '') {
                    this.variants[i].subs = firstVariantSubs.map(s => ({
                        id: Date.now() + Math.random(),
                        name: s.name,
                        price: s.price,
                        weight: s.weight,
                        stock: s.stock,
                        isCopied: false
                    }));
                }
            }
            this.isCopied = true;
        }
    };
}