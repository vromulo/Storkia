<div>
    @if ($registrationSuccessful)
        <div class="text-center py-8 animate-fade-in-up">
            <div class="w-20 h-20 bg-success/20 text-success rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-text-main mb-2">Registration Submitted!</h2>
            <p class="text-text-muted max-w-md mx-auto mb-8 leading-relaxed">
                Thank you for applying to be a seller. Please wait for the administrator's approval, which will be sent to <strong>{{ $email }}</strong>.
            </p>
            <a href="/" wire:navigate class="inline-flex py-3 px-6 bg-primary hover:bg-primary-dark text-surface font-bold rounded-xl shadow-md transition-colors cursor-pointer">
                Return to Homepage
            </a>
        </div>
    @else
        {{-- Step Progress Indicator --}}
        <div class="flex items-center justify-center mb-8 px-2 overflow-x-auto pb-4 scroll-smooth [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            @foreach (['Email', 'Personal', 'Address', 'Business', 'Docs', 'Password', 'Review'] as $index => $label)
                @php $num = $index + 1; @endphp
                <div class="flex items-center">
                    <div class="flex flex-col items-center gap-1 mx-1">
                        <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full flex items-center justify-center text-[10px] sm:text-xs font-bold transition-colors shrink-0
                            {{ $currentStep === $num ? 'bg-primary text-surface' : ($currentStep > $num ? 'bg-primary-dark text-surface' : 'bg-surface-subtle text-text-muted') }}">
                            @if ($currentStep > $num)
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            @else
                                {{ $num }}
                            @endif
                        </div>
                        <span class="text-[9px] sm:text-[10px] font-medium text-text-muted whitespace-nowrap hidden sm:block">{{ $label }}</span>
                    </div>
                    @if ($num < 7)
                        <div class="w-4 sm:w-6 h-0.5 shrink-0 {{ $currentStep > $num ? 'bg-primary-dark' : 'bg-border-subtle' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- STEP 1: Email --}}
        @if ($currentStep === 1)
            <div class="w-full max-w-md mx-auto space-y-4">
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">E-mail*</label>
                    <input type="email" wire:model.live.debounce.500ms="email" @if ($codeSent) disabled @endif
                        class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('email') border-danger focus:border-danger focus:ring-1 focus:ring-danger @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror">
                    @error('email') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                @if (! $codeSent)
                    <button wire:click="sendCode" wire:loading.attr="disabled" class="w-full py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors disabled:opacity-60 cursor-pointer">
                        <span wire:loading.remove wire:target="sendCode">Send Verification Code</span>
                        <span wire:loading wire:target="sendCode">Sending...</span>
                    </button>
                @else
                    <div>
                        <label class="block text-xs font-bold text-text-main mb-1">Verification Code*</label>
                        <input type="text" wire:model="code" maxlength="6" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm tracking-[0.5em] text-center font-bold border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main">
                        @error('code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <button wire:click="verifyCode" class="w-full py-2.5 px-4 bg-primary text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer">Verify Code</button>
                @endif
            </div>
        @endif

        {{-- STEP 2: Personal Info --}}
        @if ($currentStep === 2)
            <div class="w-full max-w-md mx-auto space-y-4">

                {{-- First Name --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">First Name*</label>
                    <input type="text" 
                        wire:model.live.debounce.500ms="first_name" 
                        x-on:input="$event.target.value = $event.target.value.replace(/^\s+/g, '').replace(/\s{2,}/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"
                        x-on:blur="$event.target.value = $event.target.value.trim(); $wire.set('first_name', $event.target.value)"
                        class="capitalize w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('first_name') border-danger focus:border-danger focus:ring-1 focus:ring-danger @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror">
                    @error('first_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Last Name --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Last Name*</label>
                    <input type="text" 
                        wire:model.live="last_name" 
                        x-on:input="$event.target.value = $event.target.value.replace(/^\s+/g, '').replace(/\s{2,}/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"
                        x-on:blur="$event.target.value = $event.target.value.trim(); $wire.set('last_name', $event.target.value)"
                        class="capitalize w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('last_name') border-danger focus:border-danger focus:ring-1 focus:ring-danger @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror">
                    @error('last_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Middle Initial --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">M.I.</label>
                    <input type="text" 
                        wire:model.live.debounce.500ms="middle_initial" 
                        maxlength="1" 
                        x-on:input="$event.target.value = $event.target.value.replace(/\s+/g, '').toUpperCase()"
                        class="uppercase w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('middle_initial') border-danger focus:border-danger focus:ring-1 focus:ring-danger @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror">
                    @error('middle_initial') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Sex --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Sex*</label>
                    <div class="flex gap-4 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="sex" value="male" 
                                class="w-4 h-4 text-text-main focus:ring-text-main border-border-subtle cursor-pointer">
                            <span class="text-sm font-medium">Male</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="sex" value="female" 
                                class="w-4 h-4 text-text-main focus:ring-text-main border-border-subtle cursor-pointer">
                            <span class="text-sm font-medium">Female</span>
                        </label>
                    </div>
                    @error('sex') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Birthday --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Birthday*</label>
                    <input type="date" 
                        wire:model.live="birthday" 
                        min="{{ now()->subYears(100)->format('Y-m-d') }}" 
                        max="{{ now()->subYears(18)->format('Y-m-d') }}" 
                        class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('birthday') border-danger focus:border-danger focus:ring-1 focus:ring-danger @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror">
                    @error('birthday') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(1)" class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">Back</button>
                    <button wire:click="nextStep(2)" class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer">Continue</button>
                </div>
            </div>
        @endif

        {{-- STEP 3: Contact & Address --}}
        @if ($currentStep === 3)
            <div class="w-full max-w-md mx-auto space-y-4">
                
                {{-- Contact No. --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Contact No.*</label>
                    <div class="flex items-center w-full border-2 rounded-xl bg-surface transition-all shadow-sm overflow-hidden 
                        @error('contact_no') border-danger focus-within:border-danger focus-within:ring-1 focus-within:ring-danger 
                        @else border-border-subtle focus-within:border-text-main focus-within:ring-1 focus-within:ring-text-main @enderror">

                        <span class="pl-3 pr-2 text-sm font-bold text-text-muted select-none border-r border-border-subtle bg-surface-subtle py-2">
                            +63
                        </span>

                        <input type="text" 
                            wire:model.live.debounce.500ms="contact_no" 
                            maxlength="10"
                            inputmode="numeric"
                            placeholder="9XXXXXXXXX"
                            x-on:input="
                                let val = $event.target.value.replace(/\D/g, ''); 
                                if (val.length > 0 && val[0] !== '9') {
                                    val = val.substring(1); 
                                }
                                $event.target.value = val.slice(0, 10);
                            "
                            class="w-full py-2 px-3 bg-transparent outline-none text-sm text-text-main">
                    </div>
                    @error('contact_no') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Province --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Province*</label>
                    <x-searchable-select 
                        wire:model.live="province_code"
                        :options="$provinces"
                        placeholder="Select Province"
                        value-key="code"
                        label-key="name"
                        loading-target="loadProvinces"
                        :hasError="$errors->has('province_code')"
                        class="cursor-pointer"
                    />
                    @error('province_code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Municipality --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Municipality*</label>
                    <x-searchable-select 
                        wire:model.live="municipality_code"
                        :options="$municipalities"
                        placeholder="Select Municipality"
                        value-key="code"
                        label-key="name"
                        loading-target="province_code"
                        :disabled="empty($municipalities)"
                        :hasError="$errors->has('municipality_code')"
                        class="cursor-pointer"
                    />
                    @error('municipality_code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Barangay --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Barangay*</label>
                    <x-searchable-select 
                        wire:model.live="barangay_code"
                        :options="$barangays"
                        placeholder="Select Barangay"
                        value-key="code"
                        label-key="name"
                        loading-target="municipality_code"
                        :disabled="empty($barangays)"
                        :hasError="$errors->has('barangay_code')"
                        class="cursor-pointer"
                    />
                    @error('barangay_code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Street --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Street</label>
                    <input type="text" 
                        wire:model="street" 
                        x-on:input="$event.target.value = $event.target.value.replace(/^\s+/g, '').replace(/\s{2,}/g, ' ')"
                        x-on:blur="$event.target.value = $event.target.value.trim(); $wire.set('street', $event.target.value)"
                        class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm border-border-subtle focus:border-text-main">
                </div>

                {{-- House/Unit/Building Details --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">House/Unit/Building Details</label>
                    <input type="text" 
                        wire:model="house_details" 
                        x-on:input="$event.target.value = $event.target.value.replace(/^\s+/g, '').replace(/\s{2,}/g, ' ')"
                        x-on:blur="$event.target.value = $event.target.value.trim(); $wire.set('house_details', $event.target.value)"
                        class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm border-border-subtle focus:border-text-main">
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(2)" class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">Back</button>
                    <button wire:click="nextStep(3)" class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer">Continue</button>
                </div>

            </div>
        @endif

        {{-- STEP 4: Business Info --}}
        @if ($currentStep === 4)
            <div class="w-full max-w-md mx-auto space-y-4">
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Business/Store Name*</label>
                    <input type="text" 
                        wire:model.live.debounce.500ms="business_name" 
                        x-on:input="$event.target.value = $event.target.value.replace(/^\s+/g, '').replace(/\s{2,}/g, ' ')"
                        x-on:blur="$event.target.value = $event.target.value.trim(); $wire.set('business_name', $event.target.value)"
                        class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('business_name') border-danger @else border-border-subtle focus:border-text-main @enderror">
                    @error('business_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Line of Business/Category*</label>
                    <x-searchable-select 
                        wire:model.live="line_of_business"
                        :options="[
                            'Pet', 'Kids', 
                            'Electronics', 'Home & Garden', 
                            'Women\'s', 'Men\'s',
                            'Health & Beauty', 'Books & Media',
                            'Sports & Outdoors', 'Food & Gourmet',
                            'Furniture & Office', 'Jewelry & Watches'
                        ]"
                        placeholder="Select Category"
                        :hasError="$errors->has('line_of_business')"
                        class="cursor-pointer"
                    />
                    @error('line_of_business') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(3)" class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">Back</button>
                    <button wire:click="nextStep(4)" class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer">Continue</button>
                </div>
            </div>
        @endif

        {{-- STEP 5: Required Documents --}}
        @if ($currentStep === 5)
            <div class="w-full max-w-md mx-auto space-y-6" x-data="{
                validIdUrl: null,
                permitUrl: null,
                handleFile(file, type) {
                    if(file && file.type === 'application/pdf') {
                        if (type === 'id') this.validIdUrl = URL.createObjectURL(file);
                        if (type === 'permit') this.permitUrl = URL.createObjectURL(file);
                    }
                }
            }">

                <!-- Valid ID Dropzone -->
                <div>
                    <label class="block text-xs font-bold text-text-main mb-2">Upload Valid ID* (JPG, PNG, PDF - Max 10MB)</label>
                    <div 
                        x-data="{ isDropping: false }"
                        @dragover.prevent="isDropping = true"
                        @dragleave.prevent="isDropping = false"
                        @drop.prevent="
                            isDropping = false;
                            if ($event.dataTransfer.files.length > 0) {
                                let file = $event.dataTransfer.files[0];
                                let validTypes = ['image/jpeg', 'image/png', 'application/pdf', 'image/jpg'];
                                if (!validTypes.includes(file.type) && !file.name.match(/\.(jpg|jpeg|png|pdf)$/i)) {
                                    alert('Only JPG, PNG, and PDF files are allowed.');
                                    return;
                                }
                                if (file.size > 10485760) {
                                    alert('File exceeds the 10MB limit.');
                                    return;
                                }
                                handleFile(file, 'id');
                                $refs.validIdInput.files = $event.dataTransfer.files;
                                $refs.validIdInput.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        "
                        :class="isDropping ? 'border-primary bg-brand-light/20 scale-[1.01]' : 'border-border-subtle bg-surface'"
                        class="relative w-full rounded-2xl border-2 border-dashed transition-all duration-200 overflow-hidden shadow-sm hover:border-text-main/40 @error('valid_id') border-danger @enderror"
                    >
                        <!-- Uploading Overlay Spinner -->
                        <div 
                            wire:loading.flex 
                            wire:target="valid_id" 
                            class="absolute inset-0 z-20 bg-surface/90 backdrop-blur-xs flex-col items-center justify-center space-y-2 p-4"
                        >
                            <svg class="animate-spin h-7 w-7 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <p class="text-xs font-bold text-primary-dark animate-pulse">Uploading Valid ID...</p>
                        </div>

                        <!-- Uploaded Preview Box -->
                        @if ($valid_id)
                            <div class="relative p-4 flex items-center justify-between gap-3 bg-surface-subtle">
                                <!-- Replace Overlay -->
                                <div x-show="isDropping" class="absolute inset-0 z-10 bg-brand-light/90 flex flex-col items-center justify-center pointer-events-none transition-opacity">
                                    <svg class="w-6 h-6 text-primary mb-1 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                    <span class="text-xs font-bold text-primary">Drop to replace file</span>
                                </div>

                                <div class="flex items-center gap-3 overflow-hidden">
                                    @php
                                        $extension = strtolower(pathinfo($valid_id->getClientOriginalName(), PATHINFO_EXTENSION));
                                    @endphp

                                    @if(in_array($extension, ['jpg', 'jpeg', 'png', 'webp']))
                                        <img src="{{ $valid_id->temporaryUrl() }}" alt="Valid ID" class="w-14 h-14 object-cover rounded-xl border border-border-subtle shrink-0 shadow-xs">
                                    @elseif($extension === 'pdf')
                                        <div class="w-14 h-14 rounded-xl bg-danger/10 text-danger flex flex-col items-center justify-center shrink-0 border border-danger/20 font-bold text-[10px]">
                                            <svg class="w-6 h-6 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            PDF
                                        </div>
                                    @else
                                        <div class="w-14 h-14 rounded-xl bg-danger/10 text-danger flex items-center justify-center shrink-0 border border-danger/20">
                                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif

                                    <div class="truncate">
                                        <p class="text-xs font-bold text-text-main truncate" title="{{ $valid_id->getClientOriginalName() }}">
                                            {{ $valid_id->getClientOriginalName() }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-[10px] text-text-muted font-medium">
                                                {{ round($valid_id->getSize() / 1024, 1) }} KB
                                            </span>
                                            <span class="text-[10px] text-success font-bold flex items-center gap-0.5">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                Ready
                                            </span>
                                            <template x-if="validIdUrl">
                                                <!-- SECURE LINK: rel="noopener noreferrer" added -->
                                                <a :href="validIdUrl" target="_blank" rel="noopener noreferrer" class="text-[10px] text-primary font-bold hover:underline flex items-center gap-0.5 ml-1">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    Preview PDF
                                                </a>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <label for="dropzone-id" class="text-xs font-bold text-primary hover:text-primary-dark cursor-pointer underline shrink-0 px-2 py-1 relative z-0">
                                    Change
                                </label>
                            </div>
                        @else
                            <!-- Empty Dropzone State -->
                            <label for="dropzone-id" class="flex flex-col items-center justify-center p-6 text-center cursor-pointer hover:bg-surface-subtle transition-colors">
                                <div class="w-12 h-12 rounded-full bg-brand-light/30 flex items-center justify-center mb-3 text-primary">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <p class="text-xs text-text-main font-semibold mb-1">
                                    <span class="text-primary font-bold">Click to upload</span> or drag and drop
                                </p>
                                <p class="text-[10px] text-text-muted">PNG, JPG, or PDF up to 10MB</p>
                            </label>
                        @endif

                        <input 
                            id="dropzone-id" 
                            x-ref="validIdInput"
                            type="file" 
                            wire:model="valid_id" 
                            class="hidden" 
                            accept=".jpg,.jpeg,.png,.pdf,application/pdf,image/png,image/jpeg" 
                            x-on:change="
                                if ($event.target.files.length > 0) {
                                    let file = $event.target.files[0];
                                    if (file.size > 10485760) {
                                        alert('File exceeds the 10MB limit.');
                                        $event.target.value = '';
                                    } else {
                                        handleFile(file, 'id');
                                    }
                                }
                            "
                        />
                    </div>
                    @error('valid_id') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Business Permit Dropzone -->
                <div>
                    <label class="block text-xs font-bold text-text-main mb-2">Upload Business Permit* (JPG, PNG, PDF - Max 10MB)</label>
                    <div 
                        x-data="{ isDropping: false }"
                        @dragover.prevent="isDropping = true"
                        @dragleave.prevent="isDropping = false"
                        @drop.prevent="
                            isDropping = false;
                            if ($event.dataTransfer.files.length > 0) {
                                let file = $event.dataTransfer.files[0];
                                let validTypes = ['image/jpeg', 'image/png', 'application/pdf', 'image/jpg'];
                                if (!validTypes.includes(file.type) && !file.name.match(/\.(jpg|jpeg|png|pdf)$/i)) {
                                    alert('Only JPG, PNG, and PDF files are allowed.');
                                    return;
                                }
                                if (file.size > 10485760) {
                                    alert('File exceeds the 10MB limit.');
                                    return;
                                }
                                handleFile(file, 'permit');
                                $refs.permitInput.files = $event.dataTransfer.files;
                                $refs.permitInput.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        "
                        :class="isDropping ? 'border-primary bg-brand-light/20 scale-[1.01]' : 'border-border-subtle bg-surface'"
                        class="relative w-full rounded-2xl border-2 border-dashed transition-all duration-200 overflow-hidden shadow-sm hover:border-text-main/40 @error('business_permit') border-danger @enderror"
                    >
                        <!-- Uploading Overlay Spinner -->
                        <div 
                            wire:loading.flex 
                            wire:target="business_permit" 
                            class="absolute inset-0 z-20 bg-surface/90 backdrop-blur-xs flex-col items-center justify-center space-y-2 p-4"
                        >
                            <svg class="animate-spin h-7 w-7 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <p class="text-xs font-bold text-primary-dark animate-pulse">Uploading Business Permit...</p>
                        </div>

                        <!-- Uploaded Preview Box -->
                        @if ($business_permit)
                            <div class="relative p-4 flex items-center justify-between gap-3 bg-surface-subtle">
                                <!-- Replace Overlay -->
                                <div x-show="isDropping" class="absolute inset-0 z-10 bg-brand-light/90 flex flex-col items-center justify-center pointer-events-none transition-opacity">
                                    <svg class="w-6 h-6 text-primary mb-1 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                    <span class="text-xs font-bold text-primary">Drop to replace file</span>
                                </div>

                                <div class="flex items-center gap-3 overflow-hidden">
                                    @php
                                        $extensionPermit = strtolower(pathinfo($business_permit->getClientOriginalName(), PATHINFO_EXTENSION));
                                    @endphp

                                    @if(in_array($extensionPermit, ['jpg', 'jpeg', 'png', 'webp']))
                                        <img src="{{ $business_permit->temporaryUrl() }}" alt="Business Permit" class="w-14 h-14 object-cover rounded-xl border border-border-subtle shrink-0 shadow-xs">
                                    @elseif($extensionPermit === 'pdf')
                                        <div class="w-14 h-14 rounded-xl bg-danger/10 text-danger flex flex-col items-center justify-center shrink-0 border border-danger/20 font-bold text-[10px]">
                                            <svg class="w-6 h-6 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            PDF
                                        </div>
                                    @else
                                        <div class="w-14 h-14 rounded-xl bg-danger/10 text-danger flex items-center justify-center shrink-0 border border-danger/20">
                                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif

                                    <div class="truncate">
                                        <p class="text-xs font-bold text-text-main truncate" title="{{ $business_permit->getClientOriginalName() }}">
                                            {{ $business_permit->getClientOriginalName() }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-[10px] text-text-muted font-medium">
                                                {{ round($business_permit->getSize() / 1024, 1) }} KB
                                            </span>
                                            <span class="text-[10px] text-success font-bold flex items-center gap-0.5">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                Ready
                                            </span>
                                            <template x-if="permitUrl">
                                                <!-- SECURE LINK: rel="noopener noreferrer" added -->
                                                <a :href="permitUrl" target="_blank" rel="noopener noreferrer" class="text-[10px] text-primary font-bold hover:underline flex items-center gap-0.5 ml-1">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    Preview PDF
                                                </a>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <label for="dropzone-permit" class="text-xs font-bold text-primary hover:text-primary-dark cursor-pointer underline shrink-0 px-2 py-1 relative z-0">
                                    Change
                                </label>
                            </div>
                        @else
                            <!-- Empty Dropzone State -->
                            <label for="dropzone-permit" class="flex flex-col items-center justify-center p-6 text-center cursor-pointer hover:bg-surface-subtle transition-colors">
                                <div class="w-12 h-12 rounded-full bg-brand-light/30 flex items-center justify-center mb-3 text-primary">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <p class="text-xs text-text-main font-semibold mb-1">
                                    <span class="text-primary font-bold">Click to upload</span> or drag and drop
                                </p>
                                <p class="text-[10px] text-text-muted">PNG, JPG, or PDF up to 10MB</p>
                            </label>
                        @endif

                        <input 
                            id="dropzone-permit" 
                            x-ref="permitInput"
                            type="file" 
                            wire:model="business_permit" 
                            class="hidden" 
                            accept=".jpg,.jpeg,.png,.pdf,application/pdf,image/png,image/jpeg" 
                            x-on:change="
                                if ($event.target.files.length > 0) {
                                    let file = $event.target.files[0];
                                    if (file.size > 10485760) {
                                        alert('File exceeds the 10MB limit.');
                                        $event.target.value = '';
                                    } else {
                                        handleFile(file, 'permit');
                                    }
                                }
                            "
                        />
                    </div>
                    @error('business_permit') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Navigation Buttons -->
                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(4)" class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">
                        Back
                    </button>
                    <button wire:click="nextStep(5)" wire:loading.attr="disabled" class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer disabled:opacity-60">
                        <span wire:loading.remove wire:target="valid_id, business_permit">Continue</span>
                        <span wire:loading wire:target="valid_id, business_permit">Uploading...</span>
                    </button>
                </div>
            </div>
        @endif

        {{-- STEP 6: Password --}}
        @if ($currentStep === 6)
            <div class="w-full max-w-md mx-auto space-y-4" x-data="{ showPass: false, showConfirm: false, pwd: '' }">
                
                {{-- Password Input --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Password*</label>
                    <div class="relative flex items-center">
                        <input 
                            :type="showPass ? 'text' : 'password'" 
                            wire:model.live.debounce.500ms="password" 
                            x-on:input="pwd = $event.target.value"
                            class="w-full py-2 pl-3 pr-10 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm
                            @error('password') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                            @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror"
                            placeholder="••••••••"
                        >
                        <button type="button" @click="showPass = !showPass" class="absolute right-3 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                            <svg x-show="showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="!showPass" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    @error('password') 
                        <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> 
                    @enderror
                </div>

                {{-- Confirm Password Input --}}
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Confirm Password*</label>
                    <div class="relative flex items-center">
                        <input 
                            :type="showConfirm ? 'text' : 'password'" 
                            wire:model.live.debounce.500ms="password_confirmation" 
                            class="w-full py-2 pl-3 pr-10 border-2 rounded-xl bg-surface outline-none transition-all shadow-sm text-sm
                            @error('password_confirmation') border-danger focus:border-danger focus:ring-1 focus:ring-danger 
                            @else border-border-subtle focus:border-text-main focus:ring-1 focus:ring-text-main @enderror"
                            placeholder="••••••••"
                        >
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                            <svg x-show="showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="!showConfirm" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    @error('password_confirmation') 
                        <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> 
                    @enderror
                </div>

                {{-- Live Password Validator List --}}
                <div class="mt-4 p-4 rounded-xl bg-surface-subtle border border-border-subtle text-xs space-y-2 shadow-sm">
                    <p class="font-bold text-text-main mb-2">Password Requirements:</p>
                    <ul class="space-y-1.5">
                        <li class="flex items-center gap-2 transition-colors duration-200" :class="pwd.length >= 8 ? 'text-success' : 'text-text-muted'">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="pwd.length >= 8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                <circle x-show="pwd.length < 8" cx="12" cy="12" r="9" stroke-width="2"></circle>
                            </svg>
                            <span>At least 8 characters</span>
                        </li>
                        <li class="flex items-center gap-2 transition-colors duration-200" :class="/[A-Z]/.test(pwd) ? 'text-success' : 'text-text-muted'">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="/[A-Z]/.test(pwd)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                <circle x-show="!/[A-Z]/.test(pwd)" cx="12" cy="12" r="9" stroke-width="2"></circle>
                            </svg>
                            <span>At least 1 uppercase letter</span>
                        </li>
                        <li class="flex items-center gap-2 transition-colors duration-200" :class="/[a-z]/.test(pwd) ? 'text-success' : 'text-text-muted'">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="/[a-z]/.test(pwd)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                <circle x-show="!/[a-z]/.test(pwd)" cx="12" cy="12" r="9" stroke-width="2"></circle>
                            </svg>
                            <span>At least 1 lowercase letter</span>
                        </li>
                        <li class="flex items-center gap-2 transition-colors duration-200" :class="/[0-9]/.test(pwd) ? 'text-success' : 'text-text-muted'">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="/[0-9]/.test(pwd)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                <circle x-show="!/[0-9]/.test(pwd)" cx="12" cy="12" r="9" stroke-width="2"></circle>
                            </svg>
                            <span>At least 1 number</span>
                        </li>
                        <li class="flex items-center gap-2 transition-colors duration-200" :class="/[^A-Za-z0-9]/.test(pwd) ? 'text-success' : 'text-text-muted'">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path x-show="/[^A-Za-z0-9]/.test(pwd)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                <circle x-show="!/[^A-Za-z0-9]/.test(pwd)" cx="12" cy="12" r="9" stroke-width="2"></circle>
                            </svg>
                            <span>At least 1 special character</span>
                        </li>
                    </ul>
                </div>

                {{-- Action Buttons --}}
                <div class="flex gap-3 mt-6">
                    <button 
                        wire:click="backToStep(5)" 
                        type="button"
                        class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer"
                    >
                        Back
                    </button>
                    <button 
                        wire:click="nextStep(6)" 
                        type="button"
                        class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer"
                    >
                        Continue
                    </button>
                </div>
            </div>
        @endif

        {{-- STEP 7: Review & Submit --}}
        @if ($currentStep === 7)
            <div class="w-full max-w-xl mx-auto space-y-6">
                <!-- Summary Card -->
                <div class="bg-surface-subtle rounded-2xl p-6 border border-border-subtle space-y-6">
                    
                    <div>
                        <div class="flex justify-between items-center mb-2 border-b border-border-subtle pb-1">
                            <h3 class="text-sm font-bold text-primary-dark uppercase tracking-wide">Account & Personal</h3>
                            <button wire:click="backToStep(2)" class="text-xs text-primary hover:underline font-bold cursor-pointer">Edit</button>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <p class="text-text-muted">Email:</p><p class="font-medium text-text-main">{{ $email }} <span class="text-success text-xs font-bold ml-1">(Verified)</span></p>
                            <p class="text-text-muted">Name:</p><p class="font-medium text-text-main">{{ $first_name }} {{ $middle_initial }} {{ $last_name }}</p>
                            <p class="text-text-muted">Sex:</p><p class="font-medium text-text-main capitalize">{{ $sex }}</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-2 border-b border-border-subtle pb-1">
                            <h3 class="text-sm font-bold text-primary-dark uppercase tracking-wide">Contact & Address</h3>
                            <button wire:click="backToStep(3)" class="text-xs text-primary hover:underline font-bold cursor-pointer">Edit</button>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <p class="text-text-muted">Contact No:</p><p class="font-medium text-text-main">+63{{ $contact_no }}</p>
                            <p class="text-text-muted">Address:</p>
                            <p class="font-medium text-text-main">
                                {{ $house_details ? $house_details . ', ' : '' }}{{ $street ? $street . ', ' : '' }}
                                {{ $barangay }}, {{ $municipality }}, {{ $province }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-2 border-b border-border-subtle pb-1">
                            <h3 class="text-sm font-bold text-primary-dark uppercase tracking-wide">Business Information</h3>
                            <button wire:click="backToStep(4)" class="text-xs text-primary hover:underline font-bold cursor-pointer">Edit</button>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <p class="text-text-muted">Store Name:</p><p class="font-medium text-text-main">{{ $business_name }}</p>
                            <p class="text-text-muted">Category:</p><p class="font-medium text-text-main">{{ $line_of_business }}</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-2 border-b border-border-subtle pb-1">
                            <h3 class="text-sm font-bold text-primary-dark uppercase tracking-wide">Documents</h3>
                            <button wire:click="backToStep(5)" class="text-xs text-primary hover:underline font-bold cursor-pointer">Edit</button>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <p class="text-text-muted flex items-center"><svg class="w-4 h-4 mr-1 text-success" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg> Valid ID</p>
                            <p class="font-medium text-text-main truncate" title="{{ $valid_id ? $valid_id->getClientOriginalName() : '' }}">{{ $valid_id ? $valid_id->getClientOriginalName() : 'Attached' }}</p>
                            <p class="text-text-muted flex items-center"><svg class="w-4 h-4 mr-1 text-success" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg> Business Permit</p>
                            <p class="font-medium text-text-main truncate" title="{{ $business_permit ? $business_permit->getClientOriginalName() : '' }}">{{ $business_permit ? $business_permit->getClientOriginalName() : 'Attached' }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(6)" class="flex-1 py-3 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">Back</button>
                    <button wire:click="register" wire:loading.attr="disabled" class="flex-[2] py-3 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl shadow-md transition-colors disabled:opacity-60 cursor-pointer">
                        <span wire:loading.remove wire:target="register">Submit Application</span>
                        <span wire:loading wire:target="register">Processing...</span>
                    </button>
                </div>
            </div>
        @endif

        {{-- Already have an account link --}}
        <div class="text-center mt-8 max-w-md mx-auto">
            <p class="text-xs text-text-muted">
                Already have an account? 
                <a href="{{ route('seller.login') }}" wire:navigate class="font-bold text-primary hover:text-primary-dark hover:underline transition-colors cursor-pointer">Sign in here</a>
            </p>
        </div>
    @endif
</div>