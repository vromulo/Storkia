{{--
    Report Seller modal (shared by the storefront, product page and order page).

    Include it ONCE per page:

        <x-buyer.report-seller-modal />

    Then open it from any button on that page. The button does not need any x-data of its own:

        <button type="button"
                @click="$dispatch('open-report-seller', { sellerId: {{ $seller->id }}, sellerName: @js($seller->business_name) })">
            Report Seller
        </button>

    On the order page the order can be preselected too (it is only applied when it
    belongs to this buyer and this seller):

        $dispatch('open-report-seller', { sellerId: ..., sellerName: ..., orderId: {{ $sellerOrder->id }} })

    sellerId is the SellerProfile id (the same id used by route('sellers.show', ...)).

    The modal talks to Buyer\SellerReportController:
        GET  sellers.report.context  -> the buyer's orders from this seller + any open report
        POST sellers.report.store    -> submits the report (multipart, JSON reply)
    It is teleported to <body>, like the other modals, so no parent can clip it.
--}}
@php
    $reportUser            = auth()->user();
    $reportIsAuthenticated = (bool) $reportUser;
    $reportIsBuyer         = $reportUser && $reportUser->role === 'Buyer';
    $reportCategories      = \App\Models\SellerReport::categoryCatalog();
    $reportConfig          = [
        'isAuthenticated' => $reportIsAuthenticated,
        'isBuyer'         => $reportIsBuyer,
        'loginUrl'        => route('login'),
        'contextUrl'      => route('sellers.report.context', ['seller' => '__SELLER__']),
        'storeUrl'        => route('sellers.report.store', ['seller' => '__SELLER__']),
        'minDescription'  => \App\Models\SellerReport::MIN_DESCRIPTION_LENGTH,
        'maxDescription'  => \App\Models\SellerReport::MAX_DESCRIPTION_LENGTH,
        'maxPhotos'       => \App\Models\SellerReport::MAX_EVIDENCE_PHOTOS,
        'maxPhotoMb'      => (int) (\App\Models\SellerReport::MAX_EVIDENCE_KB / 1024),
    ];
@endphp

