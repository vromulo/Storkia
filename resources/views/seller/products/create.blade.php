<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storkia - Add Product</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; } 
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; } 
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } 
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
        
        .no-spinners::-webkit-inner-spin-button,
        .no-spinners::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .no-spinners { -moz-appearance: textfield; }
    </style>
    <script>
        function variantManager() {
            return {
                variantTitle: '',
                subVariantTitle: '',
                variants: [ { id: Date.now(), name: '', subs: [], imagePreview: null, stock: '', isDragging: false } ],
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
                    if(this.variants.length <= 1) return;
                    
                    const firstVariantSubs = this.variants[0].subs;
                    if(firstVariantSubs.length === 0) {
                        alert("Please add at least one sub-variant to the first item before copying.");
                        return;
                    }

                    for(let i = 1; i < this.variants.length; i++) {
                        if(this.variants[i].name.trim() !== '') {
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
            }
        }
    </script>
</head>
<body class="m-0 p-0 h-screen w-screen font-sans antialiased text-text-main overflow-hidden bg-gradient-to-b from-surface via-surface to-brand-light/30">
    
    @php
        $categoriesList = [
            'Pet' => [ ['name' => 'Dog Food & Treats'], ['name' => 'Cat Litter & Accessories'], ['name' => 'Aquariums & Fish Supplies'], ['name' => 'Bird Feeders & Food'], ['name' => 'Pet Grooming Products'], ['name' => 'Pet Health & Wellness'], ],
            'Kids' => [ ['name' => 'Baby Clothes & Accessories'], ['name' => 'Toys & Games'], ['name' => 'Educational Materials'], ['name' => 'Strollers & Gear'], ['name' => 'Nursery Furniture'], ['name' => 'Safety and Health'], ],
            'Electronics' => [ ['name' => 'Mobile Phones & Accessories'], ['name' => 'Laptops, Desktops & Monitors'], ['name' => 'Audio & Video Equipment'], ['name' => 'Smart Home Devices'], ['name' => 'Cameras & Photography'], ['name' => 'Wearable Technology'], ],
            'Home & Garden' => [ ['name' => 'Kitchen Appliances'], ['name' => 'Furniture & Decor'], ['name' => 'Gardening Tools'], ['name' => 'Outdoor Living'], ['name' => 'Home Improvement Tools'], ['name' => 'Bedding & Bath'], ],
            'Women\'s' => [ ['name' => 'Dresses & Skirts'], ['name' => 'Tops & Blouses'], ['name' => 'Activewear & Yoga Pants'], ['name' => 'Lingerie & Sleepwear'], ['name' => 'Jackets & Coats'], ['name' => 'Shoes & Accessories'], ],
            'Men\'s' => [ ['name' => 'Suits & Blazers'], ['name' => 'Casual Shirts & Pants'], ['name' => 'Outerwear & Jackets'], ['name' => 'Activewear & Fitness Gear'], ['name' => 'Shoes & Accessories'], ['name' => 'Grooming Products'], ],
            'Health & Beauty' => [ ['name' => 'Skincare Products'], ['name' => 'Haircare Solutions'], ['name' => 'Makeup & Cosmetics'], ['name' => 'Personal Care Appliances'], ['name' => 'Men\'s Grooming'], ['name' => 'Health Supplements'], ],
            'Books & Media' => [ ['name' => 'Fiction & Non-Fiction Books'], ['name' => 'Magazines & Periodicals'], ['name' => 'Music CDs & Vinyl Records'], ['name' => 'Movie DVDs & Blu-ray'], ['name' => 'Video Games & Consoles'], ['name' => 'Educational DVDs'], ],
            'Sports & Outdoors' => [ ['name' => 'Fitness Equipment'], ['name' => 'Camping & Hiking Gear'], ['name' => 'Sports Apparel'], ['name' => 'Cycling & Bikes'], ['name' => 'Water Sports'], ['name' => 'Team Sports Equipment'], ],
            'Food & Gourmet' => [ ['name' => 'Baking Supplies & Ingredients'], ['name' => 'Coffee, Tea & Beverages'], ['name' => 'Snacks & Candy'], ['name' => 'Specialty Foods'], ['name' => 'Organic and Health Foods'], ['name' => 'Meal Kits & Prepped Foods'], ],
            'Furniture & Office' => [ ['name' => 'Office Desks & Chairs'], ['name' => 'Storage Cabinets & Shelving'], ['name' => 'Conference & Meeting Furniture'], ['name' => 'Computer Tables & Workstations'], ['name' => 'Ergonomic Accessories'], ['name' => 'Office Lighting & Fixtures'], ],
            'Jewelry & Watches' => [ ['name' => 'Necklaces & Pendants'], ['name' => 'Rings & Earrings'], ['name' => 'Bracelets & Bangles'], ['name' => 'Watches for Men & Women'], ['name' => 'Fashion Jewelry'], ['name' => 'Jewelry Storage & Care'], ]
        ];
        
        $sellerCategory = auth()->user()->sellerProfile->line_of_business ?? 'Pet'; 
        $subcategories = $categoriesList[$sellerCategory] ?? $categoriesList['Pet'];
    @endphp

    <!-- FLOATING POP-UP ERROR NOTIFICATION -->
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

    <div x-data="{ sidebarOpen: localStorage.getItem('sellerSidebarOpen') !== 'false', imageModalOpen: false, imageModalSrc: '' }" class="h-screen w-full relative flex">
        
        @include('components.seller.sidebar')

        <main class="flex-1 h-screen overflow-y-auto custom-scrollbar relative" x-data="{ showContent: false }" x-init="setTimeout(() => showContent = true, 50)">
            <div class="p-8 lg:p-12" x-show="showContent" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-cloak>
                
                <div class="max-w-5xl mx-auto">
                    <div class="flex items-center mb-8">
                        <a href="{{ route('seller.products.index') }}" class="mr-4 p-2 bg-white rounded-full shadow-sm border border-border-subtle hover:bg-brand-light/20 transition-colors text-text-muted hover:text-primary cursor-pointer">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </a>
                        <h1 class="text-3xl font-bold text-primary-dark">Add New Product</h1>
                    </div>

                    <form x-data="variantManager()" action="{{ route('seller.products.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl p-8 shadow-sm border border-border-subtle space-y-6">
                        @csrf
                        
                        <!-- Hidden input to supply stock_quantity to controller validation -->
                        <input type="hidden" name="stock_quantity" :value="totalStock">
                        
                        <!-- Top Level Details -->
                        <div>
                            <label class="block text-sm font-bold text-text-muted mb-1">Product Name (Max 100 chars) <span class="text-red-500">*</span></label>
                            <input type="text" name="name" maxlength="100" required value="{{ old('name') }}" class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        </div>

                        <!-- CATEGORY / SUBCATEGORY -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-text-muted mb-1">Main Category</label>
                                <input type="text" name="category" value="{{ $sellerCategory }}" readonly class="w-full p-3.5 rounded-2xl border border-gray-200 bg-gray-100 text-gray-500 font-bold outline-none cursor-not-allowed select-none">
                            </div>

                            <!-- Modernized Searchable Dropdown Matching Image UI -->
                            <div x-data="{
                                    open: false,
                                    search: '',
                                    selected: '{{ old('subcategory') }}',
                                    options: {{ json_encode(array_column($subcategories, 'name')) }},
                                    get filteredOptions() {
                                        if (!this.search.trim()) return this.options;
                                        return this.options.filter(opt => opt.toLowerCase().includes(this.search.toLowerCase()));
                                    },
                                    selectOption(opt) {
                                        this.selected = opt;
                                        this.open = false;
                                        this.search = '';
                                    }
                                 }" 
                                 @click.away="open = false" 
                                 class="relative w-full">
                                
                                <label class="block text-sm font-bold text-text-muted mb-1">Subcategory <span class="text-red-500">*</span></label>
                                
                                <!-- The actual focusable input that natively submits the data without HTML5 hidden validation errors -->
                                <input type="text" name="subcategory" x-model="selected" required tabindex="-1" class="absolute w-0 h-0 opacity-0 pointer-events-none -z-10">

                                <!-- Dropdown Input Trigger Field -->
                                <div @click="open = true; $refs.searchInput.focus()" 
                                     class="w-full p-3 bg-white rounded-2xl border-2 border-gray-300 hover:border-gray-400 focus-within:border-primary transition-all flex items-center justify-between cursor-text shadow-sm relative">
                                    
                                    <input x-ref="searchInput"
                                           type="text" 
                                           x-model="search" 
                                           @focus="open = true"
                                           @keydown.enter.prevent="if(filteredOptions.length > 0) selectOption(filteredOptions[0])"
                                           :placeholder="selected ? '' : 'Select or type to search...'" 
                                           class="w-full bg-transparent outline-none text-text-main font-medium z-10 relative">
                                           
                                    <!-- Display the strictly selected value directly below the search input when search is inactive -->
                                    <div x-show="selected && !search" class="absolute left-3 right-10 top-0 bottom-0 flex items-center text-text-main font-medium pointer-events-none">
                                        <span x-text="selected"></span>
                                    </div>

                                    <button type="button" @click.stop="open = !open" class="text-gray-500 hover:text-gray-700 ml-2 z-10 cursor-pointer">
                                        <svg class="w-5 h-5 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Dropdown Menu Overlay -->
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
                                        <div @click="selectOption(opt)" 
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
                                <div class="relative flex items-center w-full md:w-1/2">
                                    <input type="number" step="0.01" min="0" name="discount" value="{{ old('discount', 0) }}" 
                                           onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46"
                                           class="w-full pr-9 p-3 rounded-xl border border-border-subtle focus:border-primary outline-none no-spinners cursor-text">
                                    <span class="absolute right-4 text-text-muted font-bold">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Main Image Drag & Drop Upload -->
                        <div x-data="{
                                isDragging: false,
                                errorMessage: '',
                                previews: [],
                                handleDrop(e) {
                                    this.isDragging = false;
                                    const files = e.dataTransfer ? e.dataTransfer.files : null;
                                    if (files) this.processFiles(files);
                                },
                                handleSelect(e) {
                                    this.processFiles(e.target.files);
                                },
                                processFiles(files) {
                                    this.errorMessage = '';
                                    const dt = new DataTransfer();
                                    let hasInvalid = false;

                                    for (let i = 0; i < files.length; i++) {
                                        const file = files[i];
                                        if (file.type && file.type.startsWith('image/')) {
                                            dt.items.add(file);
                                        } else {
                                            hasInvalid = true;
                                        }
                                    }

                                    if (hasInvalid) {
                                        this.errorMessage = 'Only image files (PNG, JPG, WEBP, GIF, SVG) are allowed. PDFs and documents were rejected.';
                                    }

                                    this.$refs.mainFileInput.files = dt.files;
                                    this.previews = [];
                                    for (let i = 0; i < dt.files.length; i++) {
                                        let reader = new FileReader();
                                        reader.onload = (e) => this.previews.push(e.target.result);
                                        reader.readAsDataURL(dt.files[i]);
                                    }
                                }
                            }">
                            <label class="block text-sm font-bold text-text-muted mb-1">Main Product Pictures <span class="text-red-500">*</span></label>

                            <div @dragover.prevent="isDragging = true" 
                                 @dragleave.prevent="isDragging = false" 
                                 @drop.prevent="handleDrop($event)"
                                 :class="isDragging ? 'border-primary bg-primary/10 scale-[1.005]' : 'border-border-subtle bg-surface hover:bg-brand-light/10'"
                                 class="border-2 border-dashed rounded-2xl p-10 text-center transition-all cursor-pointer relative flex flex-col items-center justify-center min-h-[220px]">
                                
                                <input x-ref="mainFileInput" 
                                       type="file" 
                                       name="pictures[]" 
                                       multiple 
                                       accept="image/*" 
                                       required 
                                       @change="handleSelect($event)" 
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                
                                <div class="flex flex-col items-center justify-center space-y-3 pointer-events-none">
                                    <div class="p-4 bg-primary/10 text-primary rounded-full shadow-inner shrink-0 flex items-center justify-center">
                                        <svg class="w-10 h-10 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-base font-bold text-primary-dark">
                                        <span class="text-primary underline">Choose Files</span> or drag & drop images here
                                    </p>
                                    <p class="text-xs text-text-muted">Supports PNG, JPG, WEBP, GIF, SVG (PDFs & Documents are rejected)</p>
                                </div>
                            </div>

                            <template x-if="errorMessage">
                                <p class="text-red-500 text-xs font-bold mt-2 flex items-center">
                                    <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span x-text="errorMessage"></span>
                                </p>
                            </template>
                            
                            <!-- Enlarged Image Previews -->
                            <div x-show="previews.length > 0" x-cloak x-transition class="flex gap-4 overflow-x-auto mt-4 pb-3 custom-scrollbar">
                                <template x-for="preview in previews">
                                    <div class="relative group shrink-0">
                                        <img :src="preview" @click="imageModalSrc = preview; imageModalOpen = true" class="w-36 h-36 object-cover rounded-2xl border-2 border-border-subtle shadow-md cursor-pointer hover:opacity-90 transition-all transform hover:scale-105">
                                        <span class="absolute bottom-2 right-2 bg-black/60 text-white text-[10px] px-2 py-0.5 rounded-full backdrop-blur pointer-events-none">Click to view</span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- VARIANT SYSTEM -->
                        <div class="border border-border-subtle rounded-2xl p-6 bg-brand-light/10 space-y-6">
                            
                            <div class="border-b border-border-subtle pb-4">
                                <h3 class="text-lg font-bold text-primary-dark mb-4">Product Variants</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-text-muted mb-1">Title of the Variants <span class="text-red-500">*</span></label>
                                        <input type="text" name="variant_title" x-model="variantTitle" placeholder="e.g. Color, Units" required class="w-full p-2.5 rounded-lg border border-border-subtle focus:border-primary outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-text-muted mb-1">Sub-title (Optional)</label>
                                        <input type="text" name="sub_variant_title" x-model="subVariantTitle" placeholder="e.g. Size, RAM" class="w-full p-2.5 rounded-lg border border-border-subtle focus:border-primary outline-none">
                                    </div>
                                </div>
                            </div>

                            <!-- MAIN VARIANTS LOOP -->
                            <div class="space-y-4">
                                <template x-for="(v, vIndex) in variants" :key="v.id">
                                    <div class="bg-white rounded-xl border border-border-subtle shadow-sm overflow-hidden">
                                        
                                        <!-- Main Variant Row -->
                                        <div class="p-4 flex flex-col lg:flex-row lg:items-center gap-4 bg-surface/50 border-b border-border-subtle">
                                            
                                            <!-- Main Variant Name -->
                                            <div class="w-full lg:w-48 shrink-0">
                                                <input type="text" :name="`variant_names[${v.id}]`" x-model="v.name" :placeholder="`${variantTitle || 'Variant'} Name (e.g. Red)`" required 
                                                       :class="duplicateVariants.includes(v.name.trim().toLowerCase()) && v.name.trim() !== '' ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-border-subtle focus:border-primary'"
                                                       class="w-full p-2.5 text-sm rounded-lg border outline-none transition-colors">
                                                <span x-show="duplicateVariants.includes(v.name.trim().toLowerCase()) && v.name.trim() !== ''" class="text-red-500 text-[10px] font-bold mt-1 block">Variant name must be unique.</span>
                                            </div>
                                            
                                            <!-- Variant Image Drag & Drop Area -->
                                            <div class="flex-1 flex items-center gap-3 min-w-[260px]">
                                                <template x-if="v.imagePreview">
                                                    <img :src="v.imagePreview" @click="imageModalSrc = v.imagePreview; imageModalOpen = true" class="w-20 h-20 object-cover rounded-xl border-2 border-border-subtle shadow-sm flex-shrink-0 cursor-pointer hover:opacity-80 transition-opacity">
                                                </template>
                                                
                                                <div @dragover.prevent="v.isDragging = true"
                                                     @dragleave.prevent="v.isDragging = false"
                                                     @drop.prevent="handleVariantDrop($event, v.id)"
                                                     :class="v.isDragging ? 'border-primary bg-primary/10' : 'border-border-subtle bg-white hover:bg-brand-light/10'"
                                                     class="relative flex-1 border-2 border-dashed rounded-xl p-3 transition-all text-center flex items-center justify-center min-h-[64px]">
                                                    
                                                    <input :id="`variant_picture_input_${v.id}`"
                                                           type="file" 
                                                           :name="`variant_pictures[${v.id}]`" 
                                                           accept="image/*" 
                                                           required 
                                                           @change="handleVariantImage($event, v.id)" 
                                                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                                    
                                                    <span class="text-xs font-semibold text-text-muted pointer-events-none flex items-center justify-center gap-1.5">
                                                        <svg class="w-5 h-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                        <span>Drag & drop or <span class="text-primary underline font-bold">browse</span> image</span>
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Data requirements if no Sub Variants -->
                                            <template x-if="priceDependsOn === 'main'">
                                                <div class="flex gap-2 w-full lg:w-auto shrink-0">
                                                    <div class="relative w-full lg:w-32">
                                                        <span class="absolute left-2.5 top-2.5 text-text-muted text-xs font-bold">₱</span>
                                                        <input type="number" step="0.01" min="20" :name="`variant_prices[${v.id}]`" placeholder="Price" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" class="w-full pl-6 p-2 text-sm rounded-lg border border-border-subtle focus:border-primary outline-none no-spinners cursor-text">
                                                    </div>
                                                    <div class="relative w-full lg:w-28">
                                                        <input type="number" step="0.01" min="0" :name="`variant_weights[${v.id}]`" placeholder="Weight" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" class="w-full pr-8 p-2 text-sm rounded-lg border border-border-subtle focus:border-primary outline-none no-spinners cursor-text">
                                                        <span class="absolute right-2.5 top-2.5 text-text-muted text-xs font-bold">KG</span>
                                                    </div>
                                                    <div class="relative w-full lg:w-24">
                                                        <input type="number" min="0" :name="`variant_stocks[${v.id}]`" x-model="v.stock" placeholder="Stock" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57)" class="w-full p-2 text-sm rounded-lg border border-border-subtle focus:border-primary outline-none no-spinners cursor-text">
                                                    </div>
                                                </div>
                                            </template>

                                            <button type="button" @click="removeMainVariant(v.id)" class="text-red-500 hover:text-red-700 p-2 mt-1 lg:mt-0 cursor-pointer">
                                                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>

                                        <!-- Sub Variants -->
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
                                                    
                                                    <!-- Subvariant Automation Prompt Message for 2nd, 3rd subvariants onward -->
                                                    <div x-show="sIndex > 0 && !sub.isCopied && v.subs[0] && (v.subs[0].price || v.subs[0].weight || v.subs[0].stock)" 
                                                         x-transition
                                                         class="p-2.5 bg-blue-50 border border-blue-200 rounded-lg flex flex-wrap items-center justify-between gap-2 text-xs text-blue-900 shadow-sm">
                                                        <div class="flex items-center gap-2">
                                                            <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                            <span>Would you like to copy Price, Weight, and Stock from the 1st subvariant (<strong x-text="v.subs[0].name || '1st Subvariant'"></strong>)?</span>
                                                        </div>
                                                        <button type="button" 
                                                                @click="copyFirstSubValues(v.id, sIndex)" 
                                                                class="px-3 py-1 bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 transition-colors cursor-pointer text-[11px] shadow-sm">
                                                            Copy
                                                        </button>
                                                    </div>

                                                    <div class="flex items-start gap-3">
                                                        <div class="flex-1">
                                                            <input type="text" :name="`sub_variant_names[${v.id}][${sub.id}]`" x-model="sub.name" :placeholder="`${subVariantTitle} Name (e.g. 128GB)`" required 
                                                                   :disabled="v.name.trim() === ''"
                                                                   :class="(isSubVariantDuplicate(v, sub.name) ? 'border-red-500 focus:border-red-500 ' : 'border-border-subtle focus:border-primary ') + (v.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white')"
                                                                   class="w-full p-2 text-xs rounded-md border outline-none transition-colors">
                                                            <span x-show="isSubVariantDuplicate(v, sub.name)" class="text-red-500 text-[10px] font-bold mt-1 block">Sub-variant must be unique.</span>
                                                        </div>
                                                        
                                                        <div class="flex gap-2">
                                                            <div class="relative w-28">
                                                                <span class="absolute left-2.5 top-1.5 text-text-muted text-xs font-bold" :class="v.name.trim() === '' ? 'opacity-50' : ''">₱</span>
                                                                <input type="number" step="0.01" min="20" :name="`sub_variant_prices[${v.id}][${sub.id}]`" x-model="sub.price" placeholder="Price" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" 
                                                                       :disabled="v.name.trim() === ''"
                                                                       :class="v.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white cursor-text'"
                                                                       class="w-full pl-6 p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners">
                                                            </div>
                                                            <div class="relative w-24">
                                                                <input type="number" step="0.01" min="0" :name="`sub_variant_weights[${v.id}][${sub.id}]`" x-model="sub.weight" placeholder="Weight" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 46" 
                                                                       :disabled="v.name.trim() === ''"
                                                                       :class="v.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white cursor-text'"
                                                                       class="w-full pr-7 p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners">
                                                                <span class="absolute right-2 top-1.5 text-text-muted text-xs font-bold" :class="v.name.trim() === '' ? 'opacity-50' : ''">KG</span>
                                                            </div>
                                                            <div class="relative w-20">
                                                                <input type="number" min="0" :name="`sub_variant_stocks[${v.id}][${sub.id}]`" x-model="sub.stock" placeholder="Stock" required onkeypress="return (event.charCode >= 48 && event.charCode <= 57)" 
                                                                       :disabled="v.name.trim() === ''"
                                                                       :class="v.name.trim() === '' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white cursor-text'"
                                                                       class="w-full p-2 text-xs rounded-md border border-border-subtle focus:border-primary outline-none no-spinners">
                                                            </div>
                                                        </div>

                                                        <button type="button" @click="removeSubVariant(v.id, sub.id)" class="text-text-muted hover:text-red-500 mt-1 cursor-pointer">
                                                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                            
                                            <div x-show="v.subs.length === 0" class="space-y-4 mt-2">
                                                <div x-show="vIndex !== 0 && variants[0].subs.length > 0 && !isCopied && v.name.trim() !== ''" x-transition class="p-4 bg-blue-50 border border-blue-200 rounded-xl flex flex-col sm:flex-row items-center justify-between shadow-sm">
                                                    <div class="flex items-center text-blue-700 text-sm mb-3 sm:mb-0">
                                                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 8h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        <span>Save time! Do all variants share the same sub-variants?</span>
                                                    </div>
                                                    <button type="button" @click="copySubVariants" class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg shadow-sm hover:bg-blue-700 transition-colors cursor-pointer">
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

                        <div>
                            <label class="block text-sm font-bold text-text-muted mb-1">Standard Description</label>
                            <textarea name="description" rows="3" class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none custom-scrollbar cursor-text">{{ old('description') }}</textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-text-muted mb-1">Additional Descriptions <span class="text-xs font-normal italic">(Optional)</span></label>
                            <textarea name="additional_descriptions" rows="3" class="w-full p-3 rounded-xl border border-border-subtle focus:border-primary outline-none custom-scrollbar cursor-text">{{ old('additional_descriptions') }}</textarea>
                        </div>

                        <div class="flex justify-end mt-8 border-t border-border-subtle pt-6">
                            <button type="submit" 
                                    :disabled="hasValidationErrors" 
                                    :class="hasValidationErrors ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-primary hover:bg-primary-dark cursor-pointer'"
                                    class="px-8 py-3 text-white rounded-xl shadow-md transition-all font-bold text-lg">
                                Save Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

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
</body>
</html>