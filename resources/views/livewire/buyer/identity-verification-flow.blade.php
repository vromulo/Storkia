<div x-data="{ isReady: false }" 
     x-init="setTimeout(() => isReady = true, 450)" 
     class="bg-surface min-h-[calc(100vh-220px)] py-12 md:py-16 relative flex flex-col justify-center">

    <!-- Large 3 Bouncing Dots Loader (Centered in the viewport) -->
    <div x-show="!isReady" 
         x-transition:leave="transition ease-out duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute inset-0 flex items-center justify-center z-30 bg-surface">
        <div class="inline-flex items-center justify-center gap-3">
            <span class="w-4 h-4 bg-text-main rounded-full animate-bounce" style="animation-delay: -0.32s;"></span>
            <span class="w-4 h-4 bg-text-main rounded-full animate-bounce" style="animation-delay: -0.16s;"></span>
            <span class="w-4 h-4 bg-text-main rounded-full animate-bounce"></span>
        </div>
    </div>

    <!-- Identity Verification Content (Appears once loaded) -->
    <div x-show="isReady" 
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 w-full">

        {{-- 1. INTRO SCREEN --}}
        @if ($currentScreen === 'intro')
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-12 lg:gap-16 pt-4 w-full">
                
                <!-- Left Column: Primary Copy & Action (Expands to 100% width when privacy box is hidden) -->
                <div class="w-full flex-1 md:max-w-xl space-y-6">
                    <h1 class="text-3xl sm:text-4xl font-bold text-text-main tracking-tight font-sans">
                        Let’s add your government ID
                    </h1>

                    <div class="space-y-4 text-sm sm:text-base text-text-muted leading-relaxed">
                        <p>
                            We’ll need you to add an official government ID. This step helps make sure you’re really you.
                        </p>
                        <p>
                            You can add a driver’s license, passport, or national identity card.
                        </p>
                    </div>

                    <!-- Divider & Add an ID CTA (Aligned to the right edge of the container) -->
                    <div class="pt-8 border-t border-border-subtle flex justify-end w-full">
                        <button type="button" 
                                wire:click="start" 
                                class="px-8 py-3.5 bg-black hover:bg-text-main text-surface font-semibold text-sm rounded-xl shadow-xs transition-all cursor-pointer active:scale-[0.99]">
                            Add an ID
                        </button>
                    </div>
                </div>

                <!-- Right Column: Privacy Box Card (Hidden on tablet/mobile when logo is 'S') -->
                <div class="hidden md:block md:w-80 shrink-0">
                    <div class="bg-surface rounded-2xl border border-border-subtle p-6 shadow-xs space-y-3.5">
                        <h3 class="text-base font-bold text-text-main font-sans">
                            Your privacy
                        </h3>
                        <p class="text-xs sm:text-sm text-text-muted leading-relaxed">
                            We aim to keep the data you share during this process private, safe, and secure. Learn more in our 
                            <a href="#" class="text-text-main underline hover:text-primary transition-colors font-medium">Privacy Policy</a>.
                        </p>
                        <div class="pt-2 border-t border-border-subtle/60">
                            <a href="#" class="text-xs sm:text-sm font-semibold text-text-main hover:text-primary underline transition-colors inline-block">
                                How identity verification works
                            </a>
                        </div>
                    </div>
                </div>

            </div>

        {{-- 2. METHOD SELECTION --}}
        @elseif ($currentScreen === 'method')
            <div class="max-w-2xl mx-auto space-y-6">
                <div class="space-y-1">
                    <h2 class="text-2xl sm:text-3xl font-bold text-text-main tracking-tight font-sans">
                        How would you like to add your government ID?
                    </h2>
                    <p class="text-sm text-text-muted">Choose your preferred capture method to continue</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4">
                    <!-- Upload Existing Photo Option -->
                    <button type="button" wire:click="selectMethod('upload')" class="p-6 rounded-2xl border-2 border-border-subtle hover:border-primary hover:bg-brand-light/10 text-left transition-all group cursor-pointer flex flex-col justify-between h-40">
                        <div class="w-10 h-10 rounded-xl bg-surface-subtle group-hover:bg-primary/10 text-text-main group-hover:text-primary flex items-center justify-center transition-colors">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-text-main group-hover:text-primary transition-colors">Upload an existing photo</h3>
                            <p class="text-xs text-text-muted mt-0.5">Select JPEG or PNG files from this device</p>
                        </div>
                    </button>

                    <!-- Take a Photo Option -->
                    <button type="button" wire:click="selectMethod('camera')" class="p-6 rounded-2xl border-2 border-border-subtle hover:border-primary hover:bg-brand-light/10 text-left transition-all group cursor-pointer flex flex-col justify-between h-40">
                        <div class="w-10 h-10 rounded-xl bg-surface-subtle group-hover:bg-primary/10 text-text-main group-hover:text-primary flex items-center justify-center transition-colors">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-text-main group-hover:text-primary transition-colors">Take a photo</h3>
                            <p class="text-xs text-text-muted mt-0.5">Use your device camera to snap photos</p>
                        </div>
                    </button>
                </div>

                <hr class="border-border-subtle pt-2" />

                <div class="pt-2">
                    <button type="button" wire:click="$set('currentScreen', 'intro')" class="text-sm font-semibold text-text-muted hover:text-text-main transition-colors">
                        &larr; Back
                    </button>
                </div>
            </div>

        {{-- 3. ID TYPE SELECTION --}}
        @elseif ($currentScreen === 'id_type')
            <div class="max-w-2xl mx-auto space-y-6">
                <div class="space-y-1">
                    <h2 class="text-2xl sm:text-3xl font-bold text-text-main tracking-tight font-sans">
                        Choose an ID type to add
                    </h2>
                    <p class="text-sm text-text-muted">Select one of the government-recognized identity cards</p>
                </div>
                <div class="space-y-3 pt-2">
                    @php
                        $idOptions = [
                            'drivers_license' => [
                                'label' => "Driver's License",
                                // Car icon (1.5 stroke width)
                                'icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M5 17h-2v-6l2-5h12l2 5v6h-2m-10 0h6m-10-8h14" /></svg>'
                            ],
                            'passport' => [
                                'label' => 'Passport',
                                // Globe icon (1.5 stroke width)
                                'icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10" stroke-linecap="round" stroke-linejoin="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>'
                            ],
                            'identity_card' => [
                                'label' => 'Identity Card',
                                // Card / ID badge icon (1.5 stroke width)
                                'icon' => '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2" stroke-linecap="round" stroke-linejoin="round" /><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h4m-4 4h8m-8 4h6" /></svg>'
                            ]
                        ];
                    @endphp

                    @foreach ($idOptions as $key => $opt)
                        <button type="button" 
                                wire:click="selectIdType('{{ $key }}')" 
                                class="w-full py-4 px-5 sm:py-4.5 sm:px-6 rounded-2xl border border-border-subtle hover:border-primary hover:bg-brand-light/10 text-left transition-all flex items-center justify-between group cursor-pointer bg-surface">
                            <div class="flex items-center gap-3.5 sm:gap-4">
                                <span class="text-text-main group-hover:text-primary transition-colors shrink-0">
                                    {!! $opt['icon'] !!}
                                </span>
                                <span class="font-medium text-base sm:text-lg text-text-main group-hover:text-primary transition-colors">
                                    {{ $opt['label'] }}
                                </span>
                            </div>
                            <svg class="w-5 h-5 text-text-muted group-hover:text-primary transition-colors shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    @endforeach
                </div>

                <!-- Privacy & Handling Disclaimer -->
                <p class="text-xs sm:text-sm text-text-muted leading-relaxed pt-2">
                    Your ID will be handled according to our <a href="#" class="font-bold underline text-text-main hover:text-primary transition-colors">Privacy Policy</a> and won’t be shared with your Host or guests.
                </p>

                <hr class="border-border-subtle pt-2" />
                
                <div class="pt-2">
                    <button type="button" wire:click="$set('currentScreen', 'method')" class="text-sm font-semibold text-text-muted hover:text-text-main transition-colors">
                        &larr; Back
                    </button>
                </div>
            </div>

        {{-- 4. UPLOAD METHOD (DRAG AND DROP) --}}
        @elseif ($currentScreen === 'upload')
            @php
                $idLabel = match($id_type) {
                    'drivers_license' => "Driver's License",
                    'passport'        => "Passport",
                    default           => "Identity Card"
                };
                $canContinue = $id_type === 'passport' ? (bool)$front_image : ((bool)$front_image && (bool)$back_image);
            @endphp
            <div class="max-w-2xl mx-auto space-y-8">
                <!-- Heading & Dynamic Subdescription -->
                <div class="space-y-2">
                    <h2 class="text-2xl sm:text-3xl font-bold text-text-main tracking-tight font-sans">
                        Upload images of your {{ $idLabel }}
                    </h2>
                    <p class="text-sm text-text-muted leading-relaxed">
                        @if ($id_type === 'passport')
                            Make sure the photo of your passport isn’t blurry and that it clearly shows your face.
                        @elseif ($id_type === 'drivers_license')
                            Make sure your photos aren’t blurry and the front of your driver’s license clearly shows your face.
                        @else
                            Make sure your photos aren’t blurry and the front of your identity card clearly shows your face.
                        @endif
                    </p>
                </div>

                {{-- PASSPORT: Single Full-Width Box --}}
                @if ($id_type === 'passport')
                    <div 
                        x-data="{ isDragging: false }"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="
                            isDragging = false;
                            if ($event.dataTransfer.files.length > 0) {
                                $refs.frontInput.files = $event.dataTransfer.files;
                                $refs.frontInput.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        "
                        :class="isDragging ? 'border-primary bg-brand-light/20 scale-[1.005]' : 'border-border-subtle bg-surface hover:border-primary hover:bg-brand-light/10'"
                        class="group relative rounded-2xl border border-dashed p-10 sm:p-14 transition-all duration-200 overflow-hidden flex flex-col items-center justify-center text-center cursor-pointer min-h-[200px]"
                    >
                        <div wire:loading.flex wire:target="front_image" class="absolute inset-0 bg-surface/90 backdrop-blur-xs flex items-center justify-center z-20">
                            <span class="text-xs font-bold text-primary animate-pulse">Uploading passport...</span>
                        </div>

                        @if ($front_image)
                            <div class="flex flex-col items-center gap-3 z-10">
                                <img src="{{ $front_image->temporaryUrl() }}" class="w-24 h-16 object-cover rounded-xl border border-border-subtle shadow-xs">
                                <p class="text-xs font-medium text-text-main truncate max-w-[240px]">{{ $front_image->getClientOriginalName() }}</p>
                                <label for="front_file" class="text-xs font-bold text-primary hover:underline cursor-pointer">Change file</label>
                            </div>
                        @else
                            <label for="front_file" class="flex flex-col items-center justify-center cursor-pointer w-full h-full">
                                <!-- Open Booklet / Passport Icon -->
                                <svg class="w-10 h-10 text-text-main group-hover:text-primary transition-colors mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                <span class="text-base font-bold text-text-main group-hover:text-primary transition-colors">Upload passport</span>
                                <span class="text-xs text-text-muted mt-1">JPEG or PNG only</span>
                            </label>
                        @endif
                        <input id="front_file" x-ref="frontInput" type="file" wire:model="front_image" accept=".jpg,.jpeg,.png" class="hidden">
                    </div>
                    @error('front_image') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror

                {{-- DRIVER'S LICENSE & IDENTITY CARD: 2-Column Boxes --}}
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Front Box -->
                        <div>
                            <div 
                                x-data="{ isDragging: false }"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="
                                    isDragging = false;
                                    if ($event.dataTransfer.files.length > 0) {
                                        $refs.frontInput.files = $event.dataTransfer.files;
                                        $refs.frontInput.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                "
                                :class="isDragging ? 'border-primary bg-brand-light/20 scale-[1.005]' : 'border-border-subtle bg-surface hover:border-primary hover:bg-brand-light/10'"
                                class="group relative rounded-2xl border border-dashed p-8 transition-all duration-200 overflow-hidden flex flex-col items-center justify-center text-center cursor-pointer min-h-[190px]"
                            >
                                <div wire:loading.flex wire:target="front_image" class="absolute inset-0 bg-surface/90 backdrop-blur-xs flex items-center justify-center z-20">
                                    <span class="text-xs font-bold text-primary animate-pulse">Uploading front...</span>
                                </div>

                                @if ($front_image)
                                    <div class="flex flex-col items-center gap-2.5 z-10">
                                        <img src="{{ $front_image->temporaryUrl() }}" class="w-20 h-14 object-cover rounded-xl border border-border-subtle shadow-xs">
                                        <p class="text-xs font-medium text-text-main truncate max-w-[180px]">{{ $front_image->getClientOriginalName() }}</p>
                                        <label for="front_file" class="text-xs font-bold text-primary hover:underline cursor-pointer">Change</label>
                                    </div>
                                @else
                                    <label for="front_file" class="flex flex-col items-center justify-center cursor-pointer w-full h-full">
                                        <!-- ID Card Front Icon -->
                                        <svg class="w-9 h-9 text-text-main group-hover:text-primary transition-colors mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <rect x="3" y="4" width="18" height="16" rx="2" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="8" cy="11" r="2" stroke-linecap="round" stroke-linejoin="round" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 9h4m-4 4h4M6 16c0-1.5 1.5-2 2-2s2 .5 2 2" />
                                        </svg>
                                        <span class="text-sm font-bold text-text-main group-hover:text-primary transition-colors">Upload front</span>
                                        <span class="text-xs text-text-muted mt-0.5">JPEG or PNG only</span>
                                    </label>
                                @endif
                                <input id="front_file" x-ref="frontInput" type="file" wire:model="front_image" accept=".jpg,.jpeg,.png" class="hidden">
                            </div>
                            @error('front_image') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Back Box -->
                        <div>
                            <div 
                                x-data="{ isDragging: false }"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="
                                    isDragging = false;
                                    if ($event.dataTransfer.files.length > 0) {
                                        $refs.backInput.files = $event.dataTransfer.files;
                                        $refs.backInput.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                "
                                :class="isDragging ? 'border-primary bg-brand-light/20 scale-[1.005]' : 'border-border-subtle bg-surface hover:border-primary hover:bg-brand-light/10'"
                                class="group relative rounded-2xl border border-dashed p-8 transition-all duration-200 overflow-hidden flex flex-col items-center justify-center text-center cursor-pointer min-h-[190px]"
                            >
                                <div wire:loading.flex wire:target="back_image" class="absolute inset-0 bg-surface/90 backdrop-blur-xs flex items-center justify-center z-20">
                                    <span class="text-xs font-bold text-primary animate-pulse">Uploading back...</span>
                                </div>

                                @if ($back_image)
                                    <div class="flex flex-col items-center gap-2.5 z-10">
                                        <img src="{{ $back_image->temporaryUrl() }}" class="w-20 h-14 object-cover rounded-xl border border-border-subtle shadow-xs">
                                        <p class="text-xs font-medium text-text-main truncate max-w-[180px]">{{ $back_image->getClientOriginalName() }}</p>
                                        <label for="back_file" class="text-xs font-bold text-primary hover:underline cursor-pointer">Change</label>
                                    </div>
                                @else
                                    <label for="back_file" class="flex flex-col items-center justify-center cursor-pointer w-full h-full">
                                        <!-- ID Card Back Icon -->
                                        <svg class="w-9 h-9 text-text-main group-hover:text-primary transition-colors mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <rect x="3" y="4" width="18" height="16" rx="2" stroke-linecap="round" stroke-linejoin="round" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h18M7 15h3m4 0h3" />
                                        </svg>
                                        <span class="text-sm font-bold text-text-main group-hover:text-primary transition-colors">Upload back</span>
                                        <span class="text-xs text-text-muted mt-0.5">JPEG or PNG only</span>
                                    </label>
                                @endif
                                <input id="back_file" x-ref="backInput" type="file" wire:model="back_image" accept=".jpg,.jpeg,.png" class="hidden">
                            </div>
                            @error('back_image') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                <!-- Navigation Controls (Back & Low-Opacity Lock Button) -->
                <div class="pt-6 border-t border-border-subtle flex items-center justify-between">
                    <button type="button" 
                            wire:click="$set('currentScreen', 'id_type')" 
                            class="text-sm font-semibold text-text-muted hover:text-text-main transition-colors cursor-pointer">
                        &larr; Back
                    </button>

                    <button type="button" 
                            @if ($canContinue) wire:click="proceedToReview" @endif
                            @if (!$canContinue) disabled @endif
                            class="py-3 px-6 rounded-xl text-sm font-semibold flex items-center gap-2 transition-all 
                                {{ $canContinue 
                                    ? 'bg-text-main hover:bg-black text-surface cursor-pointer opacity-100' 
                                    : 'bg-neutral-100 text-neutral-400 opacity-40 cursor-not-allowed select-none' }}">
                        <!-- Lock Icon -->
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                        <span>Continue</span>
                    </button>
                </div>
            </div>

        {{-- 5. CAMERA CAPTURE --}}
        @elseif ($currentScreen === 'camera')
            <div 
                x-data="{
                    step: 'front',
                    requiresBack: {{ $id_type === 'passport' ? 'false' : 'true' }},
                    stream: null,
                    cameraError: null,
                    frontCaptured: null,
                    backCaptured: null,
                    initCamera() {
                        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 } }, audio: false })
                            .then(s => {
                                this.stream = s;
                                this.$refs.videoEl.srcObject = s;
                            })
                            .catch(err => {
                                this.cameraError = 'Unable to access camera: ' + err.message;
                            });
                    },
                    stopCamera() {
                        if (this.stream) {
                            this.stream.getTracks().forEach(track => track.stop());
                        }
                    },
                    capture() {
                        const canvas = document.createElement('canvas');
                        canvas.width = this.$refs.videoEl.videoWidth;
                        canvas.height = this.$refs.videoEl.videoHeight;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(this.$refs.videoEl, 0, 0);

                        canvas.toBlob((blob) => {
                            const file = new File([blob], `${this.step}_capture.jpg`, { type: 'image/jpeg' });
                            if (this.step === 'front') {
                                this.frontCaptured = URL.createObjectURL(blob);
                                @this.upload('front_image', file);
                                if (this.requiresBack) {
                                    this.step = 'back';
                                } else {
                                    this.stopCamera();
                                    @this.proceedToReview();
                                }
                            } else {
                                this.backCaptured = URL.createObjectURL(blob);
                                @this.upload('back_image', file, () => {
                                    this.stopCamera();
                                    @this.proceedToReview();
                                });
                            }
                        }, 'image/jpeg', 0.95);
                    }
                }"
                x-init="initCamera()"
                x-destroy="stopCamera()"
                class="max-w-2xl mx-auto space-y-5"
            >
                <div class="space-y-1">
                    <h2 class="text-2xl font-bold text-text-main font-sans" x-text="step === 'front' ? 'Position the front of your ID' : 'Flip and position the back of your ID'"></h2>
                    <p class="text-xs text-text-muted">Ensure all text and photos are clearly visible within the frame.</p>
                </div>

                <div class="relative bg-black rounded-2xl overflow-hidden aspect-[4/3] flex items-center justify-center">
                    <video x-ref="videoEl" autoplay playsinline class="w-full h-full object-cover"></video>
                    <div class="absolute inset-8 border-2 border-dashed border-white/50 rounded-xl pointer-events-none"></div>

                    <template x-if="cameraError">
                        <div class="absolute inset-0 bg-surface p-6 flex flex-col items-center justify-center text-center">
                            <p class="text-danger text-sm font-bold" x-text="cameraError"></p>
                            <button type="button" wire:click="$set('currentScreen', 'method')" class="mt-4 px-4 py-2 bg-text-main text-surface text-xs font-bold rounded-xl">Try Uploading Instead</button>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="button" @click="stopCamera(); $wire.set('currentScreen', 'id_type')" class="text-xs font-bold text-text-muted hover:text-text-main cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" @click="capture()" class="px-6 py-3 bg-text-main text-surface font-semibold text-sm rounded-xl shadow-xs hover:bg-black transition-all flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="7"/></svg>
                        <span x-text="step === 'front' && requiresBack ? 'Capture Front' : 'Capture & Proceed'"></span>
                    </button>
                </div>
            </div>

        {{-- 6. REVIEW SCREEN --}}
        @elseif ($currentScreen === 'review')
            <div class="max-w-2xl mx-auto space-y-6">
                <div class="space-y-1">
                    <h2 class="text-2xl sm:text-3xl font-bold text-text-main tracking-tight font-sans">
                        Review your verification
                    </h2>
                    <p class="text-xs text-text-muted">Ensure the details and images are clear before submission</p>
                </div>

                <div class="p-4 bg-surface-subtle rounded-2xl border border-border-subtle flex justify-between items-center text-xs">
                    <span class="text-text-muted">Document Type:</span>
                    <strong class="text-text-main capitalize">{{ str_replace('_', ' ', $id_type) }}</strong>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if ($front_image)
                        <div class="border border-border-subtle rounded-2xl p-3 bg-surface space-y-2">
                            <span class="text-[11px] font-bold text-text-muted uppercase">Front / Data Page</span>
                            <img src="{{ $front_image->temporaryUrl() }}" class="w-full h-36 object-cover rounded-xl border border-border-subtle">
                        </div>
                    @endif

                    @if ($back_image &&$id_type !== 'passport')
                        <div class="border border-border-subtle rounded-2xl p-3 bg-surface space-y-2">
                            <span class="text-[11px] font-bold text-text-muted uppercase">Back</span>
                            <img src="{{ $back_image->temporaryUrl() }}" class="w-full h-36 object-cover rounded-xl border border-border-subtle">
                        </div>
                    @endif
                </div>

                <div class="pt-4 flex items-center justify-between">
                    <button type="button" wire:click="$set('currentScreen', '{{$method }}')" class="py-2.5 px-5 text-sm font-semibold text-text-muted hover:text-text-main">
                        Retake / Change
                    </button>
                    <button type="button" wire:click="submit" wire:loading.attr="disabled" class="py-3 px-8 bg-text-main hover:bg-black text-surface font-semibold text-sm rounded-xl transition-all cursor-pointer">
                        <span wire:loading.remove wire:target="submit">Submit Verification</span>
                        <span wire:loading wire:target="submit">Submitting...</span>
                    </button>
                </div>
            </div>

        {{-- 7. SUBMITTED SCREEN --}}
        @elseif ($currentScreen === 'submitted')
            <div class="max-w-md mx-auto text-center py-10 space-y-4">
                <div class="w-16 h-16 bg-success/10 text-success rounded-full flex items-center justify-center mx-auto mb-2">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-text-main font-sans">Verification Submitted</h2>
                <p class="text-sm text-text-muted leading-relaxed">
                    Your government ID has been securely submitted. Our administrative compliance team will review your documents shortly.
                </p>
                <div class="pt-6">
                    <a href="{{ route('user.account-management') }}" class="px-8 py-3 bg-text-main hover:bg-black text-surface font-semibold text-sm rounded-xl transition-colors inline-block">
                        Return to Account
                    </a>
                </div>
            </div>
        @endif

    </div>
</div>