<script>
    window.storkiaReportSeller = window.storkiaReportSeller || function (config) {
        // File objects are kept OUT of Alpine's reactive state: a reactive proxy around a File
        // is not a real Blob, and FormData.append() would throw on it.
        const rawFiles = {};
        let fileCounter = 0;
        let loadToken = 0;

        return {
            config: config,

            open: false,
            // loading | form | already | success | guest | blocked | error
            step: 'loading',

            sellerId: null,
            sellerName: 'this store',
            preselectOrder: '',

            orders: [],
            openReport: null,
            myReportsUrl: '',

            category: '',
            description: '',
            orderId: '',
            files: [],

            errors: {},
            banner: '',
            submitting: false,
            successNumber: '',

            get descriptionLength() {
                return this.description.trim().length;
            },

            get canSubmit() {
                return !this.submitting
                    && this.category !== ''
                    && this.descriptionLength >= this.config.minDescription
                    && this.descriptionLength <= this.config.maxDescription;
            },

            url(template) {
                return template.replace('__SELLER__', encodeURIComponent(this.sellerId));
            },

            csrf() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            },

            resetForm() {
                this.files.forEach((entry) => URL.revokeObjectURL(entry.url));
                Object.keys(rawFiles).forEach((key) => delete rawFiles[key]);
                this.files = [];
                this.category = '';
                this.description = '';
                this.orderId = '';
                this.orders = [];
                this.openReport = null;
                this.errors = {};
                this.banner = '';
                this.submitting = false;
                this.successNumber = '';
            },

            openModal(detail) {
                this.resetForm();
                this.sellerId = detail.sellerId || null;
                this.sellerName = detail.sellerName || 'this store';
                this.preselectOrder = detail.orderId ? String(detail.orderId) : '';
                this.open = true;
                document.body.classList.add('overflow-hidden');

                if (!this.sellerId) {
                    this.step = 'error';
                    this.banner = 'We could not tell which store you want to report.';
                    return;
                }

                if (!this.config.isAuthenticated) {
                    this.step = 'guest';
                    return;
                }

                if (!this.config.isBuyer) {
                    this.step = 'blocked';
                    this.banner = 'Only buyer accounts can report a seller.';
                    return;
                }

                this.loadContext();
            },

            closeModal() {
                if (this.submitting) return;
                this.open = false;
                document.body.classList.remove('overflow-hidden');
            },

            async loadContext() {
                const token = ++loadToken;
                this.step = 'loading';

                try {
                    const res = await fetch(this.url(this.config.contextUrl), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (token !== loadToken) return;

                    // Expired session: the global session guard shows its own prompt.
                    if (res.status === 401 || res.status === 419) {
                        this.closeModal();
                        return;
                    }

                    const data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        this.step = 'error';
                        this.banner = data.message || 'We could not load the report form. Please try again.';
                        return;
                    }

                    if (!data.can_report) {
                        this.step = 'blocked';
                        this.banner = data.message || 'You cannot report this store.';
                        return;
                    }

                    this.orders = Array.isArray(data.orders) ? data.orders : [];
                    this.openReport = data.open_report || null;
                    this.myReportsUrl = data.my_reports_url || '';
                    if (data.seller_name) this.sellerName = data.seller_name;

                    if (data.has_open_report) {
                        this.step = 'already';
                        return;
                    }

                    if (this.preselectOrder && this.orders.some((order) => String(order.id) === this.preselectOrder)) {
                        this.orderId = this.preselectOrder;
                    }

                    this.step = 'form';
                } catch (err) {
                    if (token !== loadToken) return;
                    this.step = 'error';
                    this.banner = 'Network error. Please check your connection and try again.';
                }
            },

            addFiles(event) {
                this.errors.evidence_photos = '';

                const incoming = Array.from(event.target.files || []);
                event.target.value = '';

                const allowed = ['image/jpeg', 'image/png', 'image/webp'];
                const maxBytes = this.config.maxPhotoMb * 1024 * 1024;

                for (const file of incoming) {
                    if (this.files.length >= this.config.maxPhotos) {
                        this.errors.evidence_photos = 'You can attach up to ' + this.config.maxPhotos + ' photos.';
                        break;
                    }
                    if (!allowed.includes(file.type)) {
                        this.errors.evidence_photos = '"' + file.name + '" is not a JPG, PNG or WebP photo.';
                        continue;
                    }
                    if (file.size > maxBytes) {
                        this.errors.evidence_photos = '"' + file.name + '" is larger than ' + this.config.maxPhotoMb + 'MB.';
                        continue;
                    }

                    const id = 'photo_' + (++fileCounter);
                    rawFiles[id] = file;
                    this.files.push({ id: id, name: file.name, url: URL.createObjectURL(file) });
                }
            },

            removeFile(index) {
                const entry = this.files[index];
                if (!entry) return;
                URL.revokeObjectURL(entry.url);
                delete rawFiles[entry.id];
                this.files.splice(index, 1);
                this.errors.evidence_photos = '';
            },

            applyValidationErrors(errors) {
                const mapped = {};
                Object.keys(errors || {}).forEach((key) => {
                    const message = Array.isArray(errors[key]) ? errors[key][0] : String(errors[key]);
                    const field = key.indexOf('evidence_photos') === 0 ? 'evidence_photos' : key;
                    if (!mapped[field]) mapped[field] = message;
                });
                this.errors = mapped;
            },

            async submit() {
                if (!this.canSubmit) return;

                this.errors = {};
                this.banner = '';
                this.submitting = true;

                const body = new FormData();
                body.append('reason_category', this.category);
                body.append('description', this.description.trim());
                if (this.orderId) body.append('seller_order_id', this.orderId);
                this.files.forEach((entry) => body.append('evidence_photos[]', rawFiles[entry.id]));

                try {
                    const res = await fetch(this.url(this.config.storeUrl), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf()
                        },
                        body: body
                    });

                    if (res.status === 401 || res.status === 419) {
                        this.submitting = false;
                        this.closeModal();
                        return;
                    }

                    const data = await res.json().catch(() => ({}));

                    if (res.ok && data.success !== false) {
                        this.successNumber = data.report_number || '';
                        if (data.redirect) this.myReportsUrl = data.redirect;
                        this.step = 'success';
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: {
                                title: 'Report submitted',
                                message: 'Thank you for helping keep Storkia safe.',
                                type: 'success'
                            }
                        }));
                    } else if (res.status === 422) {
                        this.applyValidationErrors(data.errors);
                        this.banner = 'Please fix the highlighted fields and try again.';
                    } else if (res.status === 409) {
                        this.banner = data.message || 'You already have an open report against this store.';
                        this.step = 'already';
                    } else {
                        this.banner = data.message || 'Something went wrong while sending your report. Please try again.';
                    }
                } catch (err) {
                    this.banner = 'Network error. Please check your connection and try again.';
                } finally {
                    this.submitting = false;
                }
            }
        };
    };
