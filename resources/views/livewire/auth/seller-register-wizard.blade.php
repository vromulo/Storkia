<div class="w-full">
    @if ($registrationSuccessful)
        <div class="text-center py-10 animate-fade-in-up">
            <div class="w-16 h-16 bg-success/10 text-success rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="text-3xl font-serif text-primary-dark mb-2">Application Submitted</h2>
            <p class="text-text-muted text-sm max-w-md mx-auto mb-8 leading-relaxed font-normal">
                Thank you for applying to sell on Storkia. Your credentials and documents are under review. Status updates will be sent to <span class="font-semibold text-text-main">{{ $email }}</span>.
            </p>
            <a href="/" wire:navigate class="inline-flex py-3 px-8 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs transition-all cursor-pointer">
                Return to Homepage
            </a>
        </div>
    @else
        <!-- Header Copy -->
        <div class="mb-8 text-center">
            <h1 class="text-2xl sm:text-3xl font-serif font-normal tracking-tight text-primary-dark">
                Become a Seller
            </h1>
            <p class="mt-2 text-sm text-text-muted font-normal leading-relaxed">
                @if ($currentStep === 1) Verify your email to start your store setup
                @elseif ($currentStep === 2) Personal details of the store owner
                @elseif ($currentStep === 3) Official business contact & address
                @elseif ($currentStep === 4) Store branding & category
                @elseif ($currentStep === 5) Business verification documents
                @elseif ($currentStep === 6) Set up your merchant login credentials
                @elseif ($currentStep === 7) Review and submit your merchant application
                @endif
            </p>
        </div>

        <!-- Minimalist Step Indicator -->
        <div class="flex items-center justify-center gap-1.5 mb-10 overflow-x-auto pb-2 [&::-webkit-scrollbar]:hidden">
            @foreach (['Email', 'Personal', 'Address', 'Business', 'Docs', 'Password', 'Review'] as $index => $label)
                @php $num = $index + 1; @endphp
                <div class="flex items-center gap-1.5 shrink-0">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-all duration-300
                        {{ $currentStep === $num ? 'bg-primary-dark text-surface' : ($currentStep > $num ? 'bg-brand-light text-primary-dark' : 'bg-surface-subtle text-text-muted/60') }}">
                        @if ($currentStep > $num)
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                        @else
                            {{ $num }}
                        @endif
                    </div>
                    @if ($num < 7)
                        <div class="w-3 sm:w-5 h-[1px] {{ $currentStep > $num ? 'bg-primary-dark/40' : 'bg-border-subtle' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- STEP 1: Email Verification --}}
        @if ($currentStep === 1)
            <div class="space-y-6">
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                        Email Address*
                    </label>
                    <div class="relative flex items-center border-b {{ $errors->has('email') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="email" 
                            wire:model.live.debounce.500ms="email" 
                            @if ($codeSent) disabled @endif
                            class="w-full py-3 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 disabled:text-text-muted/50"
                            placeholder="store@example.com"
                        >
                    </div>
                    @error('email') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                @if (! $codeSent)
                    <div class="pt-2">
                        <button 
                            type="button" 
                            wire:click="sendCode" 
                            wire:loading.attr="disabled" 
                            wire:target="sendCode"
                            class="w-full py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="sendCode">Send Verification Code</span>
                            <span wire:loading wire:target="sendCode">Sending Code...</span>
                        </button>
                    </div>
                @else
                    <div>
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">
                            Verification Code*
                        </label>
                        <div class="relative flex items-center border-b {{ $errors->has('code') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                            <input 
                                type="text" 
                                wire:model="code" 
                                inputmode="numeric" 
                                maxlength="6" 
                                autocomplete="one-time-code"
                                class="w-full py-3 bg-transparent text-text-main text-lg tracking-[0.4em] font-semibold text-center outline-none placeholder:text-text-muted/40 placeholder:tracking-normal"
                                placeholder="000000"
                            >
                        </div>
                        @error('code') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                        <p class="text-text-muted text-xs mt-2 text-center">
                            Code sent to <span class="font-semibold text-text-main">{{ $email }}</span>
                        </p>
                    </div>

                    <div class="space-y-3 pt-2">
                        <button 
                            type="button" 
                            wire:click="verifyCode" 
                            wire:loading.attr="disabled" 
                            wire:target="verifyCode"
                            class="w-full py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="verifyCode">Verify & Continue</span>
                            <span wire:loading wire:target="verifyCode">Verifying...</span>
                        </button>
                        <div class="text-center" x-data="{ seconds: {{ $resendCooldown }} }"
                             x-init="let t = setInterval(() => { if (seconds > 0) seconds--; else clearInterval(t); }, 1000)">
                            <button 
                                type="button" 
                                wire:click="resendCode" 
                                wire:loading.attr="disabled" 
                                wire:target="resendCode"
                                x-bind:disabled="seconds > 0"
                                class="text-xs text-text-muted hover:text-primary transition-colors cursor-pointer disabled:text-text-muted/40 font-medium"
                            >
                                <span x-show="seconds === 0">Resend Code</span>
                                <span x-show="seconds > 0">Resend available in <span x-text="seconds"></span>s</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- STEP 2: Personal Information --}}
        @if ($currentStep === 2)
            <div class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="sm:col-span-3">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">First Name*</label>
                        <div class="relative border-b {{ $errors->has('first_name') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                            <input 
                                type="text" 
                                wire:model.live.debounce.500ms="first_name"
                                x-on:input="$event.target.value = $event.target.value.replace(/^\s+/, '').replace(/ {2,}/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"
                                class="capitalize w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                                placeholder="John"
                            >
                        </div>
                        @error('first_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">M.I.</label>
                        <div class="relative border-b {{ $errors->has('middle_initial') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                            <input 
                                type="text" 
                                wire:model.live.debounce.500ms="middle_initial" 
                                maxlength="1"
                                x-on:input="$event.target.value = $event.target.value.replace(/\s+/g, '').toUpperCase()"
                                class="uppercase w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 text-center sm:text-left"
                                placeholder="D"
                            >
                        </div>
                        @error('middle_initial') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Last Name*</label>
                    <div class="relative border-b {{ $errors->has('last_name') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.500ms="last_name"
                            x-on:input="$event.target.value = $event.target.value.replace(/^\s+/, '').replace(/ {2,}/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"
                            class="capitalize w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                            placeholder="Doe"
                        >
                    </div>
                    @error('last_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-2">Sex*</label>
                    <div class="flex gap-6 pt-1">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="radio" wire:model.live="sex" value="male" class="w-4 h-4 text-primary-dark focus:ring-primary-dark border-border-subtle cursor-pointer">
                            <span class="text-sm font-medium text-text-main group-hover:text-primary-dark transition-colors">Male</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="radio" wire:model.live="sex" value="female" class="w-4 h-4 text-primary-dark focus:ring-primary-dark border-border-subtle cursor-pointer">
                            <span class="text-sm font-medium text-text-main group-hover:text-primary-dark transition-colors">Female</span>
                        </label>
                    </div>
                    @error('sex') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Birthday*</label>
                    <div class="relative border-b {{ $errors->has('birthday') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="date" 
                            wire:model.live="birthday"
                            min="{{ now()->subYears(100)->format('Y-m-d') }}"
                            max="{{ now()->subYears(18)->format('Y-m-d') }}"
                            class="w-full py-2.5 bg-transparent text-text-main text-base outline-none cursor-pointer"
                        >
                    </div>
                    @error('birthday') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(1)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="nextStep(2)" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]">Continue</button>
                </div>
            </div>
        @endif

        {{-- STEP 3: Contact & Address --}}
        @if ($currentStep === 3)
            <div class="space-y-5">
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Contact No.*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('contact_no') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <span class="text-sm font-semibold text-text-muted pr-2">+63</span>
                        <input 
                            type="text" 
                            wire:model.live.debounce.500ms="contact_no" 
                            maxlength="10" 
                            placeholder="9XXXXXXXXX" 
                            class="w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                        >
                    </div>
                    @error('contact_no') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Province*</label>
                    <x-searchable-select 
                        wire:model.live="province_code" 
                        :options="$provinces" 
                        placeholder="Select Province" 
                        value-key="code" 
                        label-key="name" 
                        loading-target="loadProvinces" 
                        :hasError="$errors->has('province_code')" 
                    />
                    @error('province_code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Municipality / City*</label>
                    <x-searchable-select 
                        wire:model.live="municipality_code" 
                        :options="$municipalities" 
                        placeholder="Select Municipality" 
                        value-key="code" 
                        label-key="name" 
                        loading-target="province_code" 
                        :disabled="empty($municipalities)" 
                        :hasError="$errors->has('municipality_code')" 
                    />
                    @error('municipality_code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Barangay*</label>
                    <x-searchable-select 
                        wire:model.live="barangay_code" 
                        :options="$barangays" 
                        placeholder="Select Barangay" 
                        value-key="code" 
                        label-key="name" 
                        loading-target="municipality_code" 
                        :disabled="empty($barangays)" 
                        :hasError="$errors->has('barangay_code')" 
                    />
                    @error('barangay_code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Street</label>
                    <div class="relative border-b border-border-subtle focus-within:border-text-main transition-colors">
                        <input type="text" wire:model="street" class="w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40" placeholder="e.g. Rizal Street">
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">House / Unit / Building Details</label>
                    <div class="relative border-b border-border-subtle focus-within:border-text-main transition-colors">
                        <input type="text" wire:model="house_details" class="w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40" placeholder="e.g. Blk 10 Lot 4, Unit 201">
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(2)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="nextStep(3)" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]">Continue</button>
                </div>
            </div>
        @endif

        {{-- STEP 4: Business Information --}}
        @if ($currentStep === 4)
            <div class="space-y-6">
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Store / Business Name*</label>
                    <div class="relative border-b {{ $errors->has('business_name') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input type="text" wire:model.live.debounce.500ms="business_name" class="w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40" placeholder="e.g. Aurelia Lifestyle">
                    </div>
                    @error('business_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Line of Business / Category*</label>
                    <x-searchable-select 
                        wire:model.live="line_of_business" 
                        :options="['Pet', 'Kids', 'Electronics', 'Home & Garden', 'Women\'s', 'Men\'s', 'Health & Beauty', 'Books & Media', 'Sports & Outdoors', 'Food & Gourmet', 'Furniture & Office', 'Jewelry & Watches']" 
                        placeholder="Select Category" 
                        :hasError="$errors->has('line_of_business')" 
                    />
                    @error('line_of_business') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(3)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="nextStep(4)" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]">Continue</button>
                </div>
            </div>
        @endif

        {{-- STEP 5: Required Documents --}}
        @if ($currentStep === 5)
            <div class="space-y-6">
                <!-- Valid ID -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted">Valid Government ID*</label>
                        <span class="text-[11px] text-text-muted">JPG, PNG, PDF (Max 10MB)</span>
                    </div>
                    <div class="relative border-b {{ $errors->has('valid_id') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} py-2 transition-colors">
                        <input type="file" wire:model="valid_id" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-xs text-text-main file:mr-3 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-surface-subtle file:text-primary-dark hover:file:bg-brand-light/40 cursor-pointer">
                    </div>
                    <div wire:loading wire:target="valid_id" class="text-xs text-primary font-medium mt-1">Uploading Valid ID...</div>
                    @if ($valid_id)
                        <p class="text-xs text-success mt-1.5 font-semibold flex items-center gap-1">✓ Attached: {{ $valid_id->getClientOriginalName() }}</p>
                    @endif
                    @error('valid_id') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Business Permit -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted">Business Permit / DTI / Mayor's Permit*</label>
                        <span class="text-[11px] text-text-muted">JPG, PNG, PDF (Max 10MB)</span>
                    </div>
                    <div class="relative border-b {{ $errors->has('business_permit') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} py-2 transition-colors">
                        <input type="file" wire:model="business_permit" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-xs text-text-main file:mr-3 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-surface-subtle file:text-primary-dark hover:file:bg-brand-light/40 cursor-pointer">
                    </div>
                    <div wire:loading wire:target="business_permit" class="text-xs text-primary font-medium mt-1">Uploading Permit...</div>
                    @if ($business_permit)
                        <p class="text-xs text-success mt-1.5 font-semibold flex items-center gap-1">✓ Attached: {{ $business_permit->getClientOriginalName() }}</p>
                    @endif
                    @error('business_permit') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(4)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="nextStep(5)" wire:loading.attr="disabled" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60">
                        <span wire:loading.remove wire:target="valid_id, business_permit">Continue</span>
                        <span wire:loading wire:target="valid_id, business_permit">Processing Files...</span>
                    </button>
                </div>
            </div>
        @endif

        {{-- STEP 6: Password --}}
        @if ($currentStep === 6)
            <div class="space-y-5" x-data="{ showPass: false, showConfirm: false }">
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Password*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('password') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            :type="showPass ? 'text' : 'password'" 
                            wire:model.live.debounce.500ms="password" 
                            class="w-full py-2.5 pr-8 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 tracking-wider" 
                            placeholder="••••••••"
                        >
                        <button type="button" @click="showPass = !showPass" class="absolute right-0 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                            <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPass" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    @error('password') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Confirm Password*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('password_confirmation') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            :type="showConfirm ? 'text' : 'password'" 
                            wire:model.live.debounce.500ms="password_confirmation" 
                            class="w-full py-2.5 pr-8 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40 tracking-wider" 
                            placeholder="••••••••"
                        >
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute right-0 text-text-muted hover:text-text-main transition-colors focus:outline-none cursor-pointer">
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showConfirm" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    @error('password_confirmation') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <!-- Password Requirements Indicator -->
                <div class="pt-2 grid grid-cols-2 gap-1.5 text-[11px] text-text-muted">
                    <span class="flex items-center gap-1 {{ strlen($password) >= 8 ? 'text-success font-semibold' : '' }}">
                        <span>{{ strlen($password) >= 8 ? '✓' : '•' }}</span> 8+ characters
                    </span>
                    <span class="flex items-center gap-1 {{ preg_match('/[A-Z]/', $password) ? 'text-success font-semibold' : '' }}">
                        <span>{{ preg_match('/[A-Z]/', $password) ? '✓' : '•' }}</span> 1 uppercase
                    </span>
                    <span class="flex items-center gap-1 {{ preg_match('/[0-9]/', $password) ? 'text-success font-semibold' : '' }}">
                        <span>{{ preg_match('/[0-9]/', $password) ? '✓' : '•' }}</span> 1 number
                    </span>
                    <span class="flex items-center gap-1 {{ preg_match('/[\W_]/', $password) ? 'text-success font-semibold' : '' }}">
                        <span>{{ preg_match('/[\W_]/', $password) ? '✓' : '•' }}</span> 1 special symbol
                    </span>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(5)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="nextStep(6)" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]">Continue</button>
                </div>
            </div>
        @endif

        {{-- STEP 7: Review & Submit --}}
        @if ($currentStep === 7)
            <div class="space-y-6 text-xs">
                <!-- Summary List (Clean Line Separators) -->
                <div class="space-y-5">
                    <div class="border-b border-border-subtle/70 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="uppercase tracking-wider font-bold text-primary-dark">Personal & Contact</span>
                            <button type="button" wire:click="backToStep(2)" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Edit</button>
                        </div>
                        <div class="space-y-1 text-text-muted">
                            <p><span class="text-text-main font-semibold">Name:</span> {{ $first_name }} {{ $middle_initial }} {{ $last_name }} ({{ ucfirst($sex) }})</p>
                            <p><span class="text-text-main font-semibold">Email:</span> {{ $email }}</p>
                            <p><span class="text-text-main font-semibold">Phone:</span> +63{{ $contact_no }}</p>
                        </div>
                    </div>

                    <div class="border-b border-border-subtle/70 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="uppercase tracking-wider font-bold text-primary-dark">Business Location</span>
                            <button type="button" wire:click="backToStep(3)" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Edit</button>
                        </div>
                        <p class="text-text-muted">
                            {{ $house_details ? $house_details . ', ' : '' }}{{ $street ? $street . ', ' : '' }}{{ $barangay }}, {{ $municipality }}, {{ $province }}
                        </p>
                    </div>

                    <div class="border-b border-border-subtle/70 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="uppercase tracking-wider font-bold text-primary-dark">Store Details</span>
                            <button type="button" wire:click="backToStep(4)" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Edit</button>
                        </div>
                        <div class="space-y-1 text-text-muted">
                            <p><span class="text-text-main font-semibold">Store Name:</span> {{ $business_name }}</p>
                            <p><span class="text-text-main font-semibold">Category:</span> {{ $line_of_business }}</p>
                        </div>
                    </div>

                    <div class="border-b border-border-subtle/70 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="uppercase tracking-wider font-bold text-primary-dark">Verification Documents</span>
                            <button type="button" wire:click="backToStep(5)" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Edit</button>
                        </div>
                        <div class="space-y-1 text-text-muted">
                            <p><span class="text-text-main font-semibold">Valid ID:</span> {{ $valid_id ? $valid_id->getClientOriginalName() : 'Attached' }}</p>
                            <p><span class="text-text-main font-semibold">Business Permit:</span> {{ $business_permit ? $business_permit->getClientOriginalName() : 'Attached' }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" wire:click="backToStep(6)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button 
                        type="button" 
                        wire:click="register" 
                        wire:loading.attr="disabled" 
                        class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="register">Submit Application</span>
                        <span wire:loading wire:target="register">Submitting Application...</span>
                    </button>
                </div>
            </div>
        @endif

        <!-- Footer Redirection -->
        <div class="mt-8 pt-8 border-t border-border-subtle/50 text-center">
            <p class="text-xs text-text-muted">
                Already registered as a seller? 
                <a href="{{ route('seller.login') }}" wire:navigate class="ml-1 font-bold text-primary hover:text-primary-dark underline underline-offset-4 transition-colors cursor-pointer">
                    Sign in here
                </a>
            </p>
        </div>
    @endif
</div>