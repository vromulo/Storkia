@extends('layouts.seller', ['title' => 'Storkia - All Products'])

@section('content')
    @php
        $categoriesList = $categoriesList ?? \App\Http\Controllers\Seller\ProductController::getCategoriesList();
        $primaryCategory = $primaryCategory ?? (auth()->user()->sellerProfile->line_of_business ?? 'Pet');
        $otherCategories = $otherCategories ?? (auth()->user()->sellerProfile ? auth()->user()->sellerProfile->otherCategories()->orderBy('created_at', 'asc')->get() : collect());
        $selectedCategory = $selectedCategory ?? request()->query('category');
        $activeCategoryRecord = $otherCategories->firstWhere('category', $selectedCategory);
    @endphp

    <script>
        window.storkiaProductTableRow = function(data) {
            return {
                basePrice: Number(data.basePrice || 0),
                discountPercent: Number(data.discountPercent || 0),
                totalStockCount: Number(data.totalStock || 0),
                defaultImage: data.defaultImage || '',
                galleryImages: Array.isArray(data.galleryImages) ? data.galleryImages : [],
                activeGalleryIndex: 0,
                isViewingVariantImage: false,
                menuOpen: false,
                viewModal: false,
                editModal: false,
                activePrice: Number(data.basePrice || 0),
                activeStock: Number(data.totalStock || 0),
                activeImage: data.defaultImage || '',
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
                    return (this.basePrice - (this.basePrice * (this.discountPercent / 100))).toFixed(2);
                }
            };
        };
        window.productTableRow = window.storkiaProductTableRow;

        window.storkiaEditVariantManager = function(config) {
            let rawExisting = (config && config.existingVariants && typeof config.existingVariants === 'object') ? config.existingVariants : {};
            let normalizedExisting = {};
            let initialNewSubs = {};
            Object.keys(rawExisting).forEach(k => {
                let v = rawExisting[k] || {};
                let normalizedSubs = {};
                if (v.subs && typeof v.subs === 'object') {
                    Object.keys(v.subs).forEach(sk => {
                        let s = v.subs[sk] || {};
                        normalizedSubs[String(sk)] = {
                            name: String(s.name ?? ''),
                            price: s.price ?? '',
                            weight: s.weight ?? '',
                            stock: s.stock ?? '',
                            status: s.status ?? 'Approved',
                            archived: false
                        };
                    });
                }
                normalizedExisting[String(k)] = {
                    name: String(v.name ?? ''),
                    price: v.price ?? '',
                    weight: v.weight ?? '',
                    stock: v.stock ?? '',
                    status: v.status ?? 'Approved',
                    archived: false,
                    subs: normalizedSubs
                };
                initialNewSubs[String(k)] = [];
            });

            let initialPictures = (config && Array.isArray(config.existingPictures))
                ? config.existingPictures.slice(0, 10)
                : [];

            return {
                confirmModal: { open: false, message: '', onConfirm: () => {} },
                alertModal: { open: false, message: '' },
                existingPictures: initialPictures,
                newPictureFiles: [],
                newPicturePreviews: [],
                newPictureBase64: [],
                existingVariantBase64: {},
                existingVariantPreviews: {},
                pictureError: '',
                maxMainPictures: 10,
                existingVariants: normalizedExisting,
                newVariants: [],
                newExistingSubs: initialNewSubs,
                hasSubvariants: Boolean(config && config.hasSubvariants),
                priceDependsOn: (config && config.priceDependency) ? config.priceDependency : 'main',
                uidCounter: 0,
                isFormValid: true,
                touched: {},
                fieldErrors: {},
                get totalMainPicturesCount() {
                    return this.existingPictures.length + this.newPicturePreviews.length;
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
                promoteExistingToCover(idx) {
                    if (idx <= 0 || idx >= this.existingPictures.length) return;
                    const [pic] = this.existingPictures.splice(idx, 1);
                    this.existingPictures.unshift(pic);
                },
                moveExistingPicture(fromIdx, toIdx) {
                    if (
                        fromIdx === toIdx ||
                        fromIdx < 0 ||
                        toIdx < 0 ||
                        fromIdx >= this.existingPictures.length ||
                        toIdx >= this.existingPictures.length
                    ) {
                        return;
                    }
                    const [pic] = this.existingPictures.splice(fromIdx, 1);
                    this.existingPictures.splice(toIdx, 0, pic);
                },
                removeExistingPicture(idx) {
                    this.pictureError = '';
                    if (this.totalMainPicturesCount <= 1) {
                        this.pictureError = 'A product must keep at least 1 main picture (maximum 10). Upload another picture first before removing this one.';
                        return;
                    }
                    this.existingPictures.splice(idx, 1);
                },
                async handleEditNewPictures(event) {
                    this.pictureError = '';
                    const incoming = Array.from(event.target.files || []);
                    if (!incoming.length) return;
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                    const maxSize = 5 * 1024 * 1024;
                    for (const file of incoming) {
                        if (this.totalMainPicturesCount >= this.maxMainPictures) {
                            this.pictureError = 'Maximum of 10 main product pictures reached.';
                            break;
                        }
                        if (!allowedTypes.includes((file.type || '').toLowerCase())) {
                            this.pictureError = `"${file.name}" was skipped. Only JPG, PNG, and WEBP images are allowed.`;
                            continue;
                        }
                        if (file.size > maxSize) {
                            this.pictureError = `"${file.name}" exceeds the 5MB limit.`;
                            continue;
                        }
                        const webpDataUri = await this.compressToWebpDataUrl(file);
                        if (!webpDataUri) {
                            this.pictureError = `Could not process "${file.name}".`;
                            continue;
                        }
                        this.newPictureFiles.push({ name: file.name, size: file.size });
                        this.newPicturePreviews.push(webpDataUri);
                        this.newPictureBase64.push(webpDataUri);
                    }
                    this.syncEditPicturesInput();
                    this.validateForm(this.$refs.editForm);
                },
                removeEditNewPicture(idx) {
                    this.pictureError = '';
                    if (this.totalMainPicturesCount <= 1) {
                        this.pictureError = 'A product must have at least 1 main picture.';
                        return;
                    }
                    const prev = this.newPicturePreviews[idx];
                    if (prev && prev.startsWith('blob:')) {
                        URL.revokeObjectURL(prev);
                    }
                    this.newPictureFiles.splice(idx, 1);
                    this.newPicturePreviews.splice(idx, 1);
                    this.newPictureBase64.splice(idx, 1);
                    this.syncEditPicturesInput();
                    this.validateForm(this.$refs.editForm);
                },
                syncEditPicturesInput() {
                    const input = this.$refs.editMainPicturesInput;
                    if (input) {
                        input.value = '';
                    }
                },
                async handleExistingVariantImage(event, vIndex) {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                    if (!allowedTypes.includes((file.type || '').toLowerCase()) || file.size > 5 * 1024 * 1024) {
                        this.showAlert('Variant image must be a JPG, PNG, or WEBP file under 5MB.');
                        event.target.value = '';
                        return;
                    }
                    const b64 = await this.compressToWebpDataUrl(file);
                    if (b64) {
                        this.existingVariantBase64[String(vIndex)] = b64;
                        this.existingVariantPreviews[String(vIndex)] = b64;
                        event.target.value = '';
                    }
                    this.validateForm(this.$refs.editForm);
                },
                async handleNewVariantImage(event, nv) {
                    const file = event.target.files?.[0];
                    if (!file || !nv) return;
                    nv.imageError = '';
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                    if (!allowedTypes.includes((file.type || '').toLowerCase())) {
                        nv.imageError = 'Only JPG, PNG, and WEBP images are allowed.';
                        event.target.value = '';
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        nv.imageError = 'Image exceeds the 5MB limit.';
                        event.target.value = '';
                        return;
                    }
                    const b64 = await this.compressToWebpDataUrl(file);
                    if (b64) {
                        nv.imagePreview = b64;
                        nv.imageBase64 = b64;
                        event.target.value = '';
                        event.target.dispatchEvent(new Event('input', { bubbles: true }));
                        event.target.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    this.validateForm(this.$refs.editForm);
                },
                initForm(formElement) {
                    if(formElement) {
                        formElement.addEventListener('blur', (e) => {
                            if (e.target.name) {
                                this.touched[e.target.name] = true;
                                this.validateForm(formElement);
                            }
                        }, true);
                        this.validateForm(formElement);
                    }
                },
                validateForm(formElement) {
                    this.$nextTick(() => {
                        if (!formElement) return;
                        this.isFormValid = formElement.checkValidity();
                        let errs = {};
                        formElement.querySelectorAll('input, select, textarea').forEach(el => {
                            if (el.name && !el.disabled && !el.validity.valid) {
                                errs[el.name] = true;
                            }
                        });
                        this.fieldErrors = errs;
                    });
                },
                hasError(name) {
                    return this.touched[name] && this.fieldErrors[name];
                },
                nextUid() {
                    this.uidCounter++;
                    return Date.now() + '_' + this.uidCounter;
                },
                requestConfirm(msg, callback) {
                    this.confirmModal.message = msg;
                    this.confirmModal.onConfirm = callback;
                    this.confirmModal.open = true;
                },
                showAlert(msg) {
                    this.alertModal.message = msg;
                    this.alertModal.open = true;
                },
                isVariantArchived(vKey) {
                    let k = String(vKey);
                    return this.existingVariants[k] ? Boolean(this.existingVariants[k].archived) : false;
                },
                isSubArchived(vKey, sKey) {
                    let k = String(vKey), sk = String(sKey);
                    return (this.existingVariants[k] && this.existingVariants[k].subs && this.existingVariants[k].subs[sk])
                        ? Boolean(this.existingVariants[k].subs[sk].archived)
                        : false;
                },
                archiveExistingVariant(vKey) {
                    let k = String(vKey);
                    if (this.existingVariants[k]) this.existingVariants[k].archived = true;
                    if (this.newExistingSubs[k]) this.newExistingSubs[k] = [];
                },
                archiveExistingSubVariant(vKey, sKey) {
                    let k = String(vKey), sk = String(sKey);
                    if (this.existingVariants[k] && this.existingVariants[k].subs && this.existingVariants[k].subs[sk]) {
                        this.existingVariants[k].subs[sk].archived = true;
                    }
                },
                requestRemoveVariant(vKey) {
                    let v = this.existingVariants[vKey];
                    if (v && v.status === 'Approved') {
                        let approvedCount = Object.values(this.existingVariants).filter(x => !x.archived && x.status === 'Approved').length;
                        if (approvedCount <= 1) {
                            this.showAlert("You must have at least one approved variant. Add and wait for a new variant to be approved before removing this one.");
                            return;
                        }
                    }
                    this.requestConfirm('Are you sure you want to remove this variant?', () => { this.archiveExistingVariant(vKey); });
                },
                requestRemoveSubVariant(vKey, sKey) {
                    let v = this.existingVariants[vKey];
                    if (v && v.subs) {
                        let s = v.subs[sKey];
                        if (s && s.status === 'Approved') {
                            let approvedSubCount = Object.values(v.subs).filter(x => !x.archived && x.status === 'Approved').length;
                            if (approvedSubCount <= 1) {
                                this.showAlert("You must have at least one approved subvariant in this variant. Add and wait for a new subvariant to be approved before removing this one, or remove the entire variant.");
                                return;
                            }
                        }
                    }
                    this.requestConfirm('Are you sure you want to remove this subvariant?', () => { this.archiveExistingSubVariant(vKey, sKey); });
                },
                addVariant() {
                    let subs = this.hasSubvariants
                        ? [{ id: this.nextUid(), name: '', price: '', weight: '', stock: '', isCopied: false, promptState: 'active' }]
                        : undefined;
                    this.newVariants.push({
                        id: this.nextUid(),
                        name: '',
                        price: '',
                        weight: '',
                        stock: '',
                        imagePreview: null,
                        imageBase64: '',
                        imageError: '',
                        isCopied: false,
                        promptState: 'active',
                        isSubsCopied: false,
                        subPromptState: 'active',
                        subs: subs
                    });
                },
                addSubToNewVariant(nv) {
                    if (!nv.subs) nv.subs = [];
                    nv.subs.push({ id: this.nextUid(), name: '', price: '', weight: '', stock: '', isCopied: false, promptState: 'active' });
                },
                removeNewVariantSub(nv, subIndex) {
                    if (nv.subs && nv.subs.length > 1) {
                        nv.subs.splice(subIndex, 1);
                    } else {
                        this.showAlert("This product architecture requires variants to have at least one subvariant.");
                    }
                },
                getFirstMainVariantTemplate(excludeNvId = null) {
                    for (const k of Object.keys(this.existingVariants)) {
                        const ev = this.existingVariants[k];
                        if (ev && !ev.archived && (String(ev.price ?? '').trim() !== '' || String(ev.weight ?? '').trim() !== '' || String(ev.stock ?? '').trim() !== '')) {
                            return ev;
                        }
                    }
                    for (const nv of this.newVariants) {
                        if (nv && nv.id !== excludeNvId && (String(nv.price ?? '').trim() !== '' || String(nv.weight ?? '').trim() !== '' || String(nv.stock ?? '').trim() !== '')) {
                            return nv;
                        }
                    }
                    return null;
                },
                copyFirstMainToNewVariant(nv) {
                    const tpl = this.getFirstMainVariantTemplate(nv?.id);
                    if (!tpl || !nv) return;
                    nv.price = tpl.price;
                    nv.weight = tpl.weight;
                    nv.stock = tpl.stock;
                    nv.isCopied = true;
                    nv.promptState = 'copied';
                    this.validateForm(this.$refs.editForm);
                },
                copyFirstMainToAllNewVariants() {
                    const tpl = this.getFirstMainVariantTemplate();
                    if (!tpl) return;
                    this.newVariants.forEach(nv => {
                        nv.price = tpl.price;
                        nv.weight = tpl.weight;
                        nv.stock = tpl.stock;
                        nv.isCopied = true;
                        nv.promptState = 'copied';
                    });
                    this.validateForm(this.$refs.editForm);
                },
                getFirstSubListTemplate(excludeNvId = null) {
                    for (const k of Object.keys(this.existingVariants)) {
                        const ev = this.existingVariants[k];
                        if (!ev || ev.archived) continue;
                        const activeSubs = [];
                        if (ev.subs && typeof ev.subs === 'object') {
                            Object.values(ev.subs).forEach(s => {
                                if (s && !s.archived && (String(s.name ?? '').trim() !== '' || String(s.price ?? '').trim() !== '' || String(s.weight ?? '').trim() !== '' || String(s.stock ?? '').trim() !== '')) {
                                    activeSubs.push(s);
                                }
                            });
                        }
                        if (Array.isArray(this.newExistingSubs[k])) {
                            this.newExistingSubs[k].forEach(ns => {
                                if (ns && (String(ns.name ?? '').trim() !== '' || String(ns.price ?? '').trim() !== '' || String(ns.weight ?? '').trim() !== '' || String(ns.stock ?? '').trim() !== '')) {
                                    activeSubs.push(ns);
                                }
                            });
                        }
                        if (activeSubs.length > 0) {
                            return { name: ev.name || '1st Variant', subs: activeSubs };
                        }
                    }
                    for (const nv of this.newVariants) {
                        if (!nv || nv.id === excludeNvId || !Array.isArray(nv.subs)) continue;
                        const validSubs = nv.subs.filter(s => s && (String(s.name ?? '').trim() !== '' || String(s.price ?? '').trim() !== '' || String(s.weight ?? '').trim() !== '' || String(s.stock ?? '').trim() !== ''));
                        if (validSubs.length > 0) {
                            return { name: nv.name || '1st New Variant', subs: validSubs };
                        }
                    }
                    return null;
                },
                copySubListToNewVariant(nv) {
                    const tpl = this.getFirstSubListTemplate(nv?.id);
                    if (!tpl || !nv) return;
                    nv.subs = tpl.subs.map(s => ({
                        id: this.nextUid(),
                        name: s.name,
                        price: s.price,
                        weight: s.weight,
                        stock: s.stock,
                        isCopied: true,
                        promptState: 'copied'
                    }));
                    nv.isSubsCopied = true;
                    nv.subPromptState = 'copied';
                    this.validateForm(this.$refs.editForm);
                },
                copySubListToAllNewVariants() {
                    const tpl = this.getFirstSubListTemplate();
                    if (!tpl) return;
                    this.newVariants.forEach(nv => {
                        nv.subs = tpl.subs.map(s => ({
                            id: this.nextUid(),
                            name: s.name,
                            price: s.price,
                            weight: s.weight,
                            stock: s.stock,
                            isCopied: true,
                            promptState: 'copied'
                        }));
                        nv.isSubsCopied = true;
                        nv.subPromptState = 'copied';
                    });
                    this.validateForm(this.$refs.editForm);
                },
                copyFirstSubInNewVariant(nv, subIndex) {
                    if (!nv || !nv.subs || !nv.subs[0] || !nv.subs[subIndex]) return;
                    nv.subs[subIndex].price = nv.subs[0].price;
                    nv.subs[subIndex].weight = nv.subs[0].weight;
                    nv.subs[subIndex].stock = nv.subs[0].stock;
                    nv.subs[subIndex].isCopied = true;
                    nv.subs[subIndex].promptState = 'copied';
                    this.validateForm(this.$refs.editForm);
                },
                getNewSubsForVariant(vKey) {
                    return this.newExistingSubs[String(vKey)] || [];
                },
                addNewSubToVariant(vKey) {
                    let k = String(vKey);
                    if (!this.newExistingSubs[k]) this.newExistingSubs[k] = [];
                    this.newExistingSubs[k].push({ id: this.nextUid(), name: '', price: '', weight: '', stock: '', isCopied: false, promptState: 'active' });
                },
                removeNewSubFromVariant(vKey, sId) {
                    let k = String(vKey);
                    if (this.newExistingSubs[k]) {
                        this.newExistingSubs[k] = this.newExistingSubs[k].filter(s => s.id !== sId);
                    }
                },
                getFirstSubTemplateForExisting(vKey) {
                    let k = String(vKey);
                    let v = this.existingVariants[k];
                    if (v && v.subs) {
                        let activeExisting = Object.values(v.subs).find(s => s && !s.archived && (s.price !== '' || s.weight !== '' || s.stock !== ''));
                        if (activeExisting) return activeExisting;
                    }
                    if (this.newExistingSubs[k] && this.newExistingSubs[k].length > 1) {
                        let firstNew = this.newExistingSubs[k][0];
                        if (firstNew && (firstNew.price !== '' || firstNew.weight !== '' || firstNew.stock !== '')) return firstNew;
                    }
                    return null;
                },
                copyFirstSubToExistingNew(vKey, nsIndex) {
                    let k = String(vKey);
                    let tpl = this.getFirstSubTemplateForExisting(k);
                    if (!tpl || !this.newExistingSubs[k] || !this.newExistingSubs[k][nsIndex]) return;
                    this.newExistingSubs[k][nsIndex].price = tpl.price;
                    this.newExistingSubs[k][nsIndex].weight = tpl.weight;
                    this.newExistingSubs[k][nsIndex].stock = tpl.stock;
                    this.newExistingSubs[k][nsIndex].isCopied = true;
                    this.newExistingSubs[k][nsIndex].promptState = 'copied';
                    this.validateForm(this.$refs.editForm);
                },
                get duplicateVariants() {
                    let names = [];
                    Object.values(this.existingVariants).forEach(v => {
                        if (v && !v.archived) {
                            let n = String(v.name || '').trim().toLowerCase();
                            if (n !== '') names.push(n);
                        }
                    });
                    this.newVariants.forEach(nv => {
                        if (nv) {
                            let n = String(nv.name || '').trim().toLowerCase();
                            if (n !== '') names.push(n);
                        }
                    });
                    return names.filter((item, index) => names.indexOf(item) !== index);
                },
                isVariantDuplicate(name) {
                    let n = String(name || '').trim().toLowerCase();
                    return n !== '' && this.duplicateVariants.includes(n);
                },
                isExistingVariantSubDuplicate(vKey, subName) {
                    let n = String(subName || '').trim().toLowerCase();
                    if (n === '') return false;
                    let k = String(vKey), count = 0, v = this.existingVariants[k];
                    if (v && !v.archived && v.subs) {
                        Object.values(v.subs).forEach(s => {
                            if (s && !s.archived && String(s.name || '').trim().toLowerCase() === n) count++;
                        });
                    }
                    if (this.newExistingSubs[k]) {
                        this.newExistingSubs[k].forEach(ns => {
                            if (ns && String(ns.name || '').trim().toLowerCase() === n) count++;
                        });
                    }
                    return count > 1;
                },
                isNewVariantSubDuplicate(nv, subName) {
                    let n = String(subName || '').trim().toLowerCase();
                    if (n === '' || !nv || !Array.isArray(nv.subs)) return false;
                    let count = 0;
                    nv.subs.forEach(s => {
                        if (s && String(s.name || '').trim().toLowerCase() === n) count++;
                    });
                    return count > 1;
                },
                hasValidationErrors() {
                    if (this.totalMainPicturesCount < 1 || this.totalMainPicturesCount > 10) return true;
                    if (this.duplicateVariants.length > 0) return true;
                    for (let vKey of Object.keys(this.existingVariants)) {
                        let v = this.existingVariants[vKey];
                        if (!v || v.archived) continue;
                        if (String(v.name || '').trim() === '') return true;
                        if (v.subs) {
                            for (let s of Object.values(v.subs)) {
                                if (!s || s.archived) continue;
                                if (String(s.name || '').trim() === '') return true;
                                if (this.isExistingVariantSubDuplicate(vKey, s.name)) return true;
                            }
                        }
                        let newSubs = this.newExistingSubs[vKey] || [];
                        for (let ns of newSubs) {
                            if (!ns) continue;
                            if (String(ns.name || '').trim() === '') return true;
                            if (this.isExistingVariantSubDuplicate(vKey, ns.name)) return true;
                        }
                    }
                    for (let nv of this.newVariants) {
                        if (!nv) continue;
                        if (String(nv.name || '').trim() === '') return true;
                        if (!nv.imageBase64 && !nv.imagePreview) return true;
                        if (Array.isArray(nv.subs)) {
                            for (let s of nv.subs) {
                                if (!s) continue;
                                if (String(s.name || '').trim() === '') return true;
                                if (this.isNewVariantSubDuplicate(nv, s.name)) return true;
                            }
                        }
                    }
                    return false;
                }
            };
        };
        window.editVariantManager = window.storkiaEditVariantManager;
    </script>

    @if($selectedCategory === null)
        <div x-data="{
                addCategoryModal: false,
                selectedNewCategory: '',
                dropdownOpen: false,
                removeModalOpen: false,
                removeCategoryName: '',
                removeCategoryAction: ''
             }"
             class="max-w-7xl mx-auto select-none">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-primary-dark">Product Categories</h1>
                    <p class="text-sm text-text-muted mt-1">Select a category below to view and manage your products or add another category to expand your catalog.</p>
                </div>
                @if(count($availableCategoriesToAdd) > 0)
                    <button type="button" @click="addCategoryModal = true; selectedNewCategory = ''; dropdownOpen = false"
                            class="px-5 py-2.5 bg-primary text-white rounded-xl shadow-md hover:bg-primary-dark transition-colors font-bold text-sm flex items-center self-start sm:self-auto cursor-pointer">
                        <svg class="w-5 h-5 mr-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add other category
                    </button>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                @php
                    $primaryCount = $categoryCounts[$primaryCategory] ?? 0;
                    $primarySubs = $categoriesList[$primaryCategory] ?? [];
                @endphp
                <a href="{{ route('seller.products.index', ['category' => $primaryCategory]) }}"
                   class="group bg-surface rounded-3xl p-6 shadow-xs border-2 border-primary/20 hover:border-primary hover:shadow-xl transition-all flex flex-col justify-between cursor-pointer relative overflow-hidden">
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0 group-hover:bg-primary group-hover:text-white transition-colors">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                            </div>
                            <span class="px-3 py-1 bg-primary text-white text-xs font-bold rounded-full shadow-xs">
                                Primary Category
                            </span>
                        </div>
                        <h2 class="text-xl font-extrabold text-primary-dark group-hover:text-primary transition-colors mb-2">
                            {{ $primaryCategory }}
                        </h2>
                        <div class="flex flex-wrap gap-1.5 mb-6">
                            @foreach(array_slice($primarySubs, 0, 3) as $subItem)
                                <span class="text-[11px] px-2.5 py-0.5 bg-surface-subtle rounded-lg text-text-muted border border-border-subtle font-medium">
                                    {{ $subItem['name'] }}
                                </span>
                            @endforeach
                            @if(count($primarySubs) > 3)
                                <span class="text-[11px] px-2 py-0.5 bg-surface-subtle rounded-lg text-text-muted border border-border-subtle font-bold">
                                    +{{ count($primarySubs) - 3 }} more
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="pt-4 border-t border-border-subtle flex items-center justify-between">
                        <span class="text-xs font-bold px-3 py-1 rounded-full {{ $primaryCount > 0 ? 'bg-success/15 text-success' : 'bg-surface-subtle text-text-muted' }}">
                            {{ $primaryCount }} {{ Str::plural('Product', $primaryCount) }}
                        </span>
                        <span class="text-xs font-bold text-primary flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                            Open Category
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </span>
                    </div>
                </a>

                @foreach($otherCategories as $otherCat)
                    @php
                        $catName = $otherCat->category;
                        $catCount = $categoryCounts[$catName] ?? 0;
                        $catSubs = $categoriesList[$catName] ?? [];
                    @endphp
                    <div @click="window.location.href = '{{ route('seller.products.index', ['category' => $catName]) }}'"
                         class="group bg-surface rounded-3xl p-6 shadow-xs border border-border-subtle hover:border-primary hover:shadow-xl transition-all flex flex-col justify-between cursor-pointer relative overflow-hidden">
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div class="w-12 h-12 rounded-2xl bg-brand-light/30 text-primary-dark flex items-center justify-center shrink-0 group-hover:bg-primary group-hover:text-white transition-colors">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-brand-light/40 text-primary-dark border border-border-subtle text-xs font-bold rounded-full">
                                        Other Category
                                    </span>
                                    @if($catCount === 0)
                                        <button type="button"
                                                @click.stop="removeCategoryName = '{{ addslashes($catName) }}'; removeCategoryAction = '{{ route('seller.products.categories.destroy', $otherCat->id) }}'; removeModalOpen = true"
                                                title="Remove Category"
                                                class="p-1.5 text-text-muted hover:text-danger hover:bg-danger/10 rounded-full transition-colors cursor-pointer">
                                            <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <h2 class="text-xl font-extrabold text-primary-dark group-hover:text-primary transition-colors mb-2">
                                {{ $catName }}
                            </h2>
                            <div class="flex flex-wrap gap-1.5 mb-6">
                                @foreach(array_slice($catSubs, 0, 3) as $subItem)
                                    <span class="text-[11px] px-2.5 py-0.5 bg-surface-subtle rounded-lg text-text-muted border border-border-subtle font-medium">
                                        {{ $subItem['name'] }}
                                    </span>
                                @endforeach
                                @if(count($catSubs) > 3)
                                    <span class="text-[11px] px-2 py-0.5 bg-surface-subtle rounded-lg text-text-muted border border-border-subtle font-bold">
                                        +{{ count($catSubs) - 3 }} more
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="pt-4 border-t border-border-subtle flex items-center justify-between">
                            <span class="text-xs font-bold px-3 py-1 rounded-full {{ $catCount > 0 ? 'bg-success/15 text-success' : 'bg-surface-subtle text-text-muted' }}">
                                {{ $catCount }} {{ Str::plural('Product', $catCount) }}
                            </span>
                            <span class="text-xs font-bold text-primary flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                                Open Category
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <template x-teleport="body">
                <div x-show="addCategoryModal" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                    <div @click="addCategoryModal = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm cursor-pointer"></div>
                    <div class="bg-surface rounded-3xl w-full max-w-md p-6 sm:p-8 shadow-2xl border border-border-subtle relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-primary-dark">Add Other Category</h3>
                            <button type="button" @click="addCategoryModal = false" class="p-1.5 text-text-muted hover:text-danger rounded-full hover:bg-danger/10 transition-colors cursor-pointer">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <p class="text-xs text-text-muted mb-6">
                            Choose an additional category to list products under. Categories already in your profile are excluded from the choices.
                        </p>
                        <form action="{{ route('seller.products.categories.store') }}" method="POST" class="space-y-6">
                            @csrf
                            <div class="relative" @click.away="dropdownOpen = false">
                                <label class="block text-sm font-bold text-text-main mb-1.5">Select Main Category <span class="text-danger">*</span></label>
                                <input type="hidden" name="category" :value="selectedNewCategory" required>
                                <button type="button" @click="dropdownOpen = !dropdownOpen"
                                        class="w-full p-3.5 rounded-2xl border border-border-subtle focus:border-primary outline-none bg-surface-subtle text-sm flex justify-between items-center transition-colors shadow-2xs cursor-pointer hover:border-primary/50">
                                    <span x-text="selectedNewCategory || 'Choose a category...'" :class="selectedNewCategory ? 'text-text-main font-bold' : 'text-text-muted'"></span>
                                    <svg class="w-4 h-4 text-text-muted transition-transform duration-200" :class="dropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div x-show="dropdownOpen" x-transition x-cloak
                                     class="absolute z-[220] w-full mt-2 bg-surface border border-border-subtle rounded-2xl shadow-xl max-h-60 overflow-y-auto custom-scrollbar p-1.5 divide-y divide-border-subtle/50">
                                    @foreach($availableCategoriesToAdd as $availCat)
                                        <div @click="selectedNewCategory = '{{ addslashes($availCat) }}'; dropdownOpen = false"
                                             class="px-4 py-3 rounded-xl text-sm cursor-pointer transition-colors flex items-center justify-between"
                                             :class="selectedNewCategory === '{{ addslashes($availCat) }}' ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-brand-light/20'">
                                            <span>{{ $availCat }}</span>
                                            <svg x-show="selectedNewCategory === '{{ addslashes($availCat) }}'" class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="addCategoryModal = false" class="px-5 py-2.5 rounded-xl font-bold text-text-muted hover:bg-surface-subtle transition-colors text-sm cursor-pointer">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="!selectedNewCategory"
                                        :class="!selectedNewCategory ? 'opacity-50 cursor-not-allowed bg-surface-subtle text-text-muted' : 'bg-primary hover:bg-primary-dark text-white cursor-pointer'"
                                        class="px-6 py-2.5 rounded-xl shadow-md transition-colors font-bold text-sm">
                                    Add Category
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <template x-teleport="body">
                <div x-show="removeModalOpen" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                    <div @click="removeModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm cursor-pointer"></div>
                    <div class="bg-surface rounded-3xl w-full max-w-sm p-6 shadow-2xl border border-border-subtle text-center relative z-10">
                        <div class="w-14 h-14 rounded-full bg-danger/10 flex items-center justify-center mx-auto mb-4 border border-danger/20">
                            <svg class="w-7 h-7 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-primary-dark mb-2">Remove Category</h3>
                        <p class="text-sm text-text-muted mb-6">
                            Are you sure you want to remove <strong class="text-text-main" x-text="removeCategoryName"></strong> from your categories?
                        </p>
                        <form :action="removeCategoryAction" method="POST" class="flex gap-3">
                            @csrf
                            @method('DELETE')
                            <button type="button" @click="removeModalOpen = false" class="w-full px-4 py-2.5 bg-surface-subtle text-text-main font-bold rounded-xl hover:bg-brand-light/30 transition-colors text-sm cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit" class="w-full px-4 py-2.5 bg-danger text-white font-bold rounded-xl hover:opacity-90 transition-opacity text-sm shadow-md cursor-pointer">
                                Remove
                            </button>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    @else
        <div class="max-w-7xl mx-auto select-none">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                <div class="flex items-center gap-4">
                    <a href="{{ route('seller.products.index') }}"
                       class="p-2.5 bg-surface rounded-full shadow-xs border border-border-subtle hover:bg-brand-light/20 transition-colors text-text-muted hover:text-primary cursor-pointer">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </a>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="text-3xl font-bold text-primary-dark">All Products</h1>
                            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $selectedCategory === $primaryCategory ? 'bg-primary text-white' : 'bg-brand-light/40 text-primary-dark border border-border-subtle' }}">
                                {{ $selectedCategory }} ({{ $selectedCategory === $primaryCategory ? 'Primary Category' : 'Other Category' }})
                            </span>
                        </div>
                        <p class="text-xs text-text-muted mt-1">Showing products created under {{ $selectedCategory }}.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 self-start sm:self-auto">
                    @if($selectedCategory !== $primaryCategory && $activeCategoryRecord && $products->total() === 0)
                        <form action="{{ route('seller.products.categories.destroy', $activeCategoryRecord->id) }}" method="POST" class="m-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-4 py-2.5 bg-danger/10 text-danger border border-danger/25 rounded-xl hover:bg-danger hover:text-white transition-colors font-bold text-sm flex items-center cursor-pointer">
                                <svg class="w-4 h-4 mr-1.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Remove Category
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('seller.products.create', ['category' => $selectedCategory]) }}"
                       class="px-5 py-2.5 bg-primary text-white rounded-xl shadow-md hover:bg-primary-dark transition-colors font-bold text-sm flex items-center cursor-pointer">
                        <svg class="w-5 h-5 mr-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        Add Product
                    </a>
                </div>
            </div>

            <div class="bg-surface rounded-2xl shadow-xs border border-border-subtle mb-6">
                <table class="w-full text-left border-collapse relative">
                    <thead>
                        <tr class="bg-brand-light/30 text-text-muted text-sm border-b border-border-subtle">
                            <th class="p-4 font-bold w-16 rounded-tl-2xl">Cover</th>
                            <th class="p-4 font-bold">Product Name</th>
                            <th class="p-4 font-bold">Subcategory</th>
                            <th class="p-4 font-bold">Price</th>
                            <th class="p-4 font-bold">Total Stock</th>
                            <th class="p-4 font-bold">Approval Status</th>
                            <th class="p-4 font-bold text-right w-24 rounded-tr-2xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $approvalStatus = $product->approval->status ?? 'Pending';
                                $isApprovedProduct = $approvalStatus === 'Approved';
                                $productVariants = $product->raw_variants ?? $product->variants;
                                $priceDependency = $productVariants['price_dependency'] ?? 'main';
                                $productCategoryName = $product->category ?? $primaryCategory;
                                $productSubcategories = $categoriesList[$productCategoryName] ?? ($categoriesList[$primaryCategory] ?? []);
                                $totalStock = 0;
                                $pendingVariantCount = 0;
                                $pendingSubVariantCount = 0;
                                $existingVariantsData = [];
                                $variantComplianceRows = [];

                                if (isset($productVariants['items']) && is_array($productVariants['items'])) {
                                    foreach ($productVariants['items'] as $idx => $item) {
                                        if (!empty($item['is_archived'])) continue;
                                        $rawItemStatus = $item['status'] ?? 'Approved';
                                        $effectiveItemStatus = !$isApprovedProduct ? $approvalStatus : $rawItemStatus;
                                        if ($isApprovedProduct && $rawItemStatus === 'Pending') {
                                            $pendingVariantCount++;
                                        }
                                        $subData = [];
                                        $hasSubs = isset($item['subs']) && is_array($item['subs']) && count($item['subs']) > 0;
                                        if ($hasSubs) {
                                            foreach ($item['subs'] as $sIdx => $sub) {
                                                if (!empty($sub['is_archived'])) continue;
                                                $rawSubStatus = $sub['status'] ?? 'Approved';
                                                $effectiveSubStatus = !$isApprovedProduct
                                                    ? $approvalStatus
                                                    : ($effectiveItemStatus === 'Pending' ? 'Pending' : ($effectiveItemStatus === 'Disapproved' ? 'Disapproved' : $rawSubStatus));
                                                if ($isApprovedProduct && $rawItemStatus !== 'Disapproved' && $rawSubStatus === 'Pending') {
                                                    $pendingSubVariantCount++;
                                                }
                                                if ($effectiveSubStatus !== 'Disapproved') {
                                                    $subData['s_' . $sIdx] = [
                                                        'name'   => trim($sub['name'] ?? ''),
                                                        'price'  => $sub['price'] ?? '',
                                                        'weight' => $sub['weight'] ?? '',
                                                        'stock'  => $sub['stock'] ?? '',
                                                        'status' => $effectiveSubStatus,
                                                    ];
                                                }
                                                $variantComplianceRows[] = [
                                                    'variant'    => $item['name'] ?? 'N/A',
                                                    'subvariant' => $sub['name'] ?? 'N/A',
                                                    'status'     => $effectiveSubStatus,
                                                    'price'      => (float) ($sub['price'] ?? ($item['price'] ?? $product->price)),
                                                    'stock'      => (int) ($sub['stock'] ?? 0),
                                                ];
                                                $countInActiveStock = !$isApprovedProduct
                                                    ? ($rawSubStatus !== 'Disapproved')
                                                    : ($effectiveItemStatus === 'Approved' && $effectiveSubStatus === 'Approved');
                                                if ($countInActiveStock) {
                                                    $totalStock += (int) ($sub['stock'] ?? 0);
                                                }
                                            }
                                        } else {
                                            $variantComplianceRows[] = [
                                                'variant'    => $item['name'] ?? 'N/A',
                                                'subvariant' => null,
                                                'status'     => $effectiveItemStatus,
                                                'price'      => (float) ($item['price'] ?? $product->price),
                                                'stock'      => (int) ($item['stock'] ?? 0),
                                            ];
                                            $countInActiveStock = !$isApprovedProduct
                                                ? ($rawItemStatus !== 'Disapproved')
                                                : ($effectiveItemStatus === 'Approved');
                                            if ($countInActiveStock) {
                                                $totalStock += (int) ($item['stock'] ?? 0);
                                            }
                                        }
                                        if ($effectiveItemStatus !== 'Disapproved') {
                                            $existingVariantsData['v_' . $idx] = [
                                                'name'   => trim($item['name'] ?? ''),
                                                'price'  => $item['price'] ?? '',
                                                'weight' => $item['weight'] ?? '',
                                                'stock'  => $item['stock'] ?? '',
                                                'status' => $effectiveItemStatus,
                                                'subs'   => $subData,
                                            ];
                                        }
                                    }
                                } else {
                                    $totalStock = (int) ($product->stock_quantity ?? 0);
                                }

                                $mainPicsArray = (!empty($product->pictures) && is_array($product->pictures))
                                    ? array_slice(array_values(array_filter($product->pictures)), 0, 10)
                                    : [];
                                $mainPicUrls = array_map(fn ($p) => asset('storage/' . $p), $mainPicsArray);
                                $firstPic = $mainPicUrls[0] ?? '';
                                $hasPendingAdditions = $isApprovedProduct && ($product->has_pending_variants || $pendingVariantCount > 0 || $pendingSubVariantCount > 0);
                                $hasSubvariantsArchitecture = !empty($productVariants['sub_title']) || ($priceDependency === 'sub');
                                if (!$hasSubvariantsArchitecture && isset($productVariants['items']) && is_array($productVariants['items'])) {
                                    foreach ($productVariants['items'] as $it) {
                                        if (!empty($it['subs']) && is_array($it['subs']) && count($it['subs']) > 0) {
                                            $hasSubvariantsArchitecture = true;
                                            break;
                                        }
                                    }
                                }
                                $editManagerConfig = [
                                    'existingPictures' => $mainPicsArray,
                                    'existingVariants' => $existingVariantsData,
                                    'hasSubvariants'   => $hasSubvariantsArchitecture,
                                    'priceDependency'  => $priceDependency,
                                ];
                                $existingSpecs = $product->specifications->map(function($s) {
                                    return ['id' => $s->id, 'title' => $s->title, 'desc' => $s->description];
                                })->values()->toJson();

                                $modalDescLen = mb_strlen((string) ($product->description ?? '')) + mb_strlen((string) ($product->additional_descriptions ?? ''));
                                $modalDescLines = substr_count((string) ($product->description ?? ''), "\n") + substr_count((string) ($product->additional_descriptions ?? ''), "\n");
                                $isModalLongDesc = $modalDescLen > 240 || $modalDescLines >= 5;
                            @endphp
                            <tr x-data="storkiaProductTableRow({
                                    basePrice: {{ $product->price ?? 0 }},
                                    discountPercent: {{ $product->discount ?? 0 }},
                                    totalStock: {{ $totalStock }},
                                    defaultImage: '{{ $firstPic }}',
                                    galleryImages: @js($mainPicUrls)
                                })"
                                @click="viewModal = true"
                                class="border-b border-border-subtle hover:bg-brand-light/10 transition-colors text-sm cursor-pointer relative">
                                <td class="p-4">
                                    @if($firstPic)
                                        <div class="relative w-12 h-12">
                                            <img src="{{ $firstPic }}" alt="{{ $product->name }}" class="w-12 h-12 rounded-lg object-cover border border-border-subtle shadow-2xs cursor-pointer">
                                            @if(count($mainPicsArray) > 1)
                                                <span class="absolute -bottom-1 -right-1 px-1.5 py-0.2 rounded-full bg-primary text-white text-[9px] font-bold">
                                                    {{ count($mainPicsArray) }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="w-12 h-12 rounded-lg bg-surface-subtle border border-border-subtle flex items-center justify-center text-text-muted">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </div>
                                    @endif
                                </td>
                                <td class="p-4 font-bold text-primary-dark">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span>{{ $product->name }}</span>
                                        @if($hasPendingAdditions)
                                            <span class="px-2 py-0.5 bg-brand-light/70 text-primary-dark border border-primary/30 text-[10px] font-bold rounded-full">
                                                @if($pendingVariantCount > 0 && $pendingSubVariantCount > 0)
                                                    {{ $pendingVariantCount }} Variant(s) &amp; {{ $pendingSubVariantCount }} Subvariant(s) Pending
                                                @elseif($pendingVariantCount > 0)
                                                    {{ $pendingVariantCount }} Variant(s) Pending
                                                @else
                                                    {{ $pendingSubVariantCount }} Subvariant(s) Pending
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="p-4 text-text-muted font-medium">{{ $product->subcategory ?? 'Uncategorized' }}</td>
                                <td class="p-4">
                                    @if($product->discount > 0)
                                        <span class="text-xs line-through text-text-muted mr-1">&#8369;{{ number_format($product->price, 2) }}</span>
                                        <span class="font-bold text-price">&#8369;{{ number_format($product->price - ($product->price * ($product->discount / 100)), 2) }}</span>
                                    @else
                                        <span class="font-bold text-text-main">&#8369;{{ number_format($product->price, 2) }}</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $totalStock > 0 ? 'bg-success/15 text-success border-success/25' : 'bg-danger/10 text-danger border-danger/25' }}">
                                        {{ $totalStock }} in stock
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $approvalStatus === 'Approved' ? 'bg-success/15 text-success' : ($approvalStatus === 'Disapproved' ? 'bg-danger/10 text-danger' : 'bg-brand-light/70 text-primary-dark') }}">
                                            {{ $approvalStatus }}
                                        </span>
                                        @if($hasPendingAdditions)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-light/70 text-primary-dark border border-primary/30">
                                                Additions Under Review
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="relative inline-block text-left" @click.stop @click.away="menuOpen = false">
                                        <button type="button" @click.stop="menuOpen = !menuOpen" class="p-2 text-text-muted hover:text-primary hover:bg-brand-light/30 rounded-full transition-colors focus:outline-none cursor-pointer">
                                            <svg class="w-5 h-5 pointer-events-none" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                        </button>
                                        <div x-show="menuOpen" x-transition x-cloak class="absolute right-0 mt-2 w-40 bg-surface rounded-xl shadow-xl border border-border-subtle z-[60] overflow-hidden">
                                            @if($approvalStatus === 'Pending')
                                                <button type="button" disabled class="w-full text-left px-4 py-3 text-sm font-medium text-text-muted/50 cursor-not-allowed flex items-center" title="Cannot update while pending approval">
                                                    <svg class="w-4 h-4 mr-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                    Update
                                                </button>
                                            @else
                                                <button type="button" @click.stop="editModal = true; menuOpen = false;" class="w-full text-left px-4 py-3 text-sm font-medium text-text-main hover:bg-brand-light/20 hover:text-primary transition-colors cursor-pointer flex items-center">
                                                    <svg class="w-4 h-4 mr-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                    Update
                                                </button>
                                            @endif
                                            <form action="{{ route('seller.products.archive', $product) }}" method="POST" class="m-0">
                                                @csrf @method('DELETE')
                                                <button type="submit" @click.stop class="w-full text-left px-4 py-3 text-sm font-medium text-danger hover:bg-danger/10 transition-colors cursor-pointer flex items-center">
                                                    <svg class="w-4 h-4 mr-2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                                                    Archive
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>

                                <template x-teleport="body">
                                    <div x-show="viewModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-8">
                                        <div @click="viewModal = false" class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity cursor-pointer"></div>
                                        <div class="bg-surface rounded-3xl w-full max-w-5xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh] relative z-10 text-left">
                                            <button type="button" @click="viewModal = false" class="absolute top-4 right-4 z-20 p-2 bg-surface/90 backdrop-blur border border-border-subtle text-text-muted hover:text-danger rounded-full hover:bg-danger/10 transition-all shadow-xs cursor-pointer">
                                                <svg class="w-6 h-6 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                            <div class="flex flex-col md:flex-row h-full overflow-y-auto custom-scrollbar bg-surface-subtle/30 relative z-10">
                                                <div class="w-full md:w-1/2 p-6 md:p-8 border-b md:border-b-0 md:border-r border-border-subtle bg-surface">
                                                    <div class="relative aspect-square bg-surface rounded-2xl border border-border-subtle overflow-hidden flex items-center justify-center p-4 shadow-inner mb-4">
                                                        <template x-if="activeImage !== ''">
                                                            <img :src="activeImage" alt="Product" class="w-full h-full object-contain rounded-xl transition-all duration-300">
                                                        </template>
                                                        <template x-if="activeImage === ''">
                                                            <svg class="w-24 h-24 text-border-subtle pointer-events-none" fill="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                        </template>
                                                        <div class="absolute bottom-3 left-3 z-10">
                                                            <template x-if="isViewingVariantImage">
                                                                <button type="button"
                                                                        @click.stop="selectGalleryPic(activeGalleryIndex, galleryImages[activeGalleryIndex] || defaultImage)"
                                                                        class="px-3 py-1 rounded-full bg-primary text-white text-[11px] font-bold shadow-md cursor-pointer">
                                                                    Viewing Variant Photo (Click for Main Gallery)
                                                                </button>
                                                            </template>
                                                            <template x-if="!isViewingVariantImage && galleryImages.length > 0">
                                                                <span class="px-2.5 py-1 rounded-full bg-primary-dark/85 text-white text-[11px] font-bold pointer-events-none"
                                                                      x-text="'Main Gallery ' + (activeGalleryIndex + 1) + ' / ' + galleryImages.length"></span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    @if(!empty($mainPicUrls))
                                                        <div class="flex gap-3 overflow-x-auto pb-2 custom-scrollbar">
                                                            @foreach($mainPicUrls as $picIdx => $picUrl)
                                                                <button type="button"
                                                                        @click.stop="selectGalleryPic({{ $picIdx }}, '{{ $picUrl }}')"
                                                                        :class="(!isViewingVariantImage && activeGalleryIndex === {{ $picIdx }}) ? 'border-primary ring-2 ring-primary/20' : 'border-border-subtle hover:border-primary/50'"
                                                                        class="relative w-20 h-20 flex-shrink-0 rounded-xl border-2 transition-all p-1 bg-surface cursor-pointer overflow-hidden">
                                                                    <img src="{{ $picUrl }}" class="w-full h-full object-cover rounded-lg pointer-events-none">
                                                                    @if($picIdx === 0)
                                                                        <span class="absolute bottom-0 inset-x-0 bg-primary/90 text-white text-[9px] font-extrabold uppercase text-center py-0.5 pointer-events-none">
                                                                            Cover
                                                                        </span>
                                                                    @endif
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="w-full md:w-1/2 p-6 md:p-8 space-y-6 flex flex-col">
                                                    @if($approvalStatus === 'Disapproved')
                                                        <div class="p-4 bg-danger/10 border-l-4 border-danger rounded-r-xl shadow-2xs">
                                                            <div class="flex items-start gap-3">
                                                                <svg class="w-5 h-5 text-danger shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                                <div>
                                                                    <h4 class="text-sm font-bold text-danger uppercase tracking-wider">Product Disapproved</h4>
                                                                    <p class="text-xs text-text-main mt-1 font-medium italic">Reason: "{{ $product->approval->remarks }}"</p>
                                                                    <button type="button" @click="viewModal = false; editModal = true" class="mt-2 text-xs font-bold text-danger underline cursor-pointer hover:opacity-80">Update Product &amp; Resubmit</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @elseif($approvalStatus === 'Pending')
                                                        <div class="p-4 bg-brand-light/40 border-l-4 border-primary-dark rounded-r-xl shadow-2xs text-xs text-primary-dark font-medium">
                                                            This product is currently <strong>Pending Review</strong> and is not yet visible to the public.
                                                        </div>
                                                    @elseif($hasPendingAdditions)
                                                        <div class="p-4 bg-brand-light/40 border-l-4 border-primary-dark rounded-r-xl shadow-2xs text-xs text-primary-dark font-medium">
                                                            Approved variants are live on the store. Newly added variants or subvariants marked as <strong>Pending</strong> are hidden from public view until approved by an admin.
                                                        </div>
                                                    @endif

                                                    <div>
                                                        <h1 class="text-3xl font-extrabold text-primary-dark mb-1 leading-tight">{{ $product->name }}</h1>
                                                        <p class="text-sm text-text-muted font-bold mb-3">{{ $productCategoryName }} &bull; {{ $product->subcategory ?? 'Uncategorized' }}</p>
                                                        <div class="flex items-center justify-between bg-brand-light/15 p-5 rounded-2xl border border-brand-light/40 shadow-2xs">
                                                            <div class="flex items-end gap-3">
                                                                <template x-if="discountPercent > 0">
                                                                    <div class="flex flex-col">
                                                                        <div class="flex items-center gap-2 mb-1">
                                                                            <span class="text-lg line-through text-text-muted" x-text="'\u20B1' + activePrice.toFixed(2)"></span>
                                                                            <span class="px-2 py-0.5 bg-danger/10 text-danger text-[10px] font-bold rounded-md tracking-wider uppercase" x-text="discountPercent + '% OFF'"></span>
                                                                        </div>
                                                                        <span class="text-4xl font-extrabold text-price" x-text="'\u20B1' + (activePrice - (activePrice * (discountPercent / 100))).toFixed(2)"></span>
                                                                    </div>
                                                                </template>
                                                                <template x-if="discountPercent <= 0">
                                                                    <span class="text-4xl font-extrabold text-price" x-text="'\u20B1' + activePrice.toFixed(2)"></span>
                                                                </template>
                                                            </div>
                                                            <div class="text-right">
                                                                <span class="px-3 py-1.5 rounded-full text-xs font-bold shadow-2xs border block"
                                                                      :class="activeStock > 0 ? 'bg-success/15 text-success border-success/25' : 'bg-danger/10 text-danger border-danger/25'">
                                                                    <span x-text="activeStock"></span> in stock
                                                                </span>
                                                                <span class="text-[10px] text-text-muted font-medium mt-1 block" x-text="selectedMain !== null ? 'Selected Variant Stock' : 'Active Public Stock'"></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @if($productVariants && isset($productVariants['items']) && is_array($productVariants['items']))
                                                        <div class="bg-surface p-5 rounded-2xl border border-border-subtle shadow-2xs space-y-4">
                                                            <div>
                                                                <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-3">{{ $productVariants['title'] ?? 'Variants' }}</h3>
                                                                <div class="flex flex-wrap gap-2">
                                                                    @foreach($productVariants['items'] as $vIndex => $item)
                                                                        @if(!empty($item['is_archived'])) @continue @endif
                                                                        @php
                                                                            $vEffStatus = !$isApprovedProduct ? $approvalStatus : ($item['status'] ?? 'Approved');
                                                                            $hasSubs = isset($item['subs']) && count($item['subs']) > 0;
                                                                            $itemStock = 0;
                                                                            if ($hasSubs) {
                                                                                foreach ($item['subs'] as $sb) {
                                                                                    if (!empty($sb['is_archived']) || ($sb['status'] ?? 'Approved') === 'Disapproved') continue;
                                                                                    if ($isApprovedProduct && $vEffStatus === 'Approved' && ($sb['status'] ?? 'Approved') !== 'Approved') continue;
                                                                                    $itemStock += (int)($sb['stock'] ?? 0);
                                                                                }
                                                                            } else {
                                                                                $itemStock = (int)($item['stock'] ?? 0);
                                                                            }
                                                                            $itemVariantImg = (!empty($item['image'])) ? asset('storage/' . $item['image']) : '';
                                                                        @endphp
                                                                        <button type="button" @click.stop="
                                                                            if (selectedMain === '{{ $vIndex }}') {
                                                                                selectedMain = null; selectedSub = null;
                                                                                activePrice = basePrice; activeStock = totalStockCount;
                                                                                resetGalleryPic();
                                                                            } else {
                                                                                selectedMain = '{{ $vIndex }}'; selectedSub = null;
                                                                                activePrice = {{ (float)($item['price'] ?? $product->price) }}; activeStock = {{ $itemStock }};
                                                                                @if($itemVariantImg !== '')
                                                                                    selectVariantPic('{{ $itemVariantImg }}');
                                                                                @endif
                                                                            }"
                                                                            :class="selectedMain === '{{ $vIndex }}' ? 'bg-primary text-white border-primary shadow-md' : 'bg-surface text-text-main border-border-subtle hover:border-primary hover:bg-brand-light/10'"
                                                                            class="px-4 py-2 text-sm font-bold border-2 rounded-xl transition-all cursor-pointer flex items-center gap-2">
                                                                            <span class="pointer-events-none">{{ $item['name'] ?? 'Unnamed' }}</span>
                                                                            @if($vEffStatus === 'Pending')
                                                                                <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-brand-light text-primary-dark font-bold pointer-events-none">Pending</span>
                                                                            @elseif($vEffStatus === 'Disapproved')
                                                                                <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-danger/15 text-danger font-bold pointer-events-none">Disapproved</span>
                                                                            @else
                                                                                <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-success/15 text-success font-bold pointer-events-none">Approved</span>
                                                                            @endif
                                                                            <span class="text-xs px-1.5 py-0.5 rounded-md font-semibold pointer-events-none" :class="selectedMain === '{{ $vIndex }}' ? 'bg-white/20 text-white' : 'bg-surface-subtle text-text-muted'">{{ $itemStock }} left</span>
                                                                        </button>
                                                                    @endforeach
                                                                </div>
                                                            </div>

                                                            @foreach($productVariants['items'] as $vIndex => $item)
                                                                @if(!empty($item['is_archived'])) @continue @endif
                                                                @php $vEffStatus = !$isApprovedProduct ? $approvalStatus : ($item['status'] ?? 'Approved'); @endphp
                                                                @if(isset($item['subs']) && count($item['subs']) > 0)
                                                                    <div x-show="selectedMain === '{{ $vIndex }}'" x-collapse class="pt-4 border-t border-border-subtle">
                                                                        <h4 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-3">{{ $productVariants['sub_title'] ?? 'Sub Variants' }}</h4>
                                                                        <div class="flex flex-wrap gap-2">
                                                                            @foreach($item['subs'] as $sIndex => $sub)
                                                                                @if(!empty($sub['is_archived'])) @continue @endif
                                                                                @php
                                                                                    $subStock = (int)($sub['stock'] ?? 0);
                                                                                    $sEffStatus = !$isApprovedProduct ? $approvalStatus : ($vEffStatus !== 'Approved' ? $vEffStatus : ($sub['status'] ?? 'Approved'));
                                                                                @endphp
                                                                                <button type="button" @click.stop="
                                                                                    if (selectedSub === '{{ $sIndex }}') {
                                                                                        selectedSub = null; activePrice = {{ (float)($item['price'] ?? $product->price) }}; activeStock = {{ $totalStock }};
                                                                                    } else {
                                                                                        selectedSub = '{{ $sIndex }}'; activePrice = {{ (float)($sub['price'] ?? ($item['price'] ?? $product->price)) }}; activeStock = {{ $subStock }};
                                                                                    }"
                                                                                    :class="selectedSub === '{{ $sIndex }}' ? 'bg-primary text-white border-primary shadow-md' : 'bg-surface text-text-main border-border-subtle hover:border-primary hover:bg-brand-light/10'"
                                                                                    class="px-4 py-2 border-2 rounded-xl text-sm font-bold transition-all cursor-pointer flex items-center gap-2">
                                                                                    <span class="pointer-events-none">{{ $sub['name'] ?? '' }}</span>
                                                                                    @if($sEffStatus === 'Pending')
                                                                                        <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-brand-light text-primary-dark font-bold pointer-events-none">Pending</span>
                                                                                    @elseif($sEffStatus === 'Disapproved')
                                                                                        <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-danger/15 text-danger font-bold pointer-events-none">Disapproved</span>
                                                                                    @else
                                                                                        <span class="text-[9px] px-1.5 py-0.5 rounded-md bg-success/15 text-success font-bold pointer-events-none">Approved</span>
                                                                                    @endif
                                                                                    <span class="text-xs px-1.5 py-0.5 rounded-md font-semibold pointer-events-none" :class="selectedSub === '{{ $sIndex }}' ? 'bg-white/20 text-white' : 'bg-surface-subtle text-text-muted'">{{ $subStock }} left</span>
                                                                                </button>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </div>

                                                        <div class="bg-surface p-5 rounded-2xl border border-border-subtle shadow-2xs space-y-3">
                                                            <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider">All Variant Stock &amp; Compliance Details</h3>
                                                            <div class="overflow-x-auto custom-scrollbar">
                                                                <table class="w-full text-left text-xs border-collapse">
                                                                    <thead>
                                                                        <tr class="bg-surface-subtle border-b border-border-subtle text-text-muted font-bold">
                                                                            <th class="p-2">Variant</th>
                                                                            <th class="p-2">Subvariant</th>
                                                                            <th class="p-2">Status</th>
                                                                            <th class="p-2">Price</th>
                                                                            <th class="p-2 text-right">Stock</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="divide-y divide-border-subtle">
                                                                        @foreach($variantComplianceRows as $rIdx => $vRow)
                                                                            <tr x-show="{{ $rIdx }} < 5 || showAllVariantRows" class="hover:bg-brand-light/10">
                                                                                <td class="p-2 font-medium text-primary-dark">{{ $vRow['variant'] }}</td>
                                                                                <td class="p-2 {{ $vRow['subvariant'] ? 'text-text-main font-semibold' : 'text-text-muted italic' }}">{{ $vRow['subvariant'] ?? 'None' }}</td>
                                                                                <td class="p-2">
                                                                                    @if($vRow['status'] === 'Approved')
                                                                                        <span class="text-[10px] px-2 py-0.5 bg-success/15 text-success font-bold rounded-full">Approved (Live)</span>
                                                                                    @elseif($vRow['status'] === 'Disapproved')
                                                                                        <span class="text-[10px] px-2 py-0.5 bg-danger/10 text-danger font-bold rounded-full">Disapproved (Hidden)</span>
                                                                                    @else
                                                                                        <span class="text-[10px] px-2 py-0.5 bg-brand-light/80 text-primary-dark font-bold rounded-full">Pending (Hidden)</span>
                                                                                    @endif
                                                                                </td>
                                                                                <td class="p-2 font-bold text-price">&#8369;{{ number_format($vRow['price'], 2) }}</td>
                                                                                <td class="p-2 text-right">
                                                                                    <span class="px-2 py-0.5 rounded-full font-bold {{ $vRow['stock'] > 0 ? 'bg-success/15 text-success' : 'bg-danger/10 text-danger' }}">{{ $vRow['stock'] }} units</span>
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            @if(count($variantComplianceRows) > 5)
                                                                <div class="pt-2 flex justify-center">
                                                                    <button type="button"
                                                                            @click.stop="showAllVariantRows = !showAllVariantRows"
                                                                            class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                                                                        <span x-text="showAllVariantRows ? 'See Less Variants' : 'See All {{ count($variantComplianceRows) }} Variant Rows'"></span>
                                                                    </button>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    @if($product->specifications && $product->specifications->count() > 0)
                                                        <div class="bg-surface p-5 rounded-2xl border border-border-subtle shadow-2xs mb-2">
                                                            <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-3">Specifications</h3>
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                                                @foreach($product->specifications as $spIdx => $spec)
                                                                    <div x-show="{{ $spIdx }} < 6 || showAllSpecs" class="flex items-center gap-2">
                                                                        <span class="text-text-muted font-medium w-1/3">{{ $spec->title }}:</span>
                                                                        <span class="text-text-main font-bold flex-1">{{ $spec->description }}</span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            @if($product->specifications->count() > 6)
                                                                <div class="pt-3">
                                                                    <button type="button"
                                                                            @click.stop="showAllSpecs = !showAllSpecs"
                                                                            class="text-xs font-extrabold text-primary hover:underline cursor-pointer"
                                                                            x-text="showAllSpecs ? 'See Less Specifications' : 'See All {{ $product->specifications->count() }} Specifications'"></button>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    <div class="bg-surface p-5 rounded-2xl border border-border-subtle shadow-2xs flex-1">
                                                        <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-3">Product Description</h3>
                                                        <div class="relative">
                                                            <div :class="(!descExpanded && {{ $isModalLongDesc ? 'true' : 'false' }}) ? 'max-h-40 overflow-hidden' : ''"
                                                                 class="space-y-4">
                                                                <p class="text-sm text-text-main leading-relaxed whitespace-pre-line">{{ $product->description ?: 'No description provided.' }}</p>
                                                                @if(!empty($product->additional_descriptions))
                                                                    <div class="pt-4 border-t border-border-subtle">
                                                                        <h3 class="font-bold text-text-muted text-xs uppercase tracking-wider mb-2">Additional Descriptions</h3>
                                                                        <p class="text-sm text-text-main leading-relaxed whitespace-pre-line">{{ $product->additional_descriptions }}</p>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            @if($isModalLongDesc)
                                                                <div x-show="!descExpanded"
                                                                     class="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-surface via-surface/85 to-transparent"></div>
                                                            @endif
                                                        </div>
                                                        @if($isModalLongDesc)
                                                            <div class="relative z-20 pt-3 mt-2 border-t border-border-subtle/60 flex justify-center">
                                                                <button type="button"
                                                                        @click.stop="descExpanded = !descExpanded"
                                                                        class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                                                                    <span x-text="descExpanded ? 'See Less Description' : 'See More Description'"></span>
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="h-6 md:h-8 w-full shrink-0"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template x-teleport="body">
                                    <div x-show="editModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-8"
                                         x-data="storkiaEditVariantManager(@js($editManagerConfig))">
                                        <div @click.stop="editModal = false" class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity cursor-pointer"></div>
                                        <div class="bg-surface rounded-3xl w-full max-w-4xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] text-left relative z-10 pointer-events-auto">
                                            <div x-show="confirmModal && confirmModal.open" x-cloak
                                                 :class="(confirmModal && confirmModal.open) ? 'pointer-events-auto z-[200]' : 'pointer-events-none hidden'"
                                                 class="absolute inset-0 flex items-center justify-center rounded-3xl overflow-hidden">
                                                <div @click.stop="confirmModal.open = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity cursor-pointer"></div>
                                                <div @click.away="confirmModal.open = false" class="bg-surface rounded-2xl w-full max-w-sm p-6 shadow-2xl text-center relative z-10 mx-4 pointer-events-auto cursor-auto border border-border-subtle">
                                                    <div class="w-14 h-14 rounded-full bg-danger/10 flex items-center justify-center mx-auto mb-4 border border-danger/20">
                                                        <svg class="w-7 h-7 text-danger pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    </div>
                                                    <h3 class="text-lg font-bold text-primary-dark mb-2">Confirm Action</h3>
                                                    <p class="text-sm text-text-muted mb-8 leading-relaxed font-medium" x-text="confirmModal.message"></p>
                                                    <div class="flex gap-3 justify-center">
                                                        <button type="button" @click.stop="confirmModal.open = false" class="px-5 py-2.5 bg-surface-subtle text-text-main font-bold rounded-xl hover:bg-brand-light/30 transition-colors text-sm w-full cursor-pointer">Cancel</button>
                                                        <button type="button" @click.stop="confirmModal.onConfirm(); confirmModal.open = false" class="px-5 py-2.5 bg-danger text-white font-bold rounded-xl hover:opacity-90 transition-opacity text-sm shadow-md w-full cursor-pointer">Proceed</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div x-show="alertModal && alertModal.open" x-cloak
                                                 :class="(alertModal && alertModal.open) ? 'pointer-events-auto z-[210]' : 'pointer-events-none hidden'"
                                                 class="absolute inset-0 flex items-center justify-center rounded-3xl overflow-hidden">
                                                <div @click.stop="alertModal.open = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity cursor-pointer"></div>
                                                <div @click.away="alertModal.open = false" class="bg-surface rounded-2xl w-full max-w-sm p-6 shadow-2xl text-center relative z-10 mx-4 pointer-events-auto cursor-auto border-t-4 border-danger">
                                                    <div class="w-14 h-14 rounded-full bg-danger/10 flex items-center justify-center mx-auto mb-4 border border-danger/20">
                                                        <svg class="w-7 h-7 text-danger pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    </div>
                                                    <h3 class="text-lg font-bold text-primary-dark mb-2">Action Denied</h3>
                                                    <p class="text-sm text-text-muted mb-8 leading-relaxed font-medium" x-text="alertModal.message"></p>
                                                    <div class="flex justify-center">
                                                        <button type="button" @click.stop="alertModal.open = false" class="px-5 py-2.5 bg-surface-subtle text-text-main font-bold rounded-xl hover:bg-brand-light/30 transition-colors text-sm w-full cursor-pointer">Understood</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex justify-between items-center p-6 border-b border-border-subtle bg-surface shrink-0 relative z-20">
                                                <div>
                                                    <h2 class="text-xl font-bold text-primary-dark">Update Product</h2>
                                                    @if($isApprovedProduct)
                                                        <p class="text-xs text-text-muted mt-0.5">Any newly added variants or subvariants on an approved product will require admin approval before becoming public.</p>
                                                    @endif
                                                </div>
                                                <button type="button" @click.stop="editModal = false" class="p-2 text-text-muted hover:text-danger rounded-full hover:bg-danger/10 transition-colors cursor-pointer">
                                                    <svg class="w-6 h-6 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>

                                            <form action="{{ route('seller.products.update', $product->id) }}" method="POST" enctype="multipart/form-data"
                                                  x-ref="editForm"
                                                  x-init="$watch('editModal', val => { if(val) setTimeout(() => initForm($refs.editForm), 100) })"
                                                  @input="validateForm($refs.editForm)"
                                                  @change="validateForm($refs.editForm)"
                                                  @submit="if(hasValidationErrors() || !isFormValid) { $event.preventDefault(); }"
                                                  class="flex flex-col flex-1 overflow-hidden relative z-10">
                                                @csrf @method('PUT')
                                                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar bg-surface-subtle/30 flex-1 relative z-10">
                                                    <div x-show="hasValidationErrors()" x-cloak class="p-4 bg-danger/10 border-l-4 border-danger rounded-r-xl shadow-2xs flex items-start gap-3">
                                                        <svg class="w-6 h-6 text-danger shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        <div>
                                                            <h4 class="text-sm font-bold text-danger uppercase tracking-wider">Validation Error</h4>
                                                            <p class="text-xs text-text-main mt-1 font-medium italic">Please ensure 1 to 10 main pictures are present and all variant/subvariant names and photos are filled and unique.</p>
                                                        </div>
                                                    </div>

                                                    @if($approvalStatus === 'Disapproved')
                                                        <div class="p-4 bg-danger/10 border-l-4 border-danger rounded-r-xl shadow-2xs">
                                                            <div class="flex items-start gap-3">
                                                                <svg class="w-6 h-6 text-danger shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                                <div>
                                                                    <h4 class="text-sm font-bold text-danger uppercase tracking-wider">Disapproved ({{ $product->approval->disapproval_type }})</h4>
                                                                    <p class="text-xs text-text-main mt-1 font-medium italic">Admin Feedback: "{{ $product->approval->remarks }}"</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <div class="bg-surface p-6 rounded-2xl border border-border-subtle shadow-2xs space-y-5">
                                                        <h3 class="text-lg font-bold text-primary-dark">Basic Information</h3>
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                            <div>
                                                                <label class="block text-sm font-bold text-text-main mb-1">Product Name <span class="text-danger">*</span></label>
                                                                <input type="text" name="name" maxlength="100" value="{{ $product->name }}" {{ $isApprovedProduct ? 'readonly' : 'required' }}
                                                                       :class="hasError('name') ? 'border-danger focus:border-danger' : 'border-border-subtle focus:border-primary'"
                                                                       class="w-full p-3 rounded-xl border outline-none text-sm cursor-text {{ $isApprovedProduct ? 'bg-surface-subtle text-text-muted cursor-not-allowed' : 'bg-surface' }}">
                                                                <span x-show="hasError('name')" x-cloak class="text-danger text-[10px] font-bold mt-1 block">Product Name is required.</span>
                                                            </div>
                                                            <div x-data="{ subDropdownOpen: false, selectedSub: '{{ addslashes($product->subcategory ?? '') }}' }" class="relative">
                                                                <label class="block text-sm font-bold text-text-main mb-1">Subcategory ({{ $productCategoryName }}) <span class="text-danger">*</span></label>
                                                                <input type="hidden" name="subcategory" :value="selectedSub" required>
                                                                <button type="button" {{ $isApprovedProduct ? 'disabled' : '' }}
                                                                        @click.stop="if(!{{ $isApprovedProduct ? 'true' : 'false' }}) { subDropdownOpen = !subDropdownOpen; touched['subcategory'] = true; }"
                                                                        @click.away="subDropdownOpen = false"
                                                                        :class="hasError('subcategory') ? 'border-danger' : 'border-border-subtle hover:border-primary/50'"
                                                                        class="w-full p-3 rounded-xl border focus:border-primary outline-none bg-surface text-sm flex justify-between items-center transition-colors shadow-2xs {{ $isApprovedProduct ? 'bg-surface-subtle cursor-not-allowed' : 'cursor-pointer' }}">
                                                                    <span x-text="selectedSub || 'Select a Subcategory'" :class="selectedSub ? 'text-text-main font-medium' : 'text-text-muted'" class="pointer-events-none"></span>
                                                                    @if(!$isApprovedProduct)
                                                                        <svg class="w-4 h-4 text-text-muted transition-transform duration-200 pointer-events-none" :class="subDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                                    @endif
                                                                </button>
                                                                <span x-show="hasError('subcategory')" x-cloak class="text-danger text-[10px] font-bold mt-1 block">Subcategory is required.</span>
                                                                @if(!$isApprovedProduct)
                                                                    <div x-show="subDropdownOpen" x-cloak class="absolute z-[150] w-full mt-2 bg-surface border border-border-subtle rounded-xl shadow-xl max-h-60 overflow-y-auto custom-scrollbar">
                                                                        @foreach($productSubcategories as $sub)
                                                                            <div @click.stop="selectedSub = '{{ addslashes($sub['name']) }}'; subDropdownOpen = false; setTimeout(() => validateForm($refs.editForm), 50);"
                                                                                 class="px-4 py-3 text-sm cursor-pointer transition-colors flex items-center justify-between"
                                                                                 :class="selectedSub === '{{ addslashes($sub['name']) }}' ? 'bg-primary/10 text-primary font-bold' : 'text-text-main hover:bg-brand-light/30'">
                                                                                <span class="pointer-events-none">{{ $sub['name'] }}</span>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <div class="space-y-3">
                                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                                <div>
                                                                    <label class="block text-sm font-bold text-text-main">
                                                                        Main Product Pictures (Min 1, Max 10 — 1st Image is Cover Photo)
                                                                    </label>
                                                                    <p class="text-xs text-text-muted">
                                                                        Click <strong>Set Cover</strong> to promote any picture to slot #1. Variant images are managed separately below.
                                                                    </p>
                                                                </div>
                                                                <span class="px-3 py-1 rounded-full text-xs font-extrabold border"
                                                                      :class="totalMainPicturesCount >= 1 && totalMainPicturesCount <= 10 ? 'bg-primary/10 text-primary border-primary/20' : 'bg-danger/10 text-danger border-danger/25'"
                                                                      x-text="totalMainPicturesCount + ' / 10 Main Pictures'"></span>
                                                            </div>
                                                            <template x-for="(picPath, pIdx) in existingPictures" :key="'hidden_' + pIdx + '_' + picPath">
                                                                <input type="hidden" name="existing_pictures[]" :value="picPath">
                                                            </template>
                                                            <template x-for="(b64Pic, bIdx) in newPictureBase64" :key="'new_b64_' + bIdx">
                                                                <input type="hidden" name="pictures_base64[]" :value="b64Pic">
                                                            </template>
                                                            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-3 bg-surface-subtle p-3.5 rounded-2xl border border-border-subtle">
                                                                <template x-for="(picPath, pIdx) in existingPictures" :key="picPath + '_' + pIdx">
                                                                    <div :class="pIdx === 0 ? 'border-primary ring-2 ring-primary/20' : 'border-border-subtle'"
                                                                         class="relative rounded-xl border-2 bg-surface overflow-hidden shadow-2xs flex flex-col">
                                                                        <div class="relative aspect-square w-full overflow-hidden bg-surface-subtle">
                                                                            <img :src="'/storage/' + picPath" class="w-full h-full object-cover pointer-events-none">
                                                                            <span x-show="pIdx === 0" class="absolute top-1.5 left-1.5 px-2 py-0.5 rounded-full bg-primary text-white text-[9px] font-extrabold uppercase">
                                                                                Cover (#1)
                                                                            </span>
                                                                            <span x-show="pIdx > 0" class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded-full bg-primary-dark/85 text-white text-[9px] font-bold" x-text="'#' + (pIdx + 1)"></span>
                                                                            @if(!$isApprovedProduct)
                                                                                <button type="button"
                                                                                        @click.stop="removeExistingPicture(pIdx)"
                                                                                        class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-danger text-white text-xs font-bold flex items-center justify-center cursor-pointer">
                                                                                    &times;
                                                                                </button>
                                                                            @endif
                                                                        </div>
                                                                        <div class="p-1.5 bg-surface border-t border-border-subtle flex items-center justify-between text-[10px]">
                                                                            <button type="button"
                                                                                    x-show="pIdx > 0"
                                                                                    @click.stop="promoteExistingToCover(pIdx)"
                                                                                    class="px-1.5 py-0.5 rounded bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold cursor-pointer">
                                                                                Set Cover
                                                                            </button>
                                                                            <span x-show="pIdx === 0" class="text-primary font-extrabold px-1">Cover</span>
                                                                            <div class="flex items-center gap-0.5 ml-auto">
                                                                                <button type="button" :disabled="pIdx === 0" @click.stop="moveExistingPicture(pIdx, pIdx - 1)" class="px-1 rounded hover:bg-surface-subtle disabled:opacity-30 cursor-pointer">&larr;</button>
                                                                                <button type="button" :disabled="pIdx === existingPictures.length - 1" @click.stop="moveExistingPicture(pIdx, pIdx + 1)" class="px-1 rounded hover:bg-surface-subtle disabled:opacity-30 cursor-pointer">&rarr;</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                                <template x-for="(newPrev, npIdx) in newPicturePreviews" :key="'new_' + npIdx">
                                                                    <div class="relative rounded-xl border-2 border-primary bg-surface overflow-hidden shadow-2xs flex flex-col">
                                                                        <div class="relative aspect-square w-full overflow-hidden bg-surface-subtle">
                                                                            <img :src="newPrev" class="w-full h-full object-cover pointer-events-none">
                                                                            <span class="absolute top-1.5 left-1.5 px-2 py-0.5 rounded-full bg-primary text-white text-[9px] font-extrabold uppercase">
                                                                                New
                                                                            </span>
                                                                            <button type="button"
                                                                                    @click.stop="removeEditNewPicture(npIdx)"
                                                                                    class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-danger text-white text-xs font-bold flex items-center justify-center cursor-pointer">
                                                                                &times;
                                                                            </button>
                                                                        </div>
                                                                        <div class="p-1.5 bg-brand-light/30 text-primary-dark text-[9px] font-bold text-center">
                                                                            WebP Optimized
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                            <div x-show="totalMainPicturesCount < 10">
                                                                <input x-ref="editMainPicturesInput"
                                                                       type="file"
                                                                       :name="newPictureBase64.length > 0 ? null : 'pictures[]'"
                                                                       multiple
                                                                       accept="image/jpeg,image/png,image/jpg,image/webp"
                                                                       @change="handleEditNewPictures($event)"
                                                                       class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface-subtle text-xs cursor-pointer file:cursor-pointer file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                                                            </div>
                                                            <template x-if="pictureError">
                                                                <p class="text-danger text-xs font-bold" x-text="pictureError"></p>
                                                            </template>
                                                        </div>

                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                            <div>
                                                                <label class="block text-sm font-bold text-text-main mb-1">Set Discount (%)</label>
                                                                <div class="relative">
                                                                    <input type="number" step="0.01" min="0" max="99" name="discount" x-model="discountPercent"
                                                                           onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                           :class="hasError('discount') ? 'border-danger focus:border-danger' : 'border-border-subtle focus:border-primary'"
                                                                           class="w-full pr-8 p-3 rounded-xl border bg-surface outline-none text-sm cursor-text">
                                                                    <span class="absolute right-3 top-3 text-text-muted font-bold pointer-events-none">%</span>
                                                                </div>
                                                                <span x-show="hasError('discount')" x-cloak class="text-danger text-[10px] font-bold mt-1 block">Invalid discount.</span>
                                                            </div>
                                                            <div class="p-3 bg-brand-light/15 border border-brand-light/40 rounded-xl shadow-inner flex flex-col justify-center">
                                                                <div class="flex justify-between items-center text-xs mb-1">
                                                                    <span class="text-text-muted">Base Price:</span>
                                                                    <span class="font-bold text-text-main">&#8369;<span x-text="basePrice"></span></span>
                                                                </div>
                                                                <div class="flex justify-between items-center">
                                                                    <span class="text-primary font-bold text-sm">Discounted Price:</span>
                                                                    <span class="text-lg font-bold text-price">&#8369;<span x-text="editDiscountedPrice"></span></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @if($productVariants && isset($productVariants['items']))
                                                        <div class="bg-surface p-6 rounded-2xl border border-border-subtle shadow-2xs space-y-5 relative">
                                                            <h3 class="text-lg font-bold text-primary-dark">Edit Product Variants</h3>
                                                            <div class="space-y-4">
                                                                @foreach($productVariants['items'] as $vIndex => $item)
                                                                    @php
                                                                        $vKey = 'v_' . $vIndex;
                                                                        $vEffStatus = !$isApprovedProduct ? $approvalStatus : ($item['status'] ?? 'Approved');
                                                                    @endphp
                                                                    @if(!empty($item['is_archived']) || $vEffStatus === 'Disapproved') @continue @endif
                                                                    <div x-show="!isVariantArchived('{{ $vKey }}')" class="variant-container p-5 bg-surface rounded-2xl border border-border-subtle shadow-2xs relative">
                                                                        <input type="hidden" name="variant_pictures_base64[{{ $vIndex }}]" :value="existingVariantBase64['{{ $vIndex }}'] || ''">
                                                                        @if($vEffStatus === 'Pending')
                                                                            <span class="absolute top-3 right-3 px-2.5 py-0.5 bg-brand-light/80 text-primary-dark text-[10px] font-bold rounded-md border border-primary/30 z-10 pointer-events-none">Pending Review</span>
                                                                        @elseif($vEffStatus === 'Approved')
                                                                            <span class="absolute top-3 right-3 px-2.5 py-0.5 bg-success/15 text-success text-[10px] font-bold rounded-md border border-success/25 z-10 pointer-events-none">Approved</span>
                                                                        @endif
                                                                        <div class="flex flex-col sm:flex-row sm:items-start gap-6 px-2 pt-2">
                                                                            <div class="flex-1">
                                                                                <label class="block text-xs font-bold text-text-main mb-1">{{ $productVariants['title'] ?? 'Color Name' }}</label>
                                                                                <input type="text" name="variant_names[{{ $vIndex }}]"
                                                                                       x-model="existingVariants['{{ $vKey }}'].name"
                                                                                       :required="!isVariantArchived('{{ $vKey }}')"
                                                                                       placeholder="{{ ($productVariants['title'] ?? 'Variant') . ' Name' }}"
                                                                                       :class="(isVariantDuplicate(existingVariants['{{ $vKey }}']?.name) || hasError('variant_names[{{ $vIndex }}]')) ? 'border-danger focus:border-danger text-danger' : 'border-border-subtle focus:border-primary text-text-main'"
                                                                                       class="w-full p-2.5 text-sm rounded-lg border outline-none transition-colors bg-surface cursor-text">
                                                                                <span x-show="hasError('variant_names[{{ $vIndex }}]')" x-cloak class="text-danger text-[10px] font-bold mt-1 block">Name is required.</span>
                                                                                <span x-show="isVariantDuplicate(existingVariants['{{ $vKey }}']?.name)" class="text-danger text-[10px] font-bold block mt-1 pointer-events-none">Variant name must be unique.</span>
                                                                            </div>
                                                                            <div>
                                                                                <label class="block text-xs font-bold text-text-main mb-1">Dedicated Variant Image</label>
                                                                                <div class="flex items-center gap-2">
                                                                                    <img :src="existingVariantPreviews['{{ $vIndex }}'] || '{{ !empty($item['image']) ? asset('storage/'.$item['image']) : '' }}'"
                                                                                         x-show="Boolean(existingVariantPreviews['{{ $vIndex }}'] || '{{ !empty($item['image']) ? '1' : '' }}')"
                                                                                         class="h-10 w-10 object-cover rounded-lg border border-border-subtle shadow-2xs shrink-0 pointer-events-none">
                                                                                    @if(!$isApprovedProduct || $vEffStatus !== 'Approved')
                                                                                        <input type="file"
                                                                                               :name="existingVariantBase64['{{ $vIndex }}'] ? null : 'variant_pictures[{{ $vIndex }}]'"
                                                                                               accept="image/jpeg,image/png,image/jpg,image/webp"
                                                                                               @change="handleExistingVariantImage($event, '{{ $vIndex }}')"
                                                                                               class="w-full p-1.5 text-xs rounded-lg border border-border-subtle cursor-pointer file:cursor-pointer file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-surface-subtle file:text-text-main file:text-[10px] file:font-bold bg-surface">
                                                                                    @endif
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <hr class="border-border-subtle my-4">
                                                                        @if($priceDependency === 'main')
                                                                            <div class="flex flex-wrap gap-3 px-2">
                                                                                <div class="flex flex-col w-32">
                                                                                    <div class="relative w-full">
                                                                                        <span class="absolute left-2.5 top-2.5 text-text-muted text-xs font-bold pointer-events-none">&#8369;</span>
                                                                                        <input type="number" step="0.01" min="20" name="variant_prices[{{ $vIndex }}]" x-model="existingVariants['{{ $vKey }}'].price" :required="!isVariantArchived('{{ $vKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                                               :class="hasError('variant_prices[{{ $vIndex }}]') ? 'border-danger focus:border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                               class="w-full pl-6 p-2 text-sm rounded-lg border bg-surface outline-none no-spinners text-text-main cursor-text" placeholder="Price">
                                                                                    </div>
                                                                                    <span x-show="hasError('variant_prices[{{ $vIndex }}]')" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required (Min &#8369;20)</span>
                                                                                </div>
                                                                                <div class="flex flex-col w-28">
                                                                                    <div class="relative w-full">
                                                                                        <input type="number" step="0.01" min="0.01" name="variant_weights[{{ $vIndex }}]" x-model="existingVariants['{{ $vKey }}'].weight" :required="!isVariantArchived('{{ $vKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                                               :class="hasError('variant_weights[{{ $vIndex }}]') ? 'border-danger focus:border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                               class="w-full pr-8 p-2 text-sm rounded-lg border bg-surface outline-none no-spinners text-text-main cursor-text" placeholder="Weight">
                                                                                        <span class="absolute right-2.5 top-2.5 text-text-muted text-xs font-bold pointer-events-none">KG</span>
                                                                                    </div>
                                                                                    <span x-show="hasError('variant_weights[{{ $vIndex }}]')" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                </div>
                                                                                <div class="flex flex-col w-24">
                                                                                    <div class="relative w-full">
                                                                                        <input type="number" min="0" name="variant_stocks[{{ $vIndex }}]" x-model="existingVariants['{{ $vKey }}'].stock" :required="!isVariantArchived('{{ $vKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                                                                                               :class="hasError('variant_stocks[{{ $vIndex }}]') ? 'border-danger focus:border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                               class="w-full p-2 text-sm rounded-lg border bg-surface outline-none no-spinners text-center text-text-main cursor-text" placeholder="Stock">
                                                                                    </div>
                                                                                    <span x-show="hasError('variant_stocks[{{ $vIndex }}]')" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                </div>
                                                                            </div>
                                                                            <hr class="border-border-subtle my-4">
                                                                        @endif

                                                                        @if($hasSubvariantsArchitecture)
                                                                            <div class="px-2">
                                                                                <div class="flex items-center justify-between mb-3">
                                                                                    <label class="block text-[10px] font-bold text-text-main uppercase tracking-widest pointer-events-none">{{ $productVariants['sub_title'] ?? 'SIZES' }}</label>
                                                                                    <span x-show="!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === ''" class="text-[10px] text-danger font-medium italic">(Fill main variant name first)</span>
                                                                                </div>
                                                                                @if(isset($item['subs']) && is_array($item['subs']))
                                                                                    @foreach($item['subs'] as $sIndex => $sub)
                                                                                        @php
                                                                                            $sKey = 's_' . $sIndex;
                                                                                            $sEffStatus = !$isApprovedProduct ? $approvalStatus : ($vEffStatus !== 'Approved' ? $vEffStatus : ($sub['status'] ?? 'Approved'));
                                                                                        @endphp
                                                                                        @if(!empty($sub['is_archived']) || $sEffStatus === 'Disapproved') @continue @endif
                                                                                        <div x-show="!isSubArchived('{{ $vKey }}', '{{ $sKey }}')" class="flex flex-col sm:flex-row items-start gap-3 bg-brand-light/10 p-3 rounded-xl border border-brand-light/30 shadow-2xs mb-3 relative mt-3">
                                                                                            @if($sEffStatus === 'Pending')
                                                                                                <span class="absolute -top-2.5 left-3 px-2 py-0.5 bg-brand-light/90 border border-primary/30 text-primary-dark text-[9px] font-bold rounded-md z-10 pointer-events-none">Pending</span>
                                                                                            @elseif($sEffStatus === 'Approved')
                                                                                                <span class="absolute -top-2.5 left-3 px-2 py-0.5 bg-success/15 border border-success/25 text-success text-[9px] font-bold rounded-md z-10 pointer-events-none">Approved</span>
                                                                                            @endif
                                                                                            <div class="flex-1 w-full min-w-[160px] pt-1">
                                                                                                <input type="text" name="sub_variant_names[{{ $vIndex }}][{{ $sIndex }}]"
                                                                                                       x-model="existingVariants['{{ $vKey }}'].subs['{{ $sKey }}'].name"
                                                                                                       :required="!isVariantArchived('{{ $vKey }}') && !isSubArchived('{{ $vKey }}', '{{ $sKey }}')"
                                                                                                       placeholder="{{ ($productVariants['sub_title'] ?? 'Subvariant') . ' Name' }}"
                                                                                                       :class="(isExistingVariantSubDuplicate('{{ $vKey }}', existingVariants['{{ $vKey }}']?.subs?.['{{ $sKey }}']?.name) || hasError('sub_variant_names[{{ $vIndex }}][{{ $sIndex }}]')) ? 'border-danger focus:border-danger text-danger bg-surface' : 'border-border-subtle focus:border-primary bg-surface text-text-main'"
                                                                                                       class="w-full p-2 text-xs rounded-md border outline-none font-medium transition-colors cursor-text">
                                                                                                <span x-show="hasError('sub_variant_names[{{ $vIndex }}][{{ $sIndex }}]')" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                <span x-show="isExistingVariantSubDuplicate('{{ $vKey }}', existingVariants['{{ $vKey }}']?.subs?.['{{ $sKey }}']?.name)" class="text-danger text-[10px] font-bold block mt-1 pointer-events-none">Sub-variant must be unique.</span>
                                                                                            </div>
                                                                                            @if($priceDependency === 'sub')
                                                                                                <div class="flex flex-wrap gap-2 w-full sm:w-auto pt-1">
                                                                                                    <div class="flex flex-col min-w-[100px] flex-1">
                                                                                                        <div class="relative w-full">
                                                                                                            <span class="absolute left-2.5 top-2 text-text-muted text-xs font-bold pointer-events-none">&#8369;</span>
                                                                                                            <input type="number" step="0.01" min="20" name="sub_variant_prices[{{ $vIndex }}][{{ $sIndex }}]" x-model="existingVariants['{{ $vKey }}'].subs['{{ $sKey }}'].price" :required="!isVariantArchived('{{ $vKey }}') && !isSubArchived('{{ $vKey }}', '{{ $sKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                                                                   :class="hasError('sub_variant_prices[{{ $vIndex }}][{{ $sIndex }}]') ? 'border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                                   class="w-full pl-6 p-2 text-xs rounded-md border outline-none no-spinners bg-surface text-text-main cursor-text" placeholder="Price">
                                                                                                        </div>
                                                                                                        <span x-show="hasError('sub_variant_prices[{{ $vIndex }}][{{ $sIndex }}]')" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                    </div>
                                                                                                    <div class="flex flex-col min-w-[90px] flex-1">
                                                                                                        <div class="relative w-full">
                                                                                                            <input type="number" step="0.01" min="0.01" name="sub_variant_weights[{{ $vIndex }}][{{ $sIndex }}]" x-model="existingVariants['{{ $vKey }}'].subs['{{ $sKey }}'].weight" :required="!isVariantArchived('{{ $vKey }}') && !isSubArchived('{{ $vKey }}', '{{ $sKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                                                                   :class="hasError('sub_variant_weights[{{ $vIndex }}][{{ $sIndex }}]') ? 'border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                                   class="w-full pr-7 p-2 text-xs rounded-md border outline-none no-spinners bg-surface text-text-main cursor-text" placeholder="Weight">
                                                                                                            <span class="absolute right-2 top-2 text-text-muted text-xs font-bold pointer-events-none">KG</span>
                                                                                                        </div>
                                                                                                        <span x-show="hasError('sub_variant_weights[{{ $vIndex }}][{{ $sIndex }}]')" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                    </div>
                                                                                                    <div class="flex flex-col min-w-[80px] flex-1">
                                                                                                        <div class="relative w-full">
                                                                                                            <input type="number" min="0" name="sub_variant_stocks[{{ $vIndex }}][{{ $sIndex }}]" x-model="existingVariants['{{ $vKey }}'].subs['{{ $sKey }}'].stock" :required="!isVariantArchived('{{ $vKey }}') && !isSubArchived('{{ $vKey }}', '{{ $sKey }}')" onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                                                                                                                   :class="hasError('sub_variant_stocks[{{ $vIndex }}][{{ $sIndex }}]') ? 'border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                                   class="w-full p-2 text-xs rounded-md border outline-none no-spinners bg-surface text-center text-text-main cursor-text" placeholder="Stock">
                                                                                                        </div>
                                                                                                        <span x-show="hasError('sub_variant_stocks[{{ $vIndex }}][{{ $sIndex }}]')" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                    </div>
                                                                                            @endif
                                                                                            <button type="button" @click.stop="requestRemoveSubVariant('{{ $vKey }}', '{{ $sKey }}')" title="Remove Subvariant" class="p-2 text-text-muted hover:text-danger hover:bg-danger/10 rounded-lg transition-colors mt-0.5 cursor-pointer shrink-0">
                                                                                                <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                                            </button>
                                                                                            <input type="hidden" name="archive_sub_variants[{{ $vIndex }}][{{ $sIndex }}]" :value="isSubArchived('{{ $vKey }}', '{{ $sKey }}') ? 1 : 0">
                                                                                            @if($priceDependency === 'sub')
                                                                                                </div>
                                                                                            @endif
                                                                                        </div>
                                                                                    @endforeach
                                                                                @endif

                                                                                <div class="mt-4 space-y-3">
                                                                                    <template x-for="(ns, nsIndex) in getNewSubsForVariant('{{ $vKey }}')" :key="ns.id">
                                                                                        <div class="space-y-2 bg-brand-light/10 p-3 rounded-xl border border-primary/40 shadow-2xs relative mt-3">
                                                                                            <span class="absolute -top-2.5 left-3 px-2 py-0.5 bg-brand-light border border-primary/30 text-primary-dark text-[9px] font-bold rounded-md z-10 shadow-2xs pointer-events-none">
                                                                                                {{ $isApprovedProduct ? 'New Subvariant — Pending Approval' : 'New Subvariant' }}
                                                                                            </span>
                                                                                            <div x-show="!ns.isCopied && getFirstSubTemplateForExisting('{{ $vKey }}')" class="mt-1">
                                                                                                <div x-show="ns.promptState === 'active'" x-transition class="p-2 bg-brand-light/40 border border-primary/25 rounded-lg flex flex-wrap items-center justify-between gap-2 text-xs text-primary-dark shadow-2xs">
                                                                                                    <div class="flex items-center gap-2">
                                                                                                        <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                                                        <span>Copy Price, Weight, and Stock from 1st subvariant (<strong x-text="getFirstSubTemplateForExisting('{{ $vKey }}')?.name || '1st Subvariant'"></strong>)?</span>
                                                                                                    </div>
                                                                                                    <div class="flex gap-1.5">
                                                                                                        <button type="button" @click.stop="copyFirstSubToExistingNew('{{ $vKey }}', nsIndex)" class="px-3 py-1 bg-primary text-white font-bold rounded-md hover:bg-primary-dark transition-colors cursor-pointer text-[11px] shadow-2xs">Copy</button>
                                                                                                        <button type="button" @click.stop="ns.promptState = 'remind_later'" class="px-2.5 py-1 bg-surface border border-primary/30 text-primary font-bold rounded-md hover:bg-brand-light/30 transition-colors cursor-pointer text-[11px]">Not Now</button>
                                                                                                        <button type="button" @click.stop="ns.isCopied = true; ns.promptState = 'dismissed'" class="px-2.5 py-1 bg-surface-subtle text-text-muted font-bold rounded-md hover:bg-brand-light/20 transition-colors cursor-pointer text-[11px]">Dismiss</button>
                                                                                                    </div>
                                                                                                </div>
                                                                                                <div x-show="ns.promptState === 'remind_later'" class="flex justify-end mt-1">
                                                                                                    <button type="button" @click.stop="ns.promptState = 'active'" class="text-[10px] text-primary hover:text-primary-dark underline font-bold cursor-pointer">Show automation prompt</button>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="flex flex-col sm:flex-row items-start gap-3 pt-1">
                                                                                                <div class="flex-1 w-full min-w-[160px]">
                                                                                                    <input type="text" :name="`new_existing_sub_variant_names[{{ $vIndex }}][${ns.id}]`" x-model="ns.name" required
                                                                                                           :disabled="!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === ''"
                                                                                                           placeholder="{{ ($productVariants['sub_title'] ?? 'Subvariant') . ' Name (e.g. 128GB)' }}"
                                                                                                           :class="(isExistingVariantSubDuplicate('{{ $vKey }}', ns.name) || hasError(`new_existing_sub_variant_names[{{ $vIndex }}][${ns.id}]`)) ? 'border-danger focus:border-danger text-danger ' : 'border-border-subtle focus:border-primary ' + ((!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === '') ? 'bg-surface-subtle text-text-muted cursor-not-allowed' : 'bg-surface text-text-main')"
                                                                                                           class="w-full p-2 text-xs rounded-md border outline-none font-medium transition-colors">
                                                                                                    <span x-show="hasError(`new_existing_sub_variant_names[{{ $vIndex }}][${ns.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                    <span x-show="isExistingVariantSubDuplicate('{{ $vKey }}', ns.name)" class="text-danger text-[10px] font-bold block mt-1 pointer-events-none">Sub-variant must be unique.</span>
                                                                                                </div>
                                                                                                @if($priceDependency === 'sub')
                                                                                                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                                                                                                        <div class="flex flex-col min-w-[100px] flex-1">
                                                                                                            <div class="relative w-full">
                                                                                                                <span class="absolute left-2.5 top-2 text-text-muted text-xs font-bold pointer-events-none">&#8369;</span>
                                                                                                                <input type="number" step="0.01" min="20" :name="`new_existing_sub_variant_prices[{{ $vIndex }}][${ns.id}]`" x-model="ns.price" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" placeholder="Price"
                                                                                                                       :class="hasError(`new_existing_sub_variant_prices[{{ $vIndex }}][${ns.id}]`) ? 'border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                                       class="w-full pl-6 p-2 text-xs rounded-md border outline-none no-spinners bg-surface text-text-main cursor-text">
                                                                                                            </div>
                                                                                                            <span x-show="hasError(`new_existing_sub_variant_prices[{{ $vIndex }}][${ns.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                        </div>
                                                                                                        <div class="flex flex-col min-w-[90px] flex-1">
                                                                                                            <div class="relative w-full">
                                                                                                                <input type="number" step="0.01" min="0.01" :name="`new_existing_sub_variant_weights[{{ $vIndex }}][${ns.id}]`" x-model="ns.weight" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" placeholder="Weight"
                                                                                                                       :class="hasError(`new_existing_sub_variant_weights[{{ $vIndex }}][${ns.id}]`) ? 'border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                                       class="w-full pr-7 p-2 text-xs rounded-md border outline-none no-spinners bg-surface text-text-main cursor-text">
                                                                                                                <span class="absolute right-2 top-2 text-text-muted text-xs font-bold pointer-events-none">KG</span>
                                                                                                            </div>
                                                                                                            <span x-show="hasError(`new_existing_sub_variant_weights[{{ $vIndex }}][${ns.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                        </div>
                                                                                                        <div class="flex flex-col min-w-[80px] flex-1">
                                                                                                            <div class="relative w-full">
                                                                                                                <input type="number" min="0" :name="`new_existing_sub_variant_stocks[{{ $vIndex }}][${ns.id}]`" x-model="ns.stock" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57)" placeholder="Stock"
                                                                                                                       :class="hasError(`new_existing_sub_variant_stocks[{{ $vIndex }}][${ns.id}]`) ? 'border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                                       class="w-full p-2 text-xs rounded-md border outline-none no-spinners bg-surface text-center text-text-main cursor-text">
                                                                                                            </div>
                                                                                                            <span x-show="hasError(`new_existing_sub_variant_stocks[{{ $vIndex }}][${ns.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                        </div>
                                                                                                        <button type="button" @click.stop="removeNewSubFromVariant('{{ $vKey }}', ns.id)" title="Remove Subvariant" class="p-2 text-text-muted hover:text-danger hover:bg-danger/10 rounded-lg transition-colors cursor-pointer shrink-0 h-[34px]">
                                                                                                            <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                                                                        </button>
                                                                                                    </div>
                                                                                                @endif
                                                                                            </div>
                                                                                        </div>
                                                                                    </template>
                                                                                    <button type="button"
                                                                                            @click.stop="existingVariants['{{ $vKey }}']?.name?.trim() !== '' ? addNewSubToVariant('{{ $vKey }}') : null"
                                                                                            :disabled="!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === ''"
                                                                                            :class="(!existingVariants['{{ $vKey }}']?.name || existingVariants['{{ $vKey }}'].name.trim() === '') ? 'text-text-muted/50 cursor-not-allowed opacity-60' : 'text-primary hover:underline cursor-pointer'"
                                                                                            class="text-sm font-bold tracking-wide mt-1 inline-block">
                                                                                        + Add New Subvariant
                                                                                    </button>
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                        <hr class="border-border-subtle mt-5 mb-4">
                                                                        <div class="flex justify-end px-2">
                                                                            <button type="button" @click.stop="requestRemoveVariant('{{ $vKey }}')" class="flex items-center gap-1.5 text-sm text-text-muted hover:text-danger font-bold transition-colors cursor-pointer">
                                                                                <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                                Remove Variant
                                                                            </button>
                                                                            <input type="hidden" name="archive_variants[{{ $vIndex }}]" :value="isVariantArchived('{{ $vKey }}') ? 1 : 0">
                                                                        </div>
                                                                    </div>
                                                                @endforeach

                                                                <div class="mt-6">
                                                                    <template x-for="(nv, nvIndex) in newVariants" :key="nv.id">
                                                                        <div class="p-5 bg-surface rounded-2xl border border-primary/40 shadow-2xs mb-4 relative mt-4">
                                                                            <input type="hidden" :name="`new_variant_pictures_base64[${nv.id}]`" :value="nv.imageBase64 || ''">
                                                                            <span class="absolute -top-3 left-5 px-2.5 py-0.5 bg-brand-light border border-primary/30 text-primary-dark text-[10px] font-bold rounded-lg shadow-2xs z-10 pointer-events-none">
                                                                                {{ $isApprovedProduct ? 'New Variant — Pending Approval' : 'New Variant Block' }}
                                                                            </span>

                                                                            @if($priceDependency === 'main')
                                                                                <div x-show="!nv.isCopied && getFirstMainVariantTemplate(nv.id)" class="px-2 pt-2 mb-3">
                                                                                    <div x-show="nv.promptState === 'active'" x-transition class="p-2.5 bg-brand-light/40 border border-primary/25 rounded-xl flex flex-wrap items-center justify-between gap-2 text-xs text-primary-dark shadow-2xs">
                                                                                        <div class="flex items-center gap-2">
                                                                                            <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                                            <span>Do you want to copy the 1st variant's descriptions (Price, Weight, and Stock) from <strong x-text="getFirstMainVariantTemplate(nv.id)?.name || '1st Variant'"></strong>?</span>
                                                                                        </div>
                                                                                        <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                                                                                            <button type="button" @click.stop="copyFirstMainToNewVariant(nv)" class="px-3 py-1 bg-primary text-white font-bold rounded-lg hover:bg-primary-dark transition-colors cursor-pointer shadow-2xs">Copy</button>
                                                                                            <button type="button" x-show="newVariants.length > 1" @click.stop="copyFirstMainToAllNewVariants()" class="px-3 py-1 bg-primary-dark text-white font-bold rounded-lg hover:bg-primary transition-colors cursor-pointer shadow-2xs">Copy to All</button>
                                                                                            <button type="button" @click.stop="nv.promptState = 'remind_later'" class="px-3 py-1 bg-surface border border-primary/30 text-primary font-bold rounded-lg hover:bg-brand-light/30 transition-colors cursor-pointer">Not Now</button>
                                                                                            <button type="button" @click.stop="nv.isCopied = true; nv.promptState = 'dismissed'" class="px-3 py-1 bg-surface-subtle text-text-muted font-bold rounded-lg hover:bg-brand-light/20 transition-colors cursor-pointer">Dismiss</button>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div x-show="nv.promptState === 'remind_later'" class="flex justify-end mt-1">
                                                                                        <button type="button" @click.stop="nv.promptState = 'active'" class="text-[10px] text-primary hover:text-primary-dark underline font-bold cursor-pointer">Show automation prompt</button>
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <div class="flex flex-col sm:flex-row sm:items-start gap-6 px-2 mt-2">
                                                                                <div class="flex-1">
                                                                                    <label class="block text-xs font-bold text-text-main mb-1">New {{ $productVariants['title'] ?? 'Variant' }} Name <span class="text-danger">*</span></label>
                                                                                    <input type="text" :name="`new_variant_names[${nv.id}]`" x-model="nv.name" required
                                                                                           placeholder="{{ ($productVariants['title'] ?? 'Variant') . ' Name (e.g. Red)' }}"
                                                                                           :class="(isVariantDuplicate(nv.name) || hasError(`new_variant_names[${nv.id}]`)) ? 'border-danger focus:border-danger text-danger' : 'border-border-subtle focus:border-primary'"
                                                                                           class="w-full p-2.5 text-sm rounded-lg border outline-none transition-colors bg-surface text-text-main">
                                                                                    <span x-show="hasError(`new_variant_names[${nv.id}]`)" x-cloak class="text-danger text-[10px] font-bold mt-1 block">Name is required.</span>
                                                                                    <span x-show="isVariantDuplicate(nv.name)" class="text-danger text-[10px] font-bold block mt-1 pointer-events-none">Variant name must be unique.</span>
                                                                                </div>
                                                                                <div>
                                                                                    <label class="block text-xs font-bold text-text-main mb-1">Dedicated Variant Image <span class="text-danger">*</span></label>
                                                                                    <div class="flex items-center gap-2">
                                                                                        <template x-if="nv.imagePreview">
                                                                                            <img :src="nv.imagePreview" class="h-10 w-10 object-cover rounded-lg border border-primary/40 shadow-2xs shrink-0 pointer-events-none">
                                                                                        </template>
                                                                                        <input type="file"
                                                                                               :name="nv.imageBase64 ? null : `new_variant_pictures[${nv.id}]`"
                                                                                               accept="image/jpeg,image/png,image/jpg,image/webp"
                                                                                               :required="!nv.imageBase64 && !nv.imagePreview"
                                                                                               @change="handleNewVariantImage($event, nv)"
                                                                                               :class="(!nv.imageBase64 && !nv.imagePreview && hasError(`new_variant_pictures[${nv.id}]`)) ? 'border-danger' : 'border-border-subtle'"
                                                                                               class="w-full p-1.5 text-xs rounded-lg border cursor-pointer file:cursor-pointer file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-surface-subtle file:text-text-main file:text-[10px] file:font-bold bg-surface focus:outline-none">
                                                                                    </div>
                                                                                    <span x-show="!nv.imageBase64 && !nv.imagePreview && hasError(`new_variant_pictures[${nv.id}]`)" x-cloak class="text-danger text-[10px] font-bold mt-1 block">Image is required.</span>
                                                                                    <span x-show="nv.imageError" x-cloak class="text-danger text-[10px] font-bold mt-1 block" x-text="nv.imageError"></span>
                                                                                </div>
                                                                            </div>
                                                                            <hr class="border-border-subtle my-4">
                                                                            @if($priceDependency === 'main')
                                                                                <div class="flex flex-wrap gap-3 px-2">
                                                                                    <div class="flex flex-col w-32">
                                                                                        <div class="relative w-full">
                                                                                            <span class="absolute left-2.5 top-2.5 text-text-muted text-xs font-bold pointer-events-none">&#8369;</span>
                                                                                            <input type="number" step="0.01" min="20" :name="`new_variant_prices[${nv.id}]`" x-model="nv.price" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                                                   :class="hasError(`new_variant_prices[${nv.id}]`) ? 'border-danger focus:border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                   class="w-full pl-6 p-2 text-sm rounded-lg border bg-surface outline-none no-spinners text-text-main cursor-text" placeholder="Price">
                                                                                        </div>
                                                                                        <span x-show="hasError(`new_variant_prices[${nv.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required (Min &#8369;20)</span>
                                                                                    </div>
                                                                                    <div class="flex flex-col w-28">
                                                                                        <div class="relative w-full">
                                                                                            <input type="number" step="0.01" min="0.01" :name="`new_variant_weights[${nv.id}]`" x-model="nv.weight" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                                                   :class="hasError(`new_variant_weights[${nv.id}]`) ? 'border-danger focus:border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                   class="w-full pr-8 p-2 text-sm rounded-lg border bg-surface outline-none no-spinners text-text-main cursor-text" placeholder="Weight">
                                                                                            <span class="absolute right-2.5 top-2.5 text-text-muted text-xs font-bold pointer-events-none">KG</span>
                                                                                        </div>
                                                                                        <span x-show="hasError(`new_variant_weights[${nv.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                    </div>
                                                                                    <div class="flex flex-col w-24">
                                                                                        <div class="relative w-full">
                                                                                            <input type="number" min="0" :name="`new_variant_stocks[${nv.id}]`" x-model="nv.stock" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                                                                                                   :class="hasError(`new_variant_stocks[${nv.id}]`) ? 'border-danger focus:border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                   class="w-full p-2 text-sm rounded-lg border bg-surface outline-none no-spinners text-center text-text-main cursor-text" placeholder="Stock">
                                                                                        </div>
                                                                                        <span x-show="hasError(`new_variant_stocks[${nv.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                    </div>
                                                                                </div>
                                                                                <hr class="border-border-subtle my-4">
                                                                            @endif
                                                                            <template x-if="nv.subs !== undefined">
                                                                                <div class="px-2 space-y-3">
                                                                                    <div x-show="!nv.isSubsCopied && getFirstSubListTemplate(nv.id)" class="mb-2">
                                                                                        <div x-show="nv.subPromptState === 'active'" x-transition class="p-2.5 bg-brand-light/40 border border-primary/25 rounded-xl flex flex-wrap items-center justify-between gap-2 text-xs text-primary-dark shadow-2xs">
                                                                                            <div class="flex items-center gap-2">
                                                                                                <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                                                <span>Save time! Copy all <strong>{{ $productVariants['sub_title'] ?? 'Subvariants' }}</strong> (Names, Prices, Weights, and Stocks) from <strong x-text="getFirstSubListTemplate(nv.id)?.name || '1st Variant'"></strong>?</span>
                                                                                            </div>
                                                                                            <div class="flex flex-wrap gap-1.5 mt-2 sm:mt-0">
                                                                                                <button type="button" @click.stop="copySubListToNewVariant(nv)" class="px-3 py-1 bg-primary text-white font-bold rounded-lg hover:bg-primary-dark transition-colors cursor-pointer shadow-2xs">Copy to This Variant</button>
                                                                                                <button type="button" x-show="newVariants.length > 1" @click.stop="copySubListToAllNewVariants()" class="px-3 py-1 bg-primary-dark text-white font-bold rounded-lg hover:bg-primary transition-colors cursor-pointer shadow-2xs">Copy to All New Variants</button>
                                                                                                <button type="button" @click.stop="nv.subPromptState = 'remind_later'" class="px-2.5 py-1 bg-surface border border-primary/30 text-primary font-bold rounded-lg hover:bg-brand-light/30 transition-colors cursor-pointer">Not Now</button>
                                                                                                <button type="button" @click.stop="nv.isSubsCopied = true; nv.subPromptState = 'dismissed'" class="px-2.5 py-1 bg-surface-subtle text-text-muted font-bold rounded-lg hover:bg-brand-light/20 transition-colors cursor-pointer">Dismiss</button>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div x-show="nv.subPromptState === 'remind_later'" class="flex justify-end mt-1">
                                                                                            <button type="button" @click.stop="nv.subPromptState = 'active'" class="text-[10px] text-primary hover:text-primary-dark underline font-bold cursor-pointer">Show sub-variant copy prompt</button>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div class="flex justify-between items-center mb-2">
                                                                                        <div class="flex items-center gap-2">
                                                                                            <label class="block text-[10px] font-bold text-text-main uppercase tracking-widest pointer-events-none">{{ $productVariants['sub_title'] ?? 'Subvariants' }}</label>
                                                                                            <span x-show="nv.name.trim() === ''" class="text-[10px] text-danger font-medium italic">(Fill main variant name first)</span>
                                                                                        </div>
                                                                                        <button type="button" @click.stop="nv.name.trim() !== '' ? addSubToNewVariant(nv) : null" :disabled="nv.name.trim() === ''" :class="nv.name.trim() === '' ? 'text-text-muted/50 cursor-not-allowed opacity-60' : 'text-primary hover:text-primary-dark cursor-pointer'" class="text-xs font-bold transition-colors">+ Add {{ $productVariants['sub_title'] ?? 'Subvariant' }}</button>
                                                                                    </div>
                                                                                    <template x-for="(sub, subIndex) in nv.subs" :key="sub.id">
                                                                                        <div class="space-y-2 bg-brand-light/10 p-3 rounded-xl border border-brand-light/30 relative">
                                                                                            <div x-show="subIndex > 0 && !sub.isCopied && nv.subs[0] && (nv.subs[0].price || nv.subs[0].weight || nv.subs[0].stock)" class="mb-1">
                                                                                                <div x-show="sub.promptState === 'active'" x-transition class="p-2 bg-brand-light/40 border border-primary/25 rounded-lg flex flex-wrap items-center justify-between gap-2 text-xs text-primary-dark shadow-2xs">
                                                                                                    <div class="flex items-center gap-2">
                                                                                                        <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                                                        <span>Copy Price, Weight, and Stock from 1st subvariant (<strong x-text="nv.subs[0].name || '1st Subvariant'"></strong>)?</span>
                                                                                                    </div>
                                                                                                    <div class="flex gap-1.5">
                                                                                                        <button type="button" @click.stop="copyFirstSubInNewVariant(nv, subIndex)" class="px-3 py-1 bg-primary text-white font-bold rounded-md hover:bg-primary-dark transition-colors cursor-pointer text-[11px] shadow-2xs">Copy</button>
                                                                                                        <button type="button" @click.stop="sub.promptState = 'remind_later'" class="px-2.5 py-1 bg-surface border border-primary/30 text-primary font-bold rounded-md hover:bg-brand-light/30 transition-colors cursor-pointer text-[11px]">Not Now</button>
                                                                                                        <button type="button" @click.stop="sub.isCopied = true; sub.promptState = 'dismissed'" class="px-2.5 py-1 bg-surface-subtle text-text-muted font-bold rounded-md hover:bg-brand-light/20 transition-colors cursor-pointer text-[11px]">Dismiss</button>
                                                                                                    </div>
                                                                                                </div>
                                                                                                <div x-show="sub.promptState === 'remind_later'" class="flex justify-end mt-1">
                                                                                                    <button type="button" @click.stop="sub.promptState = 'active'" class="text-[10px] text-primary hover:text-primary-dark underline font-bold cursor-pointer">Show automation prompt</button>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="flex flex-col sm:flex-row items-start gap-3">
                                                                                                <div class="flex-1 w-full min-w-[160px]">
                                                                                                    <input type="text" :name="`new_sub_variant_names[${nv.id}][${sub.id}]`" x-model="sub.name" required :disabled="nv.name.trim() === ''"
                                                                                                           placeholder="{{ ($productVariants['sub_title'] ?? 'Subvariant') . ' Name (e.g. 128GB)' }}"
                                                                                                           :class="(isNewVariantSubDuplicate(nv, sub.name) || hasError(`new_sub_variant_names[${nv.id}][${sub.id}]`)) ? 'border-danger focus:border-danger text-danger ' : 'border-border-subtle focus:border-primary ' + (nv.name.trim() === '' ? 'bg-surface-subtle text-text-muted cursor-not-allowed' : 'bg-surface text-text-main')"
                                                                                                           class="w-full p-2 text-xs rounded-md border outline-none font-medium transition-colors">
                                                                                                    <span x-show="hasError(`new_sub_variant_names[${nv.id}][${sub.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                    <span x-show="isNewVariantSubDuplicate(nv, sub.name)" class="text-danger text-[10px] font-bold block mt-1 pointer-events-none">Sub-variant must be unique.</span>
                                                                                                </div>
                                                                                                @if($priceDependency === 'sub')
                                                                                                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                                                                                                        <div class="flex flex-col min-w-[100px] flex-1">
                                                                                                            <div class="relative w-full">
                                                                                                                <span class="absolute left-2.5 top-2 text-text-muted text-xs font-bold pointer-events-none" :class="nv.name.trim() === '' ? 'opacity-50' : ''">&#8369;</span>
                                                                                                                <input type="number" step="0.01" min="20" :name="`new_sub_variant_prices[${nv.id}][${sub.id}]`" x-model="sub.price" required :disabled="nv.name.trim() === ''" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                                                                       :class="hasError(`new_sub_variant_prices[${nv.id}][${sub.id}]`) ? 'border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                                       class="w-full pl-6 p-2 text-xs rounded-md border outline-none no-spinners bg-surface cursor-text" placeholder="Price">
                                                                                                            </div>
                                                                                                            <span x-show="hasError(`new_sub_variant_prices[${nv.id}][${sub.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                        </div>
                                                                                                        <div class="flex flex-col min-w-[90px] flex-1">
                                                                                                            <div class="relative w-full">
                                                                                                                <input type="number" step="0.01" min="0.01" :name="`new_sub_variant_weights[${nv.id}][${sub.id}]`" x-model="sub.weight" required :disabled="nv.name.trim() === ''" onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                                                                       :class="hasError(`new_sub_variant_weights[${nv.id}][${sub.id}]`) ? 'border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                                       class="w-full pr-7 p-2 text-xs rounded-md border outline-none no-spinners bg-surface cursor-text" placeholder="Weight">
                                                                                                                <span class="absolute right-2 top-2 text-text-muted text-xs font-bold pointer-events-none" :class="nv.name.trim() === '' ? 'opacity-50' : ''">KG</span>
                                                                                                            </div>
                                                                                                            <span x-show="hasError(`new_sub_variant_weights[${nv.id}][${sub.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                        </div>
                                                                                                        <div class="flex flex-col min-w-[80px] flex-1">
                                                                                                            <div class="relative w-full">
                                                                                                                <input type="number" min="0" :name="`new_sub_variant_stocks[${nv.id}][${sub.id}]`" x-model="sub.stock" required :disabled="nv.name.trim() === ''" onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                                                                                                                       :class="hasError(`new_sub_variant_stocks[${nv.id}][${sub.id}]`) ? 'border-danger' : 'border-border-subtle focus:border-primary'"
                                                                                                                       class="w-full p-2 text-xs rounded-md border outline-none no-spinners bg-surface text-center text-text-main cursor-text" placeholder="Stock">
                                                                                                            </div>
                                                                                                            <span x-show="hasError(`new_sub_variant_stocks[${nv.id}][${sub.id}]`)" x-cloak class="text-danger text-[9px] font-bold mt-1 block">Required</span>
                                                                                                        </div>
                                                                                                        <button type="button" @click.stop="removeNewVariantSub(nv, subIndex)" title="Remove Subvariant" class="p-2 text-text-muted hover:text-danger hover:bg-danger/10 rounded-lg transition-colors cursor-pointer shrink-0 h-[34px]">
                                                                                                            <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                                                                        </button>
                                                                                                    </div>
                                                                                                @endif
                                                                                            </div>
                                                                                        </div>
                                                                                    </template>
                                                                                </div>
                                                                            </template>
                                                                            <hr class="border-border-subtle mt-5 mb-4">
                                                                            <div class="flex justify-end px-2">
                                                                                <button type="button" @click.stop="newVariants.splice(nvIndex, 1); setTimeout(() => validateForm($refs.editForm), 50);" class="flex items-center gap-1.5 text-sm text-text-muted hover:text-danger font-bold transition-colors cursor-pointer">
                                                                                    <svg class="w-4 h-4 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                                    Drop Entire Variant
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </template>
                                                                    <button type="button" @click.stop="addVariant(); setTimeout(() => validateForm($refs.editForm), 50);" class="w-full py-4 bg-primary/5 hover:bg-primary/10 text-primary font-bold rounded-2xl border-2 border-primary/30 border-dashed transition-colors cursor-pointer tracking-wide shadow-2xs">
                                                                        + Add New Variant
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <div x-data="{
                                                        enableSpecs: {{ $product->specifications->count() > 0 ? 'true' : 'false' }},
                                                        specs: {{ $product->specifications->count() > 0 ? $existingSpecs : "[{ id: Date.now(), title: '', desc: '' }]" }},
                                                        addSpec() { this.specs.push({ id: Date.now() + '_' + Math.random(), title: '', desc: '' }); },
                                                        removeSpec(index) { this.specs.splice(index, 1); }
                                                    }" class="bg-surface p-6 rounded-2xl border border-border-subtle shadow-2xs space-y-4">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <h3 class="text-sm font-bold text-text-main">Specifications <span class="text-xs font-normal italic">(Optional)</span></h3>
                                                            <button type="button" @click="enableSpecs = !enableSpecs; if(enableSpecs && specs.length === 0) addSpec(); setTimeout(() => validateForm($refs.editForm), 50);"
                                                                    class="px-4 py-1.5 text-xs font-bold rounded-lg border transition-colors cursor-pointer"
                                                                    :class="enableSpecs ? 'bg-danger/10 text-danger border-danger/25 hover:bg-danger hover:text-white' : 'bg-primary/10 text-primary border-primary/20 hover:bg-primary hover:text-white'">
                                                                <span x-text="enableSpecs ? 'Disable Specifications' : 'Enable Specifications'"></span>
                                                            </button>
                                                        </div>
                                                        <div x-show="enableSpecs" x-collapse class="space-y-3">
                                                            <template x-for="(spec, index) in specs" :key="spec.id">
                                                                <div class="flex items-start gap-3">
                                                                    <div class="flex-1">
                                                                        <input type="text" :name="`specifications[${index}][title]`" :disabled="!enableSpecs" x-model="spec.title" placeholder="Specification Title (e.g. RAM)" class="w-full p-2.5 text-sm rounded-xl border border-border-subtle bg-surface focus:border-primary outline-none">
                                                                    </div>
                                                                    <div class="flex-1">
                                                                        <input type="text" :name="`specifications[${index}][description]`" :disabled="!enableSpecs" x-model="spec.desc" placeholder="Specification Description (e.g. 8GB)" class="w-full p-2.5 text-sm rounded-xl border border-border-subtle bg-surface focus:border-primary outline-none">
                                                                    </div>
                                                                    <button type="button" @click="requestConfirm('Are you sure you want to remove this specification?', () => { removeSpec(index); })" class="p-2.5 text-text-muted hover:text-danger rounded-lg hover:bg-danger/10 transition-colors mt-0.5 cursor-pointer">
                                                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                                    </button>
                                                                </div>
                                                            </template>
                                                            <button type="button" @click="addSpec" class="text-xs font-bold text-primary hover:text-primary-dark cursor-pointer">+ Add another specification</button>
                                                        </div>
                                                    </div>

                                                    <div class="bg-surface p-6 rounded-2xl border border-border-subtle shadow-2xs space-y-4">
                                                        <div>
                                                            <label class="block text-sm font-bold text-text-main mb-1">Standard Description</label>
                                                            <textarea name="description" rows="4" class="w-full p-3 rounded-xl border border-border-subtle bg-surface focus:border-primary outline-none custom-scrollbar text-sm cursor-text">{{ $product->description }}</textarea>
                                                        </div>
                                                        <div x-data="{ enableAddDesc: {{ !empty($product->additional_descriptions) ? 'true' : 'false' }} }">
                                                            <div class="flex items-center justify-between mb-2">
                                                                <label class="block text-sm font-bold text-text-main">Additional Descriptions <span class="text-xs font-normal italic">(Optional)</span></label>
                                                                <button type="button" @click="enableAddDesc = !enableAddDesc" class="text-xs text-primary font-bold hover:underline cursor-pointer" x-text="enableAddDesc ? 'Disable Field' : 'Enable Field'"></button>
                                                            </div>
                                                            <textarea name="additional_descriptions" rows="3" :disabled="!enableAddDesc" :class="!enableAddDesc ? 'bg-surface-subtle cursor-not-allowed opacity-70' : 'bg-surface cursor-text'" class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none custom-scrollbar text-sm">{{ $product->additional_descriptions }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="p-6 border-t border-border-subtle flex justify-end gap-3 bg-surface shrink-0 relative z-[110]">
                                                    <button type="button" @click.stop="editModal = false" class="px-6 py-3 rounded-xl font-bold text-text-muted hover:bg-surface-subtle transition-colors cursor-pointer text-sm">Cancel</button>
                                                    <button type="submit" :disabled="hasValidationErrors() || !isFormValid" :class="(hasValidationErrors() || !isFormValid) ? 'bg-surface-subtle text-text-muted opacity-50 cursor-not-allowed' : 'bg-primary hover:bg-primary-dark text-white cursor-pointer'" class="px-6 py-3 rounded-xl shadow-md transition-colors font-bold text-sm">
                                                        @if($approvalStatus === 'Disapproved')
                                                            Save &amp; Resubmit
                                                        @else
                                                            Save Changes
                                                        @endif
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-12 text-center text-text-muted">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-border-subtle mb-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                        <p>No products found in {{ $selectedCategory }}. Add some to get started.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mb-8 cursor-pointer">
                {{ $products->links() }}
            </div>
        </div>
    @endif
@endsection