</script>

<div x-data="storkiaReportSeller(@js($reportConfig))"
     @open-report-seller.window="openModal($event.detail || {})"
     class="hidden"
     aria-hidden="true">

    <template x-teleport="body">
        <div x-show="open"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @keydown.escape.window="open && closeModal()"
             @mousedown.self="closeModal()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="report_seller_title"
             class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4">

            <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto bg-surface border border-neutral-300 rounded-[28px] p-6 sm:p-8 shadow-xl">

                <!-- Close button -->
                <button type="button"
                        @click="closeModal()"
                        :disabled="submitting"
                        aria-label="Close"
                        class="absolute top-5 right-5 text-neutral-500 hover:text-text-main transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Header -->
                <div class="flex items-start gap-3 pr-8">
                    <div class="w-10 h-10 rounded-full bg-danger/10 text-danger flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                            <line x1="4" y1="22" x2="4" y2="15"></line>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 id="report_seller_title" class="text-xl sm:text-2xl font-bold text-text-main leading-tight break-words">
                            <span x-show="step !== 'success'">Report <span x-text="sellerName"></span></span>
                            <span x-show="step === 'success'">Report submitted</span>
                        </h3>
                        <p x-show="step === 'form'" class="text-xs text-text-muted mt-1 leading-relaxed">
                            Storkia reviews every report. The seller is not told who reported them.
                        </p>
                    </div>
                </div>

                <!-- Loading -->
                <div x-show="step === 'loading'"
                     class="py-10 flex items-center justify-center gap-1.5 text-sm text-text-muted font-medium">
                    <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
                    <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
                    <span class="w-1.5 h-1.5 bg-text-muted rounded-full animate-bounce"></span>
                    <span class="ml-1">Loading...</span>
                </div>

                <!-- Guest: must log in first -->
                <div x-show="step === 'guest'" x-cloak class="mt-4">
                    <p class="text-sm text-text-muted leading-relaxed">Please log in to your buyer account to report a seller.</p>
                    <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="h-11 px-5 rounded-xl border border-neutral-400 text-sm font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer">
                            Not now
                        </button>
                        <a :href="config.loginUrl"
                           class="h-11 px-5 rounded-xl bg-primary hover:bg-primary-dark text-white text-sm font-bold transition-colors inline-flex items-center justify-center">
                            Log in
                        </a>
                    </div>
                </div>

                <!-- Blocked (not a buyer, or own store) / Error -->
                <div x-show="step === 'blocked' || step === 'error'" x-cloak class="mt-4">
                    <p class="text-sm text-text-muted leading-relaxed" x-text="banner"></p>
                    <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="h-11 px-5 rounded-xl border border-neutral-400 text-sm font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer">
                            Close
                        </button>
                        <button type="button" x-show="step === 'error' && sellerId" @click="loadContext()"
                                class="h-11 px-5 rounded-xl bg-primary hover:bg-primary-dark text-white text-sm font-bold transition-colors cursor-pointer">
                            Try again
                        </button>
                    </div>
                </div>

                <!-- Already has an open report against this seller -->
                <div x-show="step === 'already'" x-cloak class="mt-4">
                    <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-sm leading-relaxed">
                        <p class="font-bold">You already have an open report against this store.</p>
                        <p class="mt-1" x-show="openReport">
                            Report <span class="font-mono font-bold" x-text="openReport ? openReport.number : ''"></span>
                            is currently <strong x-text="openReport ? openReport.status_label : ''"></strong>.
                        </p>
                        <p class="mt-1">You can send another report once Storkia has finished reviewing this one.</p>
                    </div>
                    <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="h-11 px-5 rounded-xl border border-neutral-400 text-sm font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer">
                            Close
                        </button>
                        <a :href="myReportsUrl" x-show="myReportsUrl"
                           class="h-11 px-5 rounded-xl bg-primary hover:bg-primary-dark text-white text-sm font-bold transition-colors inline-flex items-center justify-center">
                            View my reports
                        </a>
                    </div>
                </div>

                <!-- Success -->
                <div x-show="step === 'success'" x-cloak class="mt-4">
                    <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-800 text-sm leading-relaxed flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-success/10 text-success flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold">Thank you for letting us know.</p>
                            <p class="mt-1">
                                Your report <span class="font-mono font-bold" x-text="successNumber"></span>
                                is now waiting for Storkia to review. You will be notified when its status changes.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="h-11 px-5 rounded-xl border border-neutral-400 text-sm font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer">
                            Close
                        </button>
                        <a :href="myReportsUrl" x-show="myReportsUrl"
                           class="h-11 px-5 rounded-xl bg-primary hover:bg-primary-dark text-white text-sm font-bold transition-colors inline-flex items-center justify-center">
                            View my reports
                        </a>
                    </div>
                </div>

                <!-- Form -->
                <form x-show="step === 'form'" x-cloak @submit.prevent="submit()" class="mt-5 space-y-5 text-xs sm:text-sm">

                    <div x-show="banner" x-cloak class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 font-semibold" x-text="banner"></div>

                    <!-- 1. Reason -->
                    <div>
                        <label class="block font-bold text-text-main text-sm mb-2">
                            1. What are you reporting? <span class="text-danger">*</span>
                        </label>
                        <div class="space-y-2">
                            @foreach($reportCategories as $key => $reason)
                                <label class="p-3 rounded-xl border cursor-pointer flex items-start gap-2.5 transition-colors"
                                       :class="category === @js($key) ? 'border-primary bg-primary/5' : 'border-border-subtle hover:border-primary/40'">
                                    <input type="radio" name="report_seller_reason" value="{{ $key }}" x-model="category" class="mt-0.5">
                                    <span>
                                        <span class="block font-bold text-text-main">{{ $reason['label'] }}</span>
                                        <span class="block text-text-muted mt-0.5">{{ $reason['hint'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p x-show="errors.reason_category" x-cloak class="text-danger font-bold mt-1" x-text="errors.reason_category"></p>
                    </div>

                    <!-- 2. Related order (optional) -->
                    <div x-show="orders.length > 0" x-cloak>
                        <label for="report_seller_order" class="block font-bold text-text-main text-sm mb-1.5">
                            2. Related order <span class="text-text-muted font-medium">(optional)</span>
                        </label>
                        <select id="report_seller_order"
                                x-model="orderId"
                                class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary cursor-pointer">
                            <option value="">Not about a specific order</option>
                            <template x-for="order in orders" :key="order.id">
                                <option :value="String(order.id)"
                                        :selected="String(order.id) === orderId"
                                        x-text="'#' + order.number + ' - ' + order.status + (order.placed_at ? ' (' + order.placed_at + ')' : '')"></option>
                            </template>
                        </select>
                        <p x-show="errors.seller_order_id" x-cloak class="text-danger font-bold mt-1" x-text="errors.seller_order_id"></p>
                    </div>

                    <!-- 3. Description -->
                    <div>
                        <label for="report_seller_description" class="block font-bold text-text-main text-sm mb-1.5">
                            <span x-text="orders.length > 0 ? '3.' : '2.'"></span>
                            Describe what happened <span class="text-danger">*</span>
                        </label>
                        <textarea id="report_seller_description"
                                  x-model="description"
                                  rows="4"
                                  :maxlength="config.maxDescription"
                                  placeholder="Tell us what the seller did. Dates, product names and what was said all help."
                                  class="w-full p-3 rounded-xl border border-border-subtle bg-surface outline-none focus:border-primary"></textarea>
                        <div class="mt-1 flex items-center justify-between gap-3 text-[11px]">
                            <span :class="descriptionLength > 0 && descriptionLength < config.minDescription ? 'text-danger font-bold' : 'text-text-muted'"
                                  x-text="'At least ' + config.minDescription + ' characters'"></span>
                            <span class="text-text-muted" x-text="descriptionLength + ' / ' + config.maxDescription"></span>
                        </div>
                        <p x-show="errors.description" x-cloak class="text-danger font-bold mt-1" x-text="errors.description"></p>
                    </div>

                    <!-- 4. Evidence (optional) -->
                    <div>
                        <label class="block font-bold text-text-main text-sm mb-1.5">
                            <span x-text="orders.length > 0 ? '4.' : '3.'"></span>
                            Evidence photos
                            <span class="text-text-muted font-medium"
                                  x-text="'(optional, up to ' + config.maxPhotos + ' photos, ' + config.maxPhotoMb + 'MB each)'"></span>
                        </label>

                        <input id="report_seller_photos"
                               type="file"
                               multiple
                               accept="image/jpeg,image/png,image/webp"
                               @change="addFiles($event)"
                               class="hidden">

                        <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                            <template x-for="(photo, index) in files" :key="photo.id">
                                <div class="relative aspect-square rounded-xl overflow-hidden border border-border-subtle bg-surface-subtle">
                                    <img :src="photo.url" :alt="photo.name" class="w-full h-full object-cover">
                                    <button type="button"
                                            @click="removeFile(index)"
                                            :aria-label="'Remove ' + photo.name"
                                            class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 hover:bg-black/80 text-white flex items-center justify-center cursor-pointer">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <label for="report_seller_photos"
                                   x-show="files.length < config.maxPhotos"
                                   class="aspect-square rounded-xl border-2 border-dashed border-border-subtle hover:border-primary text-text-muted hover:text-primary flex flex-col items-center justify-center gap-1 cursor-pointer transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span class="text-[10px] font-bold">Add photo</span>
                            </label>
                        </div>
                        <p x-show="errors.evidence_photos" x-cloak class="text-danger font-bold mt-1" x-text="errors.evidence_photos"></p>
                    </div>

                    <!-- Actions -->
                    <div class="pt-1 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button type="button"
                                @click="closeModal()"
                                :disabled="submitting"
                                class="h-11 px-5 rounded-xl border border-neutral-400 text-sm font-semibold text-text-main hover:bg-surface-subtle transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="!canSubmit"
                                class="h-11 px-5 rounded-xl bg-danger hover:bg-red-700 text-white text-sm font-bold transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                            <span x-show="!submitting">Submit report</span>
                            <span x-show="submitting" x-cloak>Submitting...</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </template>
</div>