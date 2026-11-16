export default function variantManager(initialState = null) {
    let uidSeed = 1;
    const makeId = () => `${Date.now()}_${uidSeed++}`;

    const buildInitialVariants = () => {
        if (initialState && typeof initialState === 'object' && initialState.variantNames && typeof initialState.variantNames === 'object') {
            const namesMap = initialState.variantNames || {};
            const pricesMap = initialState.variantPrices || {};
            const weightsMap = initialState.variantWeights || {};
            const stocksMap = initialState.variantStocks || {};
            const base64Map = initialState.variantPicturesBase64 || {};
            const subNamesMap = initialState.subVariantNames || {};
            const subPricesMap = initialState.subVariantPrices || {};
            const subWeightsMap = initialState.subVariantWeights || {};
            const subStocksMap = initialState.subVariantStocks || {};
            const hasSubTitle = String(initialState.subVariantTitle || '').trim() !== '';

            const keys = Object.keys(namesMap);
            if (keys.length > 0) {
                return keys.map((vKey) => {
                    const b64 = typeof base64Map[vKey] === 'string' && base64Map[vKey].startsWith('data:image/')
                        ? base64Map[vKey]
                        : '';
                    const rawSubs = subNamesMap[vKey] && typeof subNamesMap[vKey] === 'object'
                        ? subNamesMap[vKey]
                        : {};
                    const subKeys = Object.keys(rawSubs);
                    const subsList = subKeys.map((sKey) => ({
                        id: String(sKey),
                        name: String(rawSubs[sKey] ?? ''),
                        price: subPricesMap?.[vKey]?.[sKey] ?? '',
                        weight: subWeightsMap?.[vKey]?.[sKey] ?? '',
                        stock: subStocksMap?.[vKey]?.[sKey] ?? '',
                        isCopied: false,
                        promptState: 'active'
                    }));

                    if (hasSubTitle && subsList.length === 0) {
                        subsList.push({
                            id: makeId(),
                            name: '',
                            price: '',
                            weight: '',
                            stock: '',
                            isCopied: false,
                            promptState: 'active'
                        });
                    }

                    return {
                        id: String(vKey),
                        name: String(namesMap[vKey] ?? ''),
                        price: pricesMap[vKey] ?? '',
                        weight: weightsMap[vKey] ?? '',
                        stock: stocksMap[vKey] ?? '',
                        subs: subsList,
                        imagePreview: b64 || null,
                        imageBase64: b64,
                        imageError: '',
                        isProcessing: false,
                        isDragging: false,
                        isCopied: false,
                        promptState: 'active',
                        isSubsCopied: false,
                        subPromptState: 'active'
                    };
                });
            }
        }

        return [
            {
                id: makeId(),
                name: '',
                price: '',
                weight: '',
                stock: '',
                subs: [],
                imagePreview: null,
                imageBase64: '',
                imageError: '',
                isProcessing: false,
                isDragging: false,
                isCopied: false,
                promptState: 'active',
                isSubsCopied: false,
                subPromptState: 'active'
            }
        ];
    };

    return {
        variantTitle: initialState?.variantTitle ? String(initialState.variantTitle) : '',
        subVariantTitle: initialState?.subVariantTitle ? String(initialState.subVariantTitle) : '',
        variants: buildInitialVariants(),
        isCopied: false,

        notifyError(msg) {
            if (!msg) return;
            if (window.StorkiaLive && typeof window.StorkiaLive.showToast === 'function') {
                window.StorkiaLive.showToast(msg, 'error');
            } else {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { title: 'Validation Error', message: msg, type: 'danger' }
                }));
            }
        },

        notifyVariantChanged() {
            window.dispatchEvent(new CustomEvent('storkia-variant-changed'));
        },

        onSubVariantTitleInput() {
            if (this.subVariantTitle.trim() !== '') {
                this.variants.forEach((v) => {
                    if (!Array.isArray(v.subs)) {
                        v.subs = [];
                    }
                    if (v.subs.length === 0) {
                        v.subs.push({
                            id: makeId(),
                            name: '',
                            price: '',
                            weight: '',
                            stock: '',
                            isCopied: false,
                            promptState: 'active'
                        });
                    }
                });
            }
            this.notifyVariantChanged();
        },

        get priceDependsOn() {
            return this.subVariantTitle.trim() !== '' ? 'sub' : 'main';
        },

        get totalStock() {
            let total = 0;
            if (this.priceDependsOn === 'main') {
                this.variants.forEach((v) => {
                    const val = parseInt(v.stock, 10);
                    if (!isNaN(val) && val > 0) total += val;
                });
            } else {
                this.variants.forEach((v) => {
                    (v.subs || []).forEach((s) => {
                        const val = parseInt(s.stock, 10);
                        if (!isNaN(val) && val > 0) total += val;
                    });
                });
            }
            return total;
        },

        get duplicateVariants() {
            const names = this.variants
                .map((v) => String(v.name || '').trim().toLowerCase())
                .filter((n) => n !== '');
            return names.filter((item, index) => names.indexOf(item) !== index);
        },

        isSubVariantDuplicate(variant, subName) {
            const clean = String(subName || '').trim().toLowerCase();
            if (!clean || !variant || !Array.isArray(variant.subs)) return false;
            const names = variant.subs.map((s) => String(s.name || '').trim().toLowerCase());
            return names.filter((n) => n === clean).length > 1;
        },

        hasFirstVariantMainValues() {
            if (!this.variants || this.variants.length === 0 || !this.variants[0]) {
                return false;
            }
            const first = this.variants[0];
            return (
                String(first.price ?? '').trim() !== '' ||
                String(first.weight ?? '').trim() !== '' ||
                String(first.stock ?? '').trim() !== ''
            );
        },

        hasFirstVariantSubValues() {
            if (!this.variants || this.variants.length === 0 || !this.variants[0]) {
                return false;
            }
            const subs = this.variants[0].subs;
            if (!Array.isArray(subs) || subs.length === 0) {
                return false;
            }
            return subs.some((s) =>
                s && (
                    String(s.name ?? '').trim() !== '' ||
                    String(s.price ?? '').trim() !== '' ||
                    String(s.weight ?? '').trim() !== '' ||
                    String(s.stock ?? '').trim() !== ''
                )
            );
        },

        hasFirstSubValues(variant) {
            if (!variant || !Array.isArray(variant.subs) || variant.subs.length === 0 || !variant.subs[0]) {
                return false;
            }
            const firstSub = variant.subs[0];
            return (
                String(firstSub.price ?? '').trim() !== '' ||
                String(firstSub.weight ?? '').trim() !== '' ||
                String(firstSub.stock ?? '').trim() !== ''
            );
        },

        copyFirstVariantValues(vId) {
            if (!this.variants || this.variants.length === 0 || !this.variants[0]) return;
            const target = this.variants.find((v) => v.id === vId);
            if (!target) return;
            const first = this.variants[0];
            target.price = first.price;
            target.weight = first.weight;
            target.stock = first.stock;
            target.isCopied = true;
            target.promptState = 'copied';
            this.notifyVariantChanged();
        },

        copyFirstVariantValuesToAll() {
            if (!this.variants || this.variants.length <= 1 || !this.variants[0]) return;
            const first = this.variants[0];
            for (let i = 1; i < this.variants.length; i++) {
                this.variants[i].price = first.price;
                this.variants[i].weight = first.weight;
                this.variants[i].stock = first.stock;
                this.variants[i].isCopied = true;
                this.variants[i].promptState = 'copied';
            }
            this.notifyVariantChanged();
        },

        copySubVariantsToVariant(vId) {
            if (!this.variants || this.variants.length === 0 || !this.variants[0]) return;
            const firstVariantSubs = this.variants[0].subs || [];
            if (firstVariantSubs.length === 0) {
                this.notifyError('Please add at least one sub-variant to the 1st variant before copying.');
                return;
            }
            const target = this.variants.find((v) => v.id === vId);
            if (!target) return;

            target.subs = firstVariantSubs.map((s) => ({
                id: makeId(),
                name: s.name,
                price: s.price,
                weight: s.weight,
                stock: s.stock,
                isCopied: true,
                promptState: 'copied'
            }));
            target.isSubsCopied = true;
            target.subPromptState = 'copied';
            this.notifyVariantChanged();
        },

        copySubVariants() {
            if (this.variants.length <= 1) return;
            const firstVariantSubs = this.variants[0].subs || [];
            if (firstVariantSubs.length === 0) {
                this.notifyError('Please add at least one sub-variant to the 1st variant before copying.');
                return;
            }
            for (let i = 1; i < this.variants.length; i++) {
                this.variants[i].subs = firstVariantSubs.map((s) => ({
                    id: makeId(),
                    name: s.name,
                    price: s.price,
                    weight: s.weight,
                    stock: s.stock,
                    isCopied: true,
                    promptState: 'copied'
                }));
                this.variants[i].isSubsCopied = true;
                this.variants[i].subPromptState = 'copied';
            }
            this.isCopied = true;
            this.notifyVariantChanged();
        },

        copyFirstSubValues(vId, sIndex) {
            const variant = this.variants.find((v) => v.id === vId);
            if (variant && variant.subs.length > 0 && variant.subs[0] && variant.subs[sIndex]) {
                const first = variant.subs[0];
                variant.subs[sIndex].price = first.price;
                variant.subs[sIndex].weight = first.weight;
                variant.subs[sIndex].stock = first.stock;
                variant.subs[sIndex].isCopied = true;
                variant.subs[sIndex].promptState = 'copied';
                this.notifyVariantChanged();
            }
        },

        get variantIssues() {
            const issues = [];
            if (!this.variantTitle || this.variantTitle.trim() === '') {
                issues.push('Enter a Title for the Variants (e.g., Color, Style, Pack Size).');
            }

            if (this.variants.length === 0) {
                issues.push('Add at least 1 product variant.');
            }

            if (this.duplicateVariants.length > 0) {
                issues.push(`Duplicate variant name "${this.duplicateVariants[0]}" is not allowed.`);
            }

            const isSubMode = this.priceDependsOn === 'sub';

            this.variants.forEach((v, idx) => {
                const label = (v.name && v.name.trim() !== '') ? `"${v.name.trim()}"` : `Variant #${idx + 1}`;

                if (!v.name || v.name.trim() === '') {
                    issues.push(`${label}: Variant name is required.`);
                }

                if (v.isProcessing) {
                    issues.push(`${label}: Variant photo is still optimizing, please wait a moment.`);
                } else if (!v.imagePreview && !v.imageBase64) {
                    issues.push(`${label}: Upload a dedicated variant photo.`);
                }

                if (isSubMode) {
                    const subs = Array.isArray(v.subs) ? v.subs : [];
                    if (subs.length === 0) {
                        issues.push(`${label}: Add at least 1 ${this.subVariantTitle.trim() || 'sub-variant'}.`);
                    }
                    subs.forEach((s, sIdx) => {
                        const subLabel = (s.name && s.name.trim() !== '')
                            ? `${label} (${s.name.trim()})`
                            : `${label} Sub-Variant #${sIdx + 1}`;

                        if (!s.name || s.name.trim() === '') {
                            issues.push(`${subLabel}: Sub-variant name is required.`);
                        } else if (this.isSubVariantDuplicate(v, s.name)) {
                            issues.push(`${subLabel}: Sub-variant name must be unique within ${label}.`);
                        }

                        const p = parseFloat(s.price);
                        if (isNaN(p) || p < 20) {
                            issues.push(`${subLabel}: Price is required (minimum ₱20.00).`);
                        }

                        const w = parseFloat(s.weight);
                        if (isNaN(w) || w < 0.01) {
                            issues.push(`${subLabel}: Weight in KG is required (minimum 0.01 KG).`);
                        }

                        const st = parseInt(s.stock, 10);
                        if (s.stock === '' || isNaN(st) || st < 0) {
                            issues.push(`${subLabel}: Stock quantity is required.`);
                        }
                    });
                } else {
                    const p = parseFloat(v.price);
                    if (isNaN(p) || p < 20) {
                        issues.push(`${label}: Price is required (minimum ₱20.00).`);
                    }

                    const w = parseFloat(v.weight);
                    if (isNaN(w) || w < 0.01) {
                        issues.push(`${label}: Weight in KG is required (minimum 0.01 KG).`);
                    }

                    const st = parseInt(v.stock, 10);
                    if (v.stock === '' || isNaN(st) || st < 0) {
                        issues.push(`${label}: Stock quantity is required.`);
                    }
                }
            });

            return issues;
        },

        get hasValidationErrors() {
            return this.variantIssues.length > 0;
        },

        addMainVariant() {
            const isSubMode = this.priceDependsOn === 'sub';
            this.variants.push({
                id: makeId(),
                name: '',
                price: '',
                weight: '',
                stock: '',
                subs: isSubMode
                    ? [{ id: makeId(), name: '', price: '', weight: '', stock: '', isCopied: false, promptState: 'active' }]
                    : [],
                imagePreview: null,
                imageBase64: '',
                imageError: '',
                isProcessing: false,
                isDragging: false,
                isCopied: false,
                promptState: 'active',
                isSubsCopied: false,
                subPromptState: 'active'
            });
            this.isCopied = false;
            this.notifyVariantChanged();
        },

        removeMainVariant(id) {
            if (this.variants.length <= 1) {
                this.notifyError('A product must have at least 1 variant.');
                return;
            }
            this.variants = this.variants.filter((v) => v.id !== id);
            this.notifyVariantChanged();
        },

        addSubVariant(vId) {
            const variant = this.variants.find((v) => v.id === vId);
            if (!variant) return;
            if (!variant.name || variant.name.trim() === '') {
                this.notifyError('Please enter the main variant name first before adding sub-variants.');
                return;
            }
            variant.subs.push({
                id: makeId(),
                name: '',
                price: '',
                weight: '',
                stock: '',
                isCopied: false,
                promptState: 'active'
            });
            this.notifyVariantChanged();
        },

        removeSubVariant(vId, sId) {
            const variant = this.variants.find((v) => v.id === vId);
            if (!variant) return;
            if (this.priceDependsOn === 'sub' && variant.subs.length <= 1) {
                this.notifyError(`Variant "${variant.name || 'Item'}" must keep at least 1 sub-variant while Sub-Variant Title is active.`);
                return;
            }
            variant.subs = variant.subs.filter((s) => s.id !== sId);
            this.notifyVariantChanged();
        },

        compressToWebpDataUrl(file) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const dataUrl = e.target.result;
                    const img = new Image();
                    img.onload = () => {
                        try {
                            const maxDim = 1600;
                            let width = img.width;
                            let height = img.height;
                            if (width > maxDim || height > maxDim) {
                                if (width > height) {
                                    height = Math.round((height * maxDim) / width);
                                    width = maxDim;
                                } else {
                                    width = Math.round((width * maxDim) / height);
                                    height = maxDim;
                                }
                            }
                            const canvas = document.createElement('canvas');
                            canvas.width = width;
                            canvas.height = height;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, width, height);
                            const webpUrl = canvas.toDataURL('image/webp', 0.84);
                            resolve(webpUrl && webpUrl.startsWith('data:image/') ? webpUrl : dataUrl);
                        } catch (_) {
                            resolve(dataUrl);
                        }
                    };
                    img.onerror = () => resolve(dataUrl);
                    img.src = dataUrl;
                };
                reader.onerror = () => resolve('');
                reader.readAsDataURL(file);
            });
        },

        async handleVariantFileProcess(file, variant, inputEl) {
            if (!file) return;
            variant.imageError = '';

            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!file.type || !allowedTypes.includes(file.type.toLowerCase())) {
                const msg = 'Invalid file format. Only JPG, PNG, and WEBP images are allowed for variant photos.';
                variant.imageError = msg;
                this.notifyError(msg);
                if (inputEl) inputEl.value = '';
                variant.imagePreview = null;
                variant.imageBase64 = '';
                this.notifyVariantChanged();
                return;
            }

            const maxBytes = 5 * 1024 * 1024;
            if (file.size > maxBytes) {
                const msg = `"${file.name}" exceeds the 5MB limit for variant photos.`;
                variant.imageError = msg;
                this.notifyError(msg);
                if (inputEl) inputEl.value = '';
                variant.imagePreview = null;
                variant.imageBase64 = '';
                this.notifyVariantChanged();
                return;
            }

            variant.isProcessing = true;
            this.notifyVariantChanged();

            const dataUri = await this.compressToWebpDataUrl(file);
            variant.isProcessing = false;

            if (!dataUri) {
                const msg = `Could not process "${file.name}". Please try another image.`;
                variant.imageError = msg;
                this.notifyError(msg);
                if (inputEl) inputEl.value = '';
                this.notifyVariantChanged();
                return;
            }

            variant.imagePreview = dataUri;
            variant.imageBase64 = dataUri;

            if (inputEl) {
                inputEl.value = '';
                inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                inputEl.dispatchEvent(new Event('change', { bubbles: true }));
            }

            this.notifyVariantChanged();
        },

        handleVariantDrop(event, vId) {
            const variant = this.variants.find((v) => v.id === vId);
            if (!variant) return;
            variant.isDragging = false;
            const file = event.dataTransfer?.files[0];
            const inputEl = document.getElementById(`variant_picture_input_${vId}`);
            this.handleVariantFileProcess(file, variant, inputEl);
        },

        handleVariantImage(event, vId) {
            const variant = this.variants.find((v) => v.id === vId);
            if (!variant) return;
            const file = event.target.files[0];
            this.handleVariantFileProcess(file, variant, event.target);
        }
    };
}