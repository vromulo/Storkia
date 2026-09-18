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
                Thank you for applying to be a logistics sorting center. Please wait for the administrator's review, which will be sent to <strong>{{ $email }}</strong>.
            </p>
            <a href="/" wire:navigate class="inline-flex py-3 px-6 bg-primary hover:bg-primary-dark text-surface font-bold rounded-xl shadow-md transition-colors cursor-pointer">
                Return to Homepage
            </a>
        </div>
    @else
        <div class="flex items-center justify-center mb-8 px-2 overflow-x-auto pb-4 scroll-smooth [&::-webkit-scrollbar]:hidden">
            @foreach (['Email', 'Personal', 'Address', 'Facility', 'Docs', 'Password', 'Review'] as $index => $label)
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

        {{-- Step 1: Email Verification --}}
        @if ($currentStep === 1)
            <div class="w-full max-w-md mx-auto space-y-4">
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">E-mail*</label>
                    <input type="email" wire:model.live.debounce.500ms="email" @if ($codeSent) disabled @endif
                        class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('email') border-danger @else border-border-subtle focus:border-text-main @enderror">
                    @error('email') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                @if (! $codeSent)
                    <button wire:click="sendCode" wire:loading.attr="disabled" class="w-full py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer disabled:opacity-60">
                        <span wire:loading.remove wire:target="sendCode">Send Verification Code</span>
                        <span wire:loading wire:target="sendCode">Sending...</span>
                    </button>
                @else
                    <div>
                        <label class="block text-xs font-bold text-text-main mb-1">Verification Code*</label>
                        <input type="text" wire:model="code" maxlength="6" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm tracking-[0.5em] text-center font-bold border-border-subtle focus:border-text-main">
                        @error('code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <button wire:click="verifyCode" class="w-full py-2.5 px-4 bg-primary text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer">Verify Code</button>
                @endif
            </div>
        @endif

        {{-- Step 2: Personal Info --}}
        @if ($currentStep === 2)
            <div class="w-full max-w-md mx-auto space-y-4">
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">First Name*</label>
                    <input type="text" wire:model.live.debounce.500ms="first_name" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('first_name') border-danger @else border-border-subtle focus:border-text-main @enderror">
                    @error('first_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Last Name*</label>
                    <input type="text" wire:model.live.debounce.500ms="last_name" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('last_name') border-danger @else border-border-subtle focus:border-text-main @enderror">
                    @error('last_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">M.I.</label>
                    <input type="text" wire:model.live.debounce.500ms="middle_initial" maxlength="1" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm text-center uppercase @error('middle_initial') border-danger @else border-border-subtle focus:border-text-main @enderror">
                    @error('middle_initial') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Sex*</label>
                    <div class="flex gap-4 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" wire:model.live="sex" value="male" class="w-4 h-4"><span class="text-sm font-medium">Male</span></label>
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" wire:model.live="sex" value="female" class="w-4 h-4"><span class="text-sm font-medium">Female</span></label>
                    </div>
                    @error('sex') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Birthday*</label>
                    <input type="date" wire:model.live="birthday" min="{{ now()->subYears(100)->format('Y-m-d') }}" max="{{ now()->subYears(18)->format('Y-m-d') }}" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('birthday') border-danger @else border-border-subtle focus:border-text-main @enderror">
                    @error('birthday') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(1)" class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">Back</button>
                    <button wire:click="nextStep(2)" class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer">Continue</button>
                </div>
            </div>
        @endif

        {{-- Step 3: Contact & Facility Address --}}
        @if ($currentStep === 3)
            <div class="w-full max-w-md mx-auto space-y-4">
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Contact No.*</label>
                    <div class="flex items-center w-full border-2 rounded-xl bg-surface transition-all shadow-sm overflow-hidden @error('contact_no') border-danger @else border-border-subtle focus-within:border-text-main @enderror">
                        <span class="pl-3 pr-2 text-sm font-bold text-text-muted border-r border-border-subtle bg-surface-subtle py-2">+63</span>
                        <input type="text" wire:model.live.debounce.500ms="contact_no" maxlength="10" placeholder="9XXXXXXXXX" class="w-full py-2 px-3 bg-transparent outline-none text-sm text-text-main">
                    </div>
                    @error('contact_no') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Province*</label>
                    <x-searchable-select wire:model.live="province_code" :options="$provinces" placeholder="Select Province" value-key="code" label-key="name" loading-target="loadProvinces" :hasError="$errors->has('province_code')" />
                    @error('province_code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Municipality*</label>
                    <x-searchable-select wire:model.live="municipality_code" :options="$municipalities" placeholder="Select Municipality" value-key="code" label-key="name" loading-target="province_code" :disabled="empty($municipalities)" :hasError="$errors->has('municipality_code')" />
                    @error('municipality_code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Barangay*</label>
                    <x-searchable-select wire:model.live="barangay_code" :options="$barangays" placeholder="Select Barangay" value-key="code" label-key="name" loading-target="municipality_code" :disabled="empty($barangays)" :hasError="$errors->has('barangay_code')" />
                    @error('barangay_code') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Street</label>
                    <input type="text" wire:model="street" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm border-border-subtle focus:border-text-main">
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">House / Building / Unit Details</label>
                    <input type="text" wire:model="house_details" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm border-border-subtle focus:border-text-main">
                </div>
                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(2)" class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">Back</button>
                    <button wire:click="nextStep(3)" class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer">Continue</button>
                </div>
            </div>
        @endif

        {{-- Step 4: Facility Info (No Line of Business) --}}
        @if ($currentStep === 4)
            <div class="w-full max-w-md mx-auto space-y-4">
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Sorting Center / Hub Name*</label>
                    <input type="text" wire:model.live.debounce.500ms="business_name" placeholder="e.g. Cavite Central Sorting Center" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('business_name') border-danger @else border-border-subtle focus:border-text-main @enderror">
                    @error('business_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(3)" class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">Back</button>
                    <button wire:click="nextStep(4)" class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer">Continue</button>
                </div>
            </div>
        @endif

        {{-- Step 5: Required Documents --}}
        @if ($currentStep === 5)
            <div class="w-full max-w-md mx-auto space-y-6">
                <div>
                    <label class="block text-xs font-bold text-text-main mb-2">Upload Valid ID* (JPG, PNG, PDF - Max 10MB)</label>
                    <input type="file" wire:model="valid_id" accept=".jpg,.jpeg,.png,.pdf" class="w-full p-2 rounded-xl border border-border-subtle text-xs bg-surface">
                    @error('valid_id') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-2">Upload Hub / Business Permit* (JPG, PNG, PDF - Max 10MB)</label>
                    <input type="file" wire:model="business_permit" accept=".jpg,.jpeg,.png,.pdf" class="w-full p-2 rounded-xl border border-border-subtle text-xs bg-surface">
                    @error('business_permit') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(4)" class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">Back</button>
                    <button wire:click="nextStep(5)" wire:loading.attr="disabled" class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer disabled:opacity-60">
                        <span wire:loading.remove wire:target="valid_id, business_permit">Continue</span>
                        <span wire:loading wire:target="valid_id, business_permit">Uploading...</span>
                    </button>
                </div>
            </div>
        @endif

        {{-- Step 6: Password --}}
        @if ($currentStep === 6)
            <div class="w-full max-w-md mx-auto space-y-4">
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Password*</label>
                    <input type="password" wire:model.live.debounce.500ms="password" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm @error('password') border-danger @else border-border-subtle @enderror">
                    @error('password') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-text-main mb-1">Confirm Password*</label>
                    <input type="password" wire:model.live.debounce.500ms="password_confirmation" class="w-full py-2 px-3 border-2 rounded-xl bg-surface outline-none text-sm border-border-subtle">
                </div>
                <div class="flex gap-3 mt-6">
                    <button wire:click="backToStep(5)" class="flex-1 py-2.5 px-4 bg-surface-subtle hover:bg-border-subtle text-text-main text-sm font-bold rounded-xl transition-colors cursor-pointer">Back</button>
                    <button wire:click="nextStep(6)" class="flex-[2] py-2.5 px-4 bg-primary hover:bg-primary-dark text-surface text-sm font-bold rounded-xl transition-colors cursor-pointer">Continue</button>
                </div>
            </div>
        @endif

        {{-- Step 7: Review & Submit --}}
        @if ($currentStep === 7)
            <div class="w-full max-w-xl mx-auto space-y-6">
                <div class="bg-surface-subtle rounded-2xl p-6 border border-border-subtle space-y-4 text-xs">
                    <div>
                        <h4 class="font-bold text-primary-dark border-b border-border-subtle pb-1 mb-2">Personal & Contact</h4>
                        <p><strong>Name:</strong> {{ $first_name }} {{ $middle_initial }} {{ $last_name }}</p>
                        <p><strong>Email:</strong> {{ $email }} (Verified)</p>
                        <p><strong>Contact:</strong> +63{{ $contact_no }}</p>
                    </div>
                    <div>
                        <h4 class="font-bold text-primary-dark border-b border-border-subtle pb-1 mb-2">Hub Location</h4>
                        <p>{{ $house_details ? $house_details . ', ' : '' }}{{ $street ? $street . ', ' : '' }}{{ $barangay }}, {{ $municipality }}, {{ $province }}</p>
                    </div>
                    <div>
                        <h4 class="font-bold text-primary-dark border-b border-border-subtle pb-1 mb-2">Facility Details</h4>
                        <p><strong>Center Name:</strong> {{ $business_name }}</p>
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
    @endif
</div>