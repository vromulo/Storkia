@extends('layouts.seller', ['title' => 'Storkia - Add Product'])

@section('content')
    @php
        $categoriesList = $categoriesList ?? \App\Http\Controllers\Seller\ProductController::getCategoriesList();
        $primaryCategory = auth()->user()->sellerProfile->line_of_business ?? 'Pet';
        $sellerCategory = old('category', $selectedCategory ?? request()->query('category', $primaryCategory));
        if (!array_key_exists($sellerCategory, $categoriesList)) {
            $sellerCategory = $primaryCategory;
        }
        $subcategories = $categoriesList[$sellerCategory] ?? ($categoriesList[$primaryCategory] ?? []);
    @endphp

    @if($errors->any())
        <div x-data="{ showToast: true }"
             x-show="showToast"
             x-init="setTimeout(() => showToast = false, 7000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-[-20px] scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-[-20px] scale-95"
             class="fixed top-6 right-6 z-[300] max-w-md w-full bg-white rounded-2xl shadow-2xl border-l-8 border-red-500 p-4 flex items-start gap-3"
             x-cloak>
            <div class="p-2 bg-red-100 text-red-600 rounded-xl shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="flex-1 pr-2">
                <h4 class="font-bold text-gray-900 text-sm">Form Validation Alert</h4>
                <ul class="mt-1 list-disc list-inside text-xs text-red-600 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" @click="showToast = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    <div x-data="{
        imageModalOpen: false,
        imageModalSrc: '',
        touched: {},
        fieldErrors: {},
        isFormValid: false,
        initForm() {
            this.$refs.productForm.addEventListener('blur', (e) => {
                if (e.target.name) {
                    this.touched[e.target.name] = true;
                    this.validateForm();
                }
            }, true);
            this.validateForm();
        },
        validateForm() {
            this.$nextTick(() => {
                const form = this.$refs.productForm;
                if (!form) return;
                this.isFormValid = form.checkValidity();
                let errs = {};
                form.querySelectorAll('input, select, textarea').forEach(el => {
                    if (el.name && !el.validity.valid) {
                        errs[el.name] = true;
                    }
                });
                this.fieldErrors = errs;
            });
        },
        hasError(name) {
            return this.touched[name] && this.fieldErrors[name];
        }
    }" x-init="initForm()">
        <div class="max-w-5xl mx-auto">
            <div class="flex items-center mb-8">
                <a href="{{ route('seller.products.index', ['category' => $sellerCategory]) }}" class="mr-4 p-2 bg-white rounded-full shadow-sm border border-border-subtle hover:bg-brand-light/20 transition-colors text-text-muted hover:text-primary cursor-pointer">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-primary-dark">Add New Product</h1>
                    <p class="text-xs text-text-muted mt-0.5">Creating product under <strong>{{ $sellerCategory }}</strong> ({{ $sellerCategory === $primaryCategory ? 'Primary Category' : 'Other Category' }})</p>
                </div>
            </div>

            <form x-ref="productForm"
                  @input="validateForm"
                  @change="validateForm"
                  x-data="variantManager()"
                  action="{{ route('seller.products.store') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="bg-white rounded-3xl p-8 shadow-sm border border-border-subtle space-y-6">
                @csrf
                <input type="hidden" name="stock_quantity" :value="totalStock">

                <div>
                    <label class="block text-sm font-bold text-text-muted mb-1">Product Name (Max 100 chars) <span class="text-red-500">*</span></label>
                    <input type="text" name="name" maxlength="100" required value="{{ old('name') }}"
                           :class="hasError('name') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-border-subtle focus:border-primary focus:ring-primary'"
                           class="w-full p-3 rounded-xl border focus:ring-1 outline-none">
                    <span x-show="hasError('name')" x-cloak class="text-red-500 text-[10px] font-bold mt-1 block">Product Name is required.</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-text-muted mb-1">Main Category</label>
                        <input type="text" name="category" value="{{ $sellerCategory }}" readonly class="w-full p-3.5 rounded-2xl border border-gray-200 bg-gray-100 text-gray-500 font-bold outline-none cursor-not-allowed select-none">
                    </div>

                    <div x-data="searchableSelect({
                            selected: '{{ addslashes(old('subcategory', '')) }}',
                            options: {{ json_encode(array_column($subcategories, 'name')) }}
                         })"
                         @click.away="open = false"
                         class="relative w-full">

                        <label class="block text-sm font-bold text-text-muted mb-1">Subcategory <span class="text-red-500">*</span></label>
                        <input type="text" name="subcategory" x-model="selected" required tabindex="-1" class="absolute w-0 h-0 opacity-0 pointer-events-none -z-10">

                        <div @click="open = true; $refs.searchInput.focus(); touched['subcategory'] = true;"
                             :class="hasError('subcategory') ? 'border-red-500' : 'border-gray-300 hover:border-gray-400 focus-within:border-primary'"
                             class="w-full p-3 bg-white rounded-2xl border-2 transition-all flex items-center justify-between cursor-text shadow-sm relative">
                            <input x-ref="searchInput"
                                   type="text"
                                   x-model="search"
                                   @focus="open = true"
                                   @keydown.enter.prevent="if(filteredOptions.length > 0) { selectOption(filteredOptions[0]); setTimeout(() => validateForm(), 50); }"
                                   :placeholder="selected ? '' : 'Select or type to search...'"
                                   class="w-full bg-transparent outline-none text-text-main font-medium z-10 relative">

                            <div x-show="selected && !search" class="absolute left-3 right-10 top-0 bottom-0 flex items-center text-text-main font-medium pointer-events-none">
                                <span x-text="selected"></span>
                            </div>

                            <button type="button" @click.stop="open = !open" class="text-gray-500 hover:text-gray-700 ml-2 z-10 cursor-pointer">
                                <svg class="w-5 h-5 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                </svg>
                            </button>
                        </div>
                        <span x-show="hasError('subcategory')" x-cloak class="text-red-500 text-[10px] font-bold mt-1 block">Subcategory is required.</span>

                        <div x-show="open"
                             x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                             class="absolute z-50 left-0 right-0 mt-2 bg-white border border-gray-200 rounded-2xl shadow-xl max-h-60 overflow-y-auto custom-scrollbar p-1.5 divide-y divide-gray-100">
                            <template x-for="opt in filteredOptions" :key="opt">
                                <div @click="selectOption(opt); setTimeout(() => validateForm(), 50)"
                                     class="px-4 py-3 rounded-xl hover:bg-gray-50 cursor-pointer flex items-center justify-between transition-colors text-sm font-medium text-gray-800">
                                    <span x-text="opt"></span>
                                    <svg x-show="selected === opt" class="w-5 h-5 text-pink-600 font-bold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                            </template>
                            <div x-show="filteredOptions.length === 0" class="p-3 text-center text-xs text-gray-400 font-medium">
                                No subcategories found.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-text-muted mb-1">Discount <span class="text-xs font-normal italic">(Optional)</span></label>
                        <div class="relative flex flex-col w-full md:w-1/2">
                            <div class="relative w-full">
                                <input type="number" step="0.01" min="0" max="99" name="discount" value="{{ old('discount', 0) }}"
                                       onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                       :class="hasError('discount') ? 'border-red-500 focus:border-red-500' : 'border-border-subtle focus:border-primary'"
                                       class="w-full pr-9 p-3 rounded-xl border outline-none no-spinners cursor-text">
                                <span class="absolute right-4 top-3 text-text-muted font-bold">%</span>
                            </div>
                            <span x-show="hasError('discount')" x-cloak class="text-red-500 text-[10px] font-bold mt-1 block">Invalid discount value.</span>
                        </div>
                    </div>
                </div>

                <!-- Main Product Pictures (1 to 10 Images — 1st Image is Cover Photo) -->
                <div x-data="imageUploader(10)" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <label class="block text-sm font-bold text-text-muted">
                                Main Product Pictures (Min 1, Max 10 Images — 1st Image is Cover Photo) <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-text-muted mt-0.5">
                                These images form the main product gallery. Drag thumbnails or click <strong>Set as Cover</strong> so your desired Cover Photo is in slot #1. Variant images are uploaded separately below.
                            </p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-extrabold border"
                              :class="previews.length >= 1 && previews.length <= 10 ? 'bg-primary/10 text-primary border-primary/20' : 'bg-gray-100 text-text-muted border-border-subtle'"
                              x-text="previews.length + ' / 10 Main Pictures'"></span>
                    </div>

                    <div @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="handleDrop($event); touched['pictures[]'] = true; setTimeout(() => validateForm(), 100)"
                         :class="hasError('pictures[]') ? 'border-red-500 bg-red-50 scale-[1.005]' : (isDragging ? 'border-primary bg-primary/10 scale-[1.005]' : 'border-border-subtle bg-surface hover:bg-brand-light/10')"
                         class="border-2 border-dashed rounded-2xl p-8 text-center transition-all cursor-pointer relative flex flex-col items-center justify-center min-h-[190px]">

                        <input x-ref="mainFileInput"
                               type="file"
                               name="pictures[]"
                               multiple
                               accept="image/jpeg,image/png,image/jpg,image/webp"
                               required
                               @change="handleSelect($event); touched['pictures[]'] = true; setTimeout(() => validateForm(), 100)"
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                        <div class="flex flex-col items-center justify-center space-y-2.5 pointer-events-none">
                            <div class="p-4 bg-primary/10 text-primary rounded-full shadow-inner shrink-0 flex items-center justify-center">
                                <svg class="w-9 h-9 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <p class="text-base font-bold text-primary-dark">
                                <span class="text-primary underline">Choose 1 to 10 Images</span> or drag &amp; drop here
                            </p>
                            <p class="text-xs text-text-muted">Supports JPG, PNG, WEBP up to 5MB each (Automatically optimized to WebP)</p>
                        </div>
                    </div>

                    <span x-show="hasError('pictures[]')" x-cloak class="text-red-500 text-[10px] font-bold mt-1 block">At least 1 main product picture is required (maximum 10).</span>

                    <template x-if="errorMessage">
                        <p class="text-red-500 text-xs font-bold mt-2 flex items-center">
                            <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span x-text="errorMessage"></span>
                        </p>
                    </template>

                    <div x-show="previews.length > 0" x-cloak x-transition class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4 pt-2">
                        <template x-for="(preview, idx) in previews" :key="idx">
                            <div draggable="true"
                                 @dragstart="onThumbnailDragStart(idx)"
                                 @dragover.prevent
                                 @drop.prevent="onThumbnailDrop(idx)"
                                 :class="idx === 0 ? 'border-primary ring-2 ring-primary/30' : 'border-border-subtle'"
                                 class="relative group rounded-2xl border-2 bg-surface overflow-hidden shadow-sm flex flex-col">
                                <div class="relative aspect-square w-full overflow-hidden bg-surface-subtle">
                                    <img :src="preview"
                                         @click="imageModalSrc = preview; imageModalOpen = true"
                                         class="w-full h-full object-cover cursor-pointer hover:opacity-90 transition-all">

                                    <span x-show="idx === 0"
                                          class="absolute top-2 left-2 px-2.5 py-0.5 rounded-full bg-primary text-white text-[10px] font-extrabold uppercase shadow-sm pointer-events-none">
                                        Cover Photo (#1)
                                    </span>
                                    <span x-show="idx > 0"
                                          class="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-black/65 text-white text-[10px] font-bold pointer-events-none"
                                          x-text="'#' + (idx + 1)"></span>

                                    <button type="button"
                                            @click.stop="removeImage(idx); setTimeout(() => validateForm(), 50)"
                                            title="Remove image"
                                            class="absolute top-2 right-2 w-6 h-6 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center text-xs font-bold shadow-md cursor-pointer">
                                        &times;
                                    </button>
                                </div>

                                <div class="p-2 bg-white border-t border-border-subtle flex items-center justify-between gap-1 text-[10px]">
                                    <button type="button"
                                            x-show="idx > 0"
                                            @click.stop="promoteToCover(idx)"
                                            class="px-2 py-1 rounded-lg bg-primary/10 hover:bg-primary text-primary hover:text-white font-bold transition-colors cursor-pointer">
                                        Set as Cover
                                    </button>
                                    <span x-show="idx === 0" class="text-primary font-extrabold px-1">Main Cover</span>

                                    <div class="flex items-center gap-1 ml-auto">
                                        <button type="button"
                                                :disabled="idx === 0"
                                                @click.stop="moveImage(idx, idx - 1)"
                                                class="p-1 rounded hover:bg-surface-subtle disabled:opacity-30 cursor-pointer"
                                                title="Move left">
                                            &larr;
                                        </button>
                                        <button type="button"
                                                :disabled="idx === previews.length - 1"
                                                @click.stop="moveImage(idx, idx + 1)"
                                                class="p-1 rounded hover:bg-surface-subtle disabled:opacity-30 cursor-pointer"
                                                title="Move right">
                                            &rarr;
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Product Variants (Variant-Specific Images Kept Strictly Separate from Main Gallery) -->
                <div class="border border-border-subtle rounded-2xl p-6 bg-brand-light/10 space-y-6">
                    <div class="border-b border-border-subtle pb-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                            <h3 class="text-lg font-bold text-primary-dark">Product Variants</h3>
                            <span class="text-[11px] font-semibold text-text-muted">
                                Each variant image is displayed on the product page only when the buyer clicks that specific variant.
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                            <div>
                                <label class="block text-xs font-bold text-text-muted mb-1">Title of the Variants <span x-show="variants.length > 0" class="text-red-500">*</span></label>
                                <input type="text" name="variant_title" x-model="variantTitle" placeholder="e.g. Color, Units" :required="variants.length > 0"
                                       :class="hasError('variant_title') ? 'border-red-500 focus:border-red-500' : 'border-border-subtle focus:border-primary'"
                                       class="w-full p-2.5 rounded-lg border outline-none">
                                <span x-show="hasError('variant_title')" x-cloak class="text-red-500 text-[10px] font-bold mt-1 block">Title is required.</span>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-text-muted mb-1">Title of the Sub Variants (If necessary)</label>
                                <input type="text" name="sub_variant_title" x-model="subVariantTitle" placeholder="e.g. Size, RAM"
                                       :disabled="!variantTitle || variantTitle.trim() === ''"
                                       :class="(!variantTitle || variantTitle.trim() === '') ? 'bg-gray-100 cursor-not-allowed opacity-70 border-border-subtle text-gray-500' : 'bg-white border-border-subtle focus:border-primary'"
                                       class="w-full p-2.5 rounded-lg border outline-none transition-colors">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <template x-for="(v, vIndex) in variants" :key="v.id">
                            <div class="bg-white rounded-xl border border-border-subtle shadow-sm overflow-hidden">

                                <div x-show="vIndex > 0 && !v.isCopied && variants[0] && (variants[0].price || variants[0].weight || variants[0].stock) && priceDependsOn === 'main'"
                                     x-data="{ promptState: 'active' }"
                                     class="px-4 pt-4">
                                     <div x-show="promptState === 'active'" x-transition class="p-2.5 bg-blue-50 border border-blue-200 rounded-lg flex flex-wrap items-center justify-between gap-2 text-xs text-blue-900 shadow-sm">
                                         <div class="flex items-center gap-2">
                                             <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                             <span>Do you want to copy the previous variant's descriptions (Price, Weight, and Stock)?</span>
                                         </div>
                                         <div class="flex gap-2 mt-2 sm:mt-0">
                                             <button type="button" @click="v.price = variants[0].price; v.weight = variants[0].weight; v.stock = variants[0].stock; v.isCopied = true; promptState = 'copied'; setTimeout(() => validateForm(), 50);" class="px-3 py-1 bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 transition-colors cursor-pointer shadow-sm">Copy</button>
                                             <button type="button" @click="promptState = 'remind_later'" class="px-3 py-1 bg-white border border-blue-300 text-blue-600 font-bold rounded-md hover:bg-blue-50 transition-colors cursor-pointer shadow-sm">Not Now</button>
                                             <button type="button" @click="v.isCopied = true; promptState = 'dismissed'" class="px-3 py-1 bg-gray-100 text-gray-600 font-bold rounded-md hover:bg-gray-200 transition-colors cursor-pointer shadow-sm">Dismiss</button>
                                         </div>
                                     </div>
                                     <div x-show="promptState === 'remind_later'" class="flex justify-end mt-1">
                                         <button type="button" @click="promptState = 'active'" class="text-[10px] text-blue-500 hover:text-blue-700 underline font-bold">Show automation prompt</button>
                                     </div>
                                </div>

                                <div class="p-4 flex flex-col lg:flex-row lg:items-start gap-4 bg-surface/50 border-b border-border-subtle">
                                    <div class="w-full lg:w-48 shrink-0 flex flex-col">
                                        <input type="text" :name="`variant_names[${v.id}]`" x-model="v.name" :placeholder="`${variantTitle || 'Variant'} Name (e.g. Red)`" required
                                               :disabled="!variantTitle || variantTitle.trim() === ''"
                                               :class="(!variantTitle || variantTitle.trim() === '') ? 'bg-gray-100 text-gray-500 cursor-not-allowed border-border-subtle' : ((duplicateVariants.includes(v.name.trim().toLowerCase()) && v.name.trim() !== '') || hasError(`variant_names[${v.id}]`) ? 'border-red-500 focus:border-red-500 focus:ring-red-500 bg-white' : 'border-border-subtle focus:border-primary bg-white')"
                                               class="w-full p-2.5 text-sm rounded-lg border outline-none transition-colors">
                                        <span x-show="hasError(`variant_names[${v.id}]`)" x-cloak class="text-red-500 text-[10px] font-bold mt-1 block">Name is required.</span>
                                        <span x-show="duplicateVariants.includes(v.name.trim().toLowerCase()) && v.name.trim() !== ''" class="text-red-500 text-[10px] font-bold mt-1 block">Variant name must be unique.</span>
                                    </div>

                                    <div class="flex-1 flex flex-col min-w-[260px]">
                                        <div class="flex items-center gap-3">
                                            <template x-if="v.imagePreview">
                                                <img :src="v.imagePreview" @click="imageModalSrc = v.imagePreview; imageModalOpen = true" class="w-20 h-20 object-cover rounded-xl border-2 border-border-subtle shadow-sm flex-shrink-0 cursor-pointer hover:opacity-80 transition-opacity">
                                            </template>

                                            <div @dragover.prevent="v.isDragging = true"
                                                 @dragleave.prevent="v.isDragging = false"
                                                 @drop.prevent="handleVariantDrop($event, v.id); touched[`variant_pictures[${v.id}]`] = true; setTimeout(() => validateForm(), 100)"
                                                 :class="hasError(`variant_pictures[${v.id}]`) ? 'border-red-500 bg-red-50' : (v.isDragging ? 'border-primary bg-primary/10' : 'border-border-subtle bg-white hover:bg-brand-light/10')"
                                                 class="relative flex-1 border-2 border-dashed rounded-xl p-3 transition-all text-center flex items-center justify-center min-h-[64px]">
                                                <input :id="`variant_picture_input_${v.id}`"
                                                       type="file"
                                                       :name="`variant_pictures[${v.id}]`"
                                                       accept="image/jpeg,image/png,image/jpg,image/webp"
                                                       required
                                                       @change="handleVariantImage($event, v.id); touched[`variant_pictures[${v.id}]`] = true; setTimeout(() => validateForm(), 100)"
                                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                                <span class="text-xs font-semibold text-text-muted pointer-events-none flex items-center justify-center gap-1.5">
                                                    <svg class="w-5 h-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                    <span>Variant Photo: Drag &amp; drop or <span class="text-primary underline font-bold">browse</span></span>
                                                </span>
                                            </div>
                                        </div>
                                        <span x-show="hasError(`variant_pictures[${v.id}]`)" x-cloak class="text-red-500 text-[10px] font-bold mt-1 block">Dedicated variant image is required.</span>
                                    </div>

                                    <template x-if="priceDependsOn === 'main'">
                                        <div class="flex gap-2 w-full lg:w-auto shrink-0 pt-0.5">
                                            <div class="flex flex-col w-full lg:w-32">
                                                <div class="relative w-full">
                                                    <span class="absolute left-2.5 top-2.5 text-text-muted text-xs font-bold pointer-events-none">₱</span>
                                                    <input type="number" step="0.01" min="20" :name="`variant_prices[${v.id}]`" x-model="v.price" placeholder="Price" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                           :class="hasError(`variant_prices[${v.id}]`) ? 'border-red-500 focus:border-red-500' : 'border-border-subtle focus:border-primary'"
                                                           class="w-full pl-6 p-2 text-sm rounded-lg border outline-none no-spinners cursor-text">
                                                </div>
                                                <span x-show="hasError(`variant_prices[${v.id}]`)" x-cloak class="text-red-500 text-[9px] font-bold mt-1 block">Required (Min ₱20)</span>
                                            </div>

                                            <div class="flex flex-col w-full lg:w-28">
                                                <div class="relative w-full">
                                                    <input type="number" step="0.01" min="0.01" :name="`variant_weights[${v.id}]`" x-model="v.weight" placeholder="Weight" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                           :class="hasError(`variant_weights[${v.id}]`) ? 'border-red-500 focus:border-red-500' : 'border-border-subtle focus:border-primary'"
                                                           class="w-full pr-8 p-2 text-sm rounded-lg border outline-none no-spinners cursor-text">
                                                    <span class="absolute right-2.5 top-2.5 text-text-muted text-xs font-bold pointer-events-none">KG</span>
                                                </div>
                                                <span x-show="hasError(`variant_weights[${v.id}]`)" x-cloak class="text-red-500 text-[9px] font-bold mt-1 block">Required</span>
                                            </div>

                                            <div class="flex flex-col w-full lg:w-24">
                                                <div class="relative w-full">
                                                    <input type="number" min="0" :name="`variant_stocks[${v.id}]`" x-model="v.stock" placeholder="Stock" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                                                           :class="hasError(`variant_stocks[${v.id}]`) ? 'border-red-500 focus:border-red-500' : 'border-border-subtle focus:border-primary'"
                                                           class="w-full p-2 text-sm rounded-lg border outline-none no-spinners cursor-text">
                                                </div>
                                                <span x-show="hasError(`variant_stocks[${v.id}]`)" x-cloak class="text-red-500 text-[9px] font-bold mt-1 block">Required</span>
                                            </div>
                                        </div>
                                    </template>

                                    <button type="button" @click="removeMainVariant(v.id)" class="text-red-500 hover:text-red-700 p-2 mt-1 lg:mt-0 cursor-pointer">
                                        <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>

                                <div x-show="priceDependsOn === 'sub'" class="p-4 bg-white space-y-3">
                                    <div class="flex justify-between items-center mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-text-muted uppercase tracking-wider" x-text="subVariantTitle"></span>
                                            <span x-show="v.name.trim() === ''" class="text-[10px] text-red-400 font-medium italic">(Fill main variant name first)</span>
                                        </div>
                                        <button type="button"
                                                @click="v.name.trim() !== '' ? addSubVariant(v.id) : null"
                                                :disabled="v.name.trim() === ''"
                                                :class="v.name.trim() === '' ? 'text-gray-400 cursor-not-allowed opacity-60' : 'text-primary hover:text-primary-dark cursor-pointer'"
                                                class="text-xs font-bold transition-colors">
                                            + Add <span x-text="subVariantTitle"></span>
                                        </button>
                                    </div>

                                    <template x-for="(sub, sIndex) in v.subs" :key="sub.id">
                                        <div class="space-y-2 bg-brand-light/10 p-3 rounded-xl border border-brand-light/30">

                                            <div x-show="sIndex > 0 && !sub.isCopied && v.subs[0] && (v.subs[0].price || v.subs[0].weight || v.subs[0].stock)"
                                                 x-data="{ promptState: 'active' }"
                                                 class="mb-2">
                                                 <div x-show="promptState === 'active'" x-transition class="p-2.5 bg-blue-50 border border-blue-200 rounded-lg flex flex-wrap items-center justify-between gap-2 text-xs text-blue-900 shadow-sm">
                                                     <div class="flex items-center gap-2">
                                                         <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                         <span>Do you want to copy the previous subvariant descriptions (Price, Weight, Stock) from <strong x-text="v.subs[0].name || '1st Subvariant'"></strong>?</span>
                                                     </div>
                                                     <div class="flex gap-2 mt-2 sm:mt-0">
                                                         <button type="button" @click="copyFirstSubValues(v.id, sIndex); promptState = 'copied'; setTimeout(() => validateForm(), 50);" class="px-3 py-1 bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 transition-colors cursor-pointer shadow-sm">Copy</button>
                                                         <button type="button" @click="promptState = 'remind_later'" class="px-3 py-1 bg-white border border-blue-300 text-blue-600 font-bold rounded-md hover:bg-blue-50 transition-colors cursor-pointer shadow-sm">Not Now</button>
                                                         <button type="button" @click="sub.isCopied = true; promptState = 'dismissed'" class="px-3 py-1 bg-gray-100 text-gray-600 font-bold rounded-md hover:bg-gray-200 transition-colors cursor-pointer shadow-sm">Dismiss</button>
                                                     </div>
                                                 </div>
                                                 <div x-show="promptState === 'remind_later'" class="flex justify-end mt-1">
                                                     <button type="button" @click="promptState = 'active'" class="text-[10px] text-blue-500 hover:text-blue-700 underline font-bold">Show automation prompt</button>
                                                 </div>
                                            </div>

                                            <div class="flex flex-col md:flex-row items-start gap-3">
                                                <div class="flex-1 w-full">
                                                    <input type="text" :name="`sub_variant_names[${v.id}][${sub.id}]`" x-model="sub.name" :placeholder="`${subVariantTitle} Name (e.g. 128GB)`" required
                                                           :disabled="v.name.trim() === ''"
                                                           :class="(isSubVariantDuplicate(v, sub.name) || hasError(`sub_variant_names[${v.id}][${sub.id}]`) ? 'border-red-500 focus:border-red-500 ' : 'border-border-subtle focus:border-primary ') + (v.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white')"
                                                           class="w-full p-2 text-xs rounded-md border outline-none transition-colors">
                                                    <span x-show="hasError(`sub_variant_names[${v.id}][${sub.id}]`)" x-cloak class="text-red-500 text-[9px] font-bold mt-1 block">Required</span>
                                                    <span x-show="isSubVariantDuplicate(v, sub.name)" class="text-red-500 text-[9px] font-bold mt-1 block">Sub-variant must be unique.</span>
                                                </div>

                                                <div class="flex flex-wrap gap-2 w-full md:w-auto">
                                                    <div class="flex flex-col flex-1 min-w-[100px]">
                                                        <div class="relative w-full">
                                                            <span class="absolute left-2.5 top-1.5 text-text-muted text-xs font-bold pointer-events-none" :class="v.name.trim() === '' ? 'opacity-50' : ''">₱</span>
                                                            <input type="number" step="0.01" min="20" :name="`sub_variant_prices[${v.id}][${sub.id}]`" x-model="sub.price" placeholder="Price" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                   :disabled="v.name.trim() === ''"
                                                                   :class="(hasError(`sub_variant_prices[${v.id}][${sub.id}]`) ? 'border-red-500 ' : 'border-border-subtle focus:border-primary ') + (v.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white cursor-text')"
                                                                   class="w-full pl-6 p-2 text-xs rounded-md border outline-none no-spinners">
                                                        </div>
                                                        <span x-show="hasError(`sub_variant_prices[${v.id}][${sub.id}]`)" x-cloak class="text-red-500 text-[9px] font-bold mt-1 block">Required</span>
                                                    </div>

                                                    <div class="flex flex-col flex-1 min-w-[90px]">
                                                        <div class="relative w-full">
                                                            <input type="number" step="0.01" min="0.01" :name="`sub_variant_weights[${v.id}][${sub.id}]`" x-model="sub.weight" placeholder="Weight" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                                                   :disabled="v.name.trim() === ''"
                                                                   :class="(hasError(`sub_variant_weights[${v.id}][${sub.id}]`) ? 'border-red-500 ' : 'border-border-subtle focus:border-primary ') + (v.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white cursor-text')"
                                                                   class="w-full pr-7 p-2 text-xs rounded-md border outline-none no-spinners">
                                                            <span class="absolute right-2 top-1.5 text-text-muted text-xs font-bold pointer-events-none" :class="v.name.trim() === '' ? 'opacity-50' : ''">KG</span>
                                                        </div>
                                                        <span x-show="hasError(`sub_variant_weights[${v.id}][${sub.id}]`)" x-cloak class="text-red-500 text-[9px] font-bold mt-1 block">Required</span>
                                                    </div>

                                                    <div class="flex flex-col flex-1 min-w-[80px]">
                                                        <div class="relative w-full">
                                                            <input type="number" min="0" :name="`sub_variant_stocks[${v.id}][${sub.id}]`" x-model="sub.stock" placeholder="Stock" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                                                                   :disabled="v.name.trim() === ''"
                                                                   :class="(hasError(`sub_variant_stocks[${v.id}][${sub.id}]`) ? 'border-red-500 ' : 'border-border-subtle focus:border-primary ') + (v.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white cursor-text')"
                                                                   class="w-full p-2 text-xs rounded-md border outline-none no-spinners">
                                                        </div>
                                                        <span x-show="hasError(`sub_variant_stocks[${v.id}][${sub.id}]`)" x-cloak class="text-red-500 text-[9px] font-bold mt-1 block">Required</span>
                                                    </div>

                                                    <button type="button" @click="removeSubVariant(v.id, sub.id)" class="text-text-muted hover:text-red-500 mt-1.5 md:mt-1 cursor-pointer shrink-0">
                                                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <div x-show="v.subs.length === 0" class="space-y-4 mt-2">
                                        <div x-show="vIndex !== 0 && variants[0].subs.length > 0 && !isCopied && v.name.trim() !== ''" x-transition class="p-4 bg-blue-50 border border-blue-200 rounded-xl flex flex-col sm:flex-row items-center justify-between shadow-sm">
                                            <div class="flex items-center text-blue-700 text-sm mb-3 sm:mb-0">
                                                <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                <span>Save time! Do all variants share the same sub-variants?</span>
                                            </div>
                                            <button type="button" @click="copySubVariants(); setTimeout(() => validateForm(), 50);" class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg shadow-sm hover:bg-blue-700 transition-colors cursor-pointer">
                                                Copy from 1st Variant
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <button type="button" @click="addMainVariant()" class="w-full py-3 border-2 border-dashed border-primary/50 text-primary font-bold rounded-xl hover:bg-primary/5 transition-colors cursor-pointer">
                            + Add <span x-text="variantTitle || 'Variant'"></span>
                        </button>
                    </div>
                </div>

                <div x-data="{
                    enableSpecs: false,
                    specs: [{ id: Date.now(), title: '', desc: '' }],
                    addSpec() { this.specs.push({ id: Date.now(), title: '', desc: '' }); },
                    removeSpec(index) { this.specs.splice(index, 1); }
                }" class="border border-border-subtle rounded-2xl p-6 bg-brand-light/5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-text-muted">Specifications <span class="text-xs font-normal italic">(Optional)</span></h3>
                            <p class="text-xs text-text-muted mt-1">Add dynamic product specifications depending on the item.</p>
                        </div>
                        <button type="button" @click="enableSpecs = !enableSpecs; if(enableSpecs && specs.length === 0) addSpec();"
                                class="px-4 py-2 text-xs font-bold rounded-lg border transition-colors cursor-pointer"
                                :class="enableSpecs ? 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100' : 'bg-primary/10 text-primary border-primary/20 hover:bg-primary/20'">
                            <span x-text="enableSpecs ? 'Disable Specifications' : 'Enable Specifications'"></span>
                        </button>
                    </div>

                    <div x-show="enableSpecs" x-collapse class="space-y-3 mt-4">
                        <template x-for="(spec, index) in specs" :key="spec.id">
                            <div class="flex items-start gap-3">
                                <div class="flex-1">
                                    <input type="text" :name="`specifications[${index}][title]`" x-model="spec.title" placeholder="Specification Title (e.g. RAM, Warranty)" class="w-full p-2.5 text-sm rounded-xl border border-border-subtle focus:border-primary outline-none">
                                </div>
                                <div class="flex-1">
                                    <input type="text" :name="`specifications[${index}][description]`" x-model="spec.desc" placeholder="Specification Description (e.g. 8GB, 1 Year)" class="w-full p-2.5 text-sm rounded-xl border border-border-subtle focus:border-primary outline-none">
                                </div>
                                <button type="button" @click="removeSpec(index)" class="p-2.5 text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-50 transition-colors mt-0.5 cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </template>
                        <button type="button" @click="addSpec" class="text-xs font-bold text-primary hover:text-primary-dark cursor-pointer">+ Add another specification</button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-text-muted mb-1">Standard Description</label>
                    <textarea name="description" rows="3" class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none custom-scrollbar cursor-text">{{ old('description') }}</textarea>
                </div>

                <div x-data="{ enableAddDesc: false }">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-bold text-text-muted">Additional Descriptions <span class="text-xs font-normal italic">(Optional)</span></label>
                        <button type="button" @click="enableAddDesc = !enableAddDesc" class="text-xs text-primary font-bold hover:underline cursor-pointer" x-text="enableAddDesc ? 'Disable Field' : 'Enable Field'"></button>
                    </div>
                    <textarea name="additional_descriptions" rows="3" :disabled="!enableAddDesc" :class="!enableAddDesc ? 'bg-gray-100 cursor-not-allowed opacity-70' : 'bg-white cursor-text'" class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none custom-scrollbar">{{ old('additional_descriptions') }}</textarea>
                </div>

                <div class="flex flex-col items-end mt-8 border-t border-border-subtle pt-6">
                    <button type="submit"
                            :disabled="hasValidationErrors || !isFormValid"
                            :class="(hasValidationErrors || !isFormValid) ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-primary hover:bg-primary-dark cursor-pointer'"
                            class="px-8 py-3 text-white rounded-xl shadow-md transition-all font-bold text-lg">
                        Create Product
                    </button>
                </div>
            </form>
        </div>

        <template x-teleport="body">
            <div x-show="imageModalOpen" x-cloak x-transition class="fixed inset-0 z-[200] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 cursor-default">
                <div @click.away="imageModalOpen = false" class="relative max-w-5xl max-h-[90vh] flex flex-col items-center justify-center cursor-default">
                    <button @click="imageModalOpen = false" class="absolute -top-12 right-0 md:-right-12 p-2 text-white/70 hover:text-white hover:bg-white/10 rounded-full transition-all cursor-pointer">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    <img :src="imageModalSrc" class="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl">
                </div>
            </div>
        </template>
    </div>
@endsection