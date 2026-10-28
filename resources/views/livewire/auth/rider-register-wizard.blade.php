<div class="w-full">
    @if ($registrationSuccessful)
        <div class="text-center py-10 animate-fade-in-up">
            <div class="w-16 h-16 bg-success/10 text-success rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="text-3xl font-serif text-primary-dark mb-2">Rider Account Created</h2>
            <p class="text-text-muted text-sm max-w-md mx-auto mb-6 leading-relaxed font-normal">
                Delivery Rider <span class="font-semibold text-text-main">{{ $first_name }} {{ $last_name }}</span> has been created and assigned to <span class="font-semibold text-text-main">{{ $selectedLogisticsName }} ({{ $selectedHubMunicipality }})</span>.
            </p>
            <div class="max-w-md mx-auto mb-8 p-4 rounded-2xl bg-surface-subtle border border-border-subtle text-xs space-y-1.5 text-left">
                <div class="flex justify-between">
                    <span class="text-text-muted">Internal Login Email:</span>
                    <span class="font-mono font-bold text-primary-dark">{{ $email }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Assigned Hub:</span>
                    <span class="font-semibold text-text-main">{{ $selectedLogisticsName }} ({{ $selectedHubMunicipality }})</span>
                </div>
                <div>
                    <span class="text-text-muted block mb-1">Barangay Coverage:</span>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($selectedBarangays as $bName)
                            <span class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-800 border border-blue-200 text-[11px] font-semibold">{{ $bName }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-between pt-1">
                    <span class="text-text-muted">Account Status:</span>
                    <span class="font-bold text-success uppercase">Approved & Active</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('logistics.employees.index') }}" class="inline-flex py-3 px-8 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs transition-all cursor-pointer">
                    Open Employee Management
                </a>
                <a href="{{ route('logistics.riders.active') }}" class="inline-flex py-3 px-6 bg-surface border border-border-subtle hover:border-primary text-text-main text-sm font-semibold rounded-full transition-all cursor-pointer">
                    View Active Riders
                </a>
            </div>
        </div>
    @else
        <div class="mb-8 text-center">
            <h1 class="text-2xl sm:text-3xl font-serif font-normal tracking-tight text-primary-dark">
                Create Delivery Rider Account
            </h1>
            <p class="mt-2 text-sm text-text-muted font-normal leading-relaxed">
                @if ($currentStep === 1) Internal @storkia.shop email, hub municipality, barangay coverage & personal details
                @elseif ($currentStep === 2) Driver's license & delivery vehicle information
                @elseif ($currentStep === 3) Upload driver's license and vehicle OR/CR documents
                @elseif ($currentStep === 4) Set up rider login password
                @elseif ($currentStep === 5) Review and confirm rider account creation
                @endif
            </p>
        </div>

        <div class="flex items-center justify-center gap-1.5 mb-10 overflow-x-auto pb-2 [&::-webkit-scrollbar]:hidden">
            @foreach (['Personal & Hub', 'Vehicle', 'Docs', 'Password', 'Review'] as $index => $label)
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
                    @if ($num < 5)
                        <div class="w-4 sm:w-6 h-[1px] {{ $currentStep > $num ? 'bg-primary-dark/40' : 'bg-border-subtle' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($currentStep === 1)
            <div class="space-y-5">
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Email Username*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('email_prefix') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="email_prefix" 
                            maxlength="40"
                            x-on:input="$event.target.value = $event.target.value.toLowerCase().replace(/@.*$/, '').replace(/[^a-z0-9._-]/g, '')"
                            class="w-full py-2.5 bg-transparent text-text-main text-base font-mono lowercase outline-none placeholder:text-text-muted/40"
                            placeholder="rider001"
                        >
                        <span class="px-3 py-1.5 rounded-lg bg-surface-subtle border border-border-subtle text-xs font-mono font-bold text-primary-dark select-none shrink-0">
                            @storkia.shop
                        </span>
                    </div>
                    <p class="text-[11px] text-text-muted mt-1">
                        Generated Account Email: <span class="font-mono font-semibold text-text-main">{{ ($email_prefix ?: 'username') . '@storkia.shop' }}</span>
                    </p>
                    @error('email_prefix') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Company Hub (Municipality)*</label>
                    <div class="relative border-b {{ $errors->has('logistics_hub_id') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <select wire:model.live="logistics_hub_id" class="w-full py-2.5 bg-transparent text-text-main text-sm font-semibold outline-none cursor-pointer">
                            @foreach ($eligibleHubs as $h)
                                <option value="{{ $h['id'] }}">{{ $h['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('logistics_hub_id') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted">
                            Assigned Barangay Coverage ({{ $selectedHubMunicipality ?: 'Hub Municipality' }})*
                        </label>
                        <span class="text-[11px] font-bold text-primary">{{ count($selectedBarangays) }} selected</span>
                    </div>
                    <div class="max-h-48 overflow-y-auto rounded-2xl border {{ $errors->has('selectedBarangays') ? 'border-danger' : 'border-border-subtle' }} p-3 bg-surface-subtle/50 grid grid-cols-1 sm:grid-cols-2 gap-2 custom-scrollbar">
                        @foreach ($availableBarangays as $brgy)
                            <label class="flex items-center gap-2 p-2.5 rounded-xl bg-surface border border-border-subtle hover:border-primary transition-colors cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedBarangays" 
                                    value="{{ $brgy['name'] }}" 
                                    class="rounded text-primary focus:ring-primary"
                                >
                                <span class="text-xs font-medium text-text-main truncate">{{ $brgy['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-text-muted mt-1">Select one or more Barangays within {{ $selectedHubMunicipality }} covered by this Rider.</p>
                    @error('selectedBarangays') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">First Name*</label>
                    <div class="relative border-b {{ $errors->has('first_name') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.400ms="first_name"
                            maxlength="50"
                            x-on:input="$event.target.value = $event.target.value.replace(/^\s+/, '').replace(/ {2,}/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"
                            class="capitalize w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                            placeholder="Pedro Jose"
                        >
                    </div>
                    @error('first_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Last Name*</label>
                    <div class="relative border-b {{ $errors->has('last_name') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.400ms="last_name"
                            maxlength="50"
                            x-on:input="$event.target.value = $event.target.value.replace(/^\s+/, '').replace(/ {2,}/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"
                            class="capitalize w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                            placeholder="Garcia"
                        >
                    </div>
                    @error('last_name') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Date of Birth*</label>
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

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Mobile Number*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('contact_no') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <span class="text-sm font-semibold text-text-muted pr-2">+63</span>
                        <input 
                            type="text" 
                            wire:model.live.debounce.400ms="contact_no" 
                            inputmode="numeric"
                            maxlength="10" 
                            placeholder="9XXXXXXXXX" 
                            class="w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                        >
                    </div>
                    @error('contact_no') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="pt-4">
                    <button type="button" wire:click="nextStep(1)" class="w-full py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]">
                        Continue
                    </button>
                </div>
            </div>
        @endif

        @if ($currentStep === 2)
            <div class="space-y-5">
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Driver's License No.*</label>
                    <div class="relative border-b {{ $errors->has('license_no') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.400ms="license_no"
                            maxlength="13"
                            x-on:input="$event.target.value = $event.target.value.toUpperCase()"
                            class="uppercase font-mono w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                            placeholder="N01-23-456789"
                        >
                    </div>
                    <p class="text-[11px] text-text-muted mt-1">Format: A00-00-000000</p>
                    @error('license_no') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">License Expiry Date*</label>
                    <div class="relative border-b {{ $errors->has('license_expiry_date') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="date" 
                            wire:model.live="license_expiry_date"
                            min="{{ now()->format('Y-m-d') }}"
                            class="w-full py-2.5 bg-transparent text-text-main text-base outline-none cursor-pointer"
                        >
                    </div>
                    @error('license_expiry_date') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Vehicle Type*</label>
                    <x-ui.searchable-select 
                        wire:model.live="vehicle_type" 
                        :options="$vehicleTypes" 
                        placeholder="Select Vehicle Type" 
                        :hasError="$errors->has('vehicle_type')" 
                    />
                    @error('vehicle_type') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Plate Number*</label>
                    <div class="relative border-b {{ $errors->has('plate_number') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.400ms="plate_number"
                            maxlength="15"
                            x-on:input="$event.target.value = $event.target.value.toUpperCase()"
                            class="uppercase font-mono w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                            placeholder="123ABC"
                        >
                    </div>
                    @error('plate_number') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(1)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="nextStep(2)" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]">Continue</button>
                </div>
            </div>
        @endif

        @if ($currentStep === 3)
            <div class="space-y-6">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted">
                            Driver's License (Upload)*
                        </label>
                        <span class="text-[11px] text-text-muted">JPG, PNG, PDF (Max 5MB)</span>
                    </div>

                    <div 
                        x-data="{ isDragging: false }"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="
                            isDragging = false;
                            if ($event.dataTransfer.files.length > 0) {
                                $refs.licenseInput.files = $event.dataTransfer.files;
                                $refs.licenseInput.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        "
                        :class="isDragging ? 'border-primary bg-brand-light/20' : 'border-border-subtle hover:border-text-main/50'"
                        class="relative rounded-2xl border-2 border-dashed p-4 transition-all duration-200 overflow-hidden bg-surface group"
                    >
                        <div 
                            wire:loading.flex 
                            wire:target="drivers_license" 
                            class="absolute inset-0 z-20 bg-surface/90 backdrop-blur-xs flex-col items-center justify-center space-y-1.5 p-4"
                        >
                            <svg class="animate-spin h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <p class="text-xs font-semibold text-primary-dark">Uploading Driver's License...</p>
                        </div>

                        @if ($drivers_license)
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    @php
                                        $licenseExt = strtolower(pathinfo($drivers_license->getClientOriginalName(), PATHINFO_EXTENSION));
                                    @endphp

                                    @if(in_array($licenseExt, ['jpg', 'jpeg', 'png', 'webp']))
                                        <img src="{{ $drivers_license->temporaryUrl() }}" alt="Driver's License" class="w-12 h-12 object-cover rounded-xl border border-border-subtle shrink-0 shadow-xs">
                                    @else
                                        <div class="w-12 h-12 rounded-xl bg-danger/10 text-danger flex items-center justify-center shrink-0 border border-danger/20">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif

                                    <div class="truncate">
                                        <p class="text-xs font-bold text-text-main truncate" title="{{ $drivers_license->getClientOriginalName() }}">
                                            {{ $drivers_license->getClientOriginalName() }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[10px] text-text-muted">
                                                {{ round($drivers_license->getSize() / 1024, 1) }} KB
                                            </span>
                                            <span class="text-[10px] text-success font-semibold flex items-center gap-0.5">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                Ready
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <label for="drivers_license_file" class="text-xs font-bold text-primary hover:text-primary-dark underline cursor-pointer shrink-0 px-2 py-1">
                                    Change
                                </label>
                            </div>
                        @else
                            <label for="drivers_license_file" class="flex flex-col items-center justify-center py-4 cursor-pointer">
                                <div class="w-9 h-9 rounded-full bg-surface-subtle flex items-center justify-center mb-2 text-text-muted group-hover:text-primary group-hover:bg-brand-light/30 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <p class="text-xs text-text-main font-medium">
                                    <span class="text-primary font-semibold underline">Browse file</span> or drag & drop here
                                </p>
                            </label>
                        @endif

                        <input 
                            id="drivers_license_file" 
                            x-ref="licenseInput"
                            type="file" 
                            wire:model="drivers_license" 
                            class="hidden" 
                            accept=".jpg,.jpeg,.png,.pdf" 
                        />
                    </div>
                    @error('drivers_license') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted">
                            Vehicle OR/CR (Upload)*
                        </label>
                        <span class="text-[11px] text-text-muted">JPG, PNG, PDF (Max 5MB)</span>
                    </div>

                    <div 
                        x-data="{ isDragging: false }"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="
                            isDragging = false;
                            if ($event.dataTransfer.files.length > 0) {
                                $refs.orCrInput.files = $event.dataTransfer.files;
                                $refs.orCrInput.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        "
                        :class="isDragging ? 'border-primary bg-brand-light/20' : 'border-border-subtle hover:border-text-main/50'"
                        class="relative rounded-2xl border-2 border-dashed p-4 transition-all duration-200 overflow-hidden bg-surface group"
                    >
                        <div 
                            wire:loading.flex 
                            wire:target="vehicle_or_cr" 
                            class="absolute inset-0 z-20 bg-surface/90 backdrop-blur-xs flex-col items-center justify-center space-y-1.5 p-4"
                        >
                            <svg class="animate-spin h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <p class="text-xs font-semibold text-primary-dark">Uploading Vehicle OR/CR...</p>
                        </div>

                        @if ($vehicle_or_cr)
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    @php
                                        $orCrExt = strtolower(pathinfo($vehicle_or_cr->getClientOriginalName(), PATHINFO_EXTENSION));
                                    @endphp

                                    @if(in_array($orCrExt, ['jpg', 'jpeg', 'png', 'webp']))
                                        <img src="{{ $vehicle_or_cr->temporaryUrl() }}" alt="Vehicle OR/CR" class="w-12 h-12 object-cover rounded-xl border border-border-subtle shrink-0 shadow-xs">
                                    @else
                                        <div class="w-12 h-12 rounded-xl bg-danger/10 text-danger flex items-center justify-center shrink-0 border border-danger/20">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif

                                    <div class="truncate">
                                        <p class="text-xs font-bold text-text-main truncate" title="{{ $vehicle_or_cr->getClientOriginalName() }}">
                                            {{ $vehicle_or_cr->getClientOriginalName() }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[10px] text-text-muted">
                                                {{ round($vehicle_or_cr->getSize() / 1024, 1) }} KB
                                            </span>
                                            <span class="text-[10px] text-success font-semibold flex items-center gap-0.5">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                Ready
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <label for="vehicle_or_cr_file" class="text-xs font-bold text-primary hover:text-primary-dark underline cursor-pointer shrink-0 px-2 py-1">
                                    Change
                                </label>
                            </div>
                        @else
                            <label for="vehicle_or_cr_file" class="flex flex-col items-center justify-center py-4 cursor-pointer">
                                <div class="w-9 h-9 rounded-full bg-surface-subtle flex items-center justify-center mb-2 text-text-muted group-hover:text-primary group-hover:bg-brand-light/30 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <p class="text-xs text-text-main font-medium">
                                    <span class="text-primary font-semibold underline">Browse file</span> or drag & drop here
                                </p>
                            </label>
                        @endif

                        <input 
                            id="vehicle_or_cr_file" 
                            x-ref="orCrInput"
                            type="file" 
                            wire:model="vehicle_or_cr" 
                            class="hidden" 
                            accept=".jpg,.jpeg,.png,.pdf" 
                        />
                    </div>
                    @error('vehicle_or_cr') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(2)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">
                        Back
                    </button>
                    <button 
                        type="button" 
                        wire:click="nextStep(3)" 
                        wire:loading.attr="disabled" 
                        class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="drivers_license, vehicle_or_cr">Continue</span>
                        <span wire:loading wire:target="drivers_license, vehicle_or_cr">Processing Files...</span>
                    </button>
                </div>
            </div>
        @endif

        @if ($currentStep === 4)
            <div class="space-y-5" x-data="{ showPass: false, showConfirm: false }">
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Password*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('password') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            :type="showPass ? 'text' : 'password'" 
                            wire:model.live.debounce.400ms="password" 
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
                            wire:model.live.debounce.400ms="password_confirmation" 
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

                <div class="pt-2 grid grid-cols-2 gap-1.5 text-[11px] text-text-muted">
                    <span class="flex items-center gap-1 {{ strlen($password) >= 8 ? 'text-success font-semibold' : '' }}">
                        <span>{{ strlen($password) >= 8 ? '✓' : '•' }}</span> 8+ characters
                    </span>
                    <span class="flex items-center gap-1 {{ preg_match('/[A-Z]/', $password) ? 'text-success font-semibold' : '' }}">
                        <span>{{ preg_match('/[A-Z]/', $password) ? '✓' : '•' }}</span> 1 uppercase
                    </span>
                    <span class="flex items-center gap-1 {{ preg_match('/[a-z]/', $password) ? 'text-success font-semibold' : '' }}">
                        <span>{{ preg_match('/[a-z]/', $password) ? '✓' : '•' }}</span> 1 lowercase
                    </span>
                    <span class="flex items-center gap-1 {{ preg_match('/[0-9]/', $password) ? 'text-success font-semibold' : '' }}">
                        <span>{{ preg_match('/[0-9]/', $password) ? '✓' : '•' }}</span> 1 number
                    </span>
                    <span class="flex items-center gap-1 {{ preg_match('/[\W_]/', $password) ? 'text-success font-semibold' : '' }}">
                        <span>{{ preg_match('/[\W_]/', $password) ? '✓' : '•' }}</span> 1 special symbol
                    </span>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(3)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="nextStep(4)" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99]">Continue</button>
                </div>
            </div>
        @endif

        @if ($currentStep === 5)
            <div class="space-y-6 text-xs">
                <div class="space-y-5">
                    <div class="border-b border-border-subtle/70 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="uppercase tracking-wider font-bold text-primary-dark">Hub, Barangay Coverage & Personal Info</span>
                            <button type="button" wire:click="backToStep(1)" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Edit</button>
                        </div>
                        <div class="space-y-1 text-text-muted">
                            <p><span class="text-text-main font-semibold">Assigned Hub:</span> {{ $selectedLogisticsName }} ({{ $selectedHubMunicipality }})</p>
                            <p><span class="text-text-main font-semibold">Barangay Coverage:</span> {{ implode(', ', $selectedBarangays) }}</p>
                            <p><span class="text-text-main font-semibold">Full Name:</span> {{ $first_name }} {{ $last_name }}</p>
                            <p><span class="text-text-main font-semibold">Date of Birth:</span> {{ $birthday ? \Carbon\Carbon::parse($birthday)->format('M d, Y') : '' }}</p>
                            <p><span class="text-text-main font-semibold">Internal Email:</span> <span class="font-mono font-bold text-primary-dark">{{ $email }}</span></p>
                            <p><span class="text-text-main font-semibold">Mobile Number:</span> +63{{ $contact_no }}</p>
                        </div>
                    </div>

                    <div class="border-b border-border-subtle/70 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="uppercase tracking-wider font-bold text-primary-dark">License & Vehicle Details</span>
                            <button type="button" wire:click="backToStep(2)" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Edit</button>
                        </div>
                        <div class="space-y-1 text-text-muted">
                            <p><span class="text-text-main font-semibold">License No.:</span> {{ $license_no }}</p>
                            <p><span class="text-text-main font-semibold">License Expiry:</span> {{ $license_expiry_date ? \Carbon\Carbon::parse($license_expiry_date)->format('M d, Y') : '' }}</p>
                            <p><span class="text-text-main font-semibold">Vehicle Type:</span> {{ $vehicle_type }}</p>
                            <p><span class="text-text-main font-semibold">Plate Number:</span> {{ $plate_number }}</p>
                        </div>
                    </div>

                    <div class="border-b border-border-subtle/70 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="uppercase tracking-wider font-bold text-primary-dark">Verification Documents</span>
                            <button type="button" wire:click="backToStep(3)" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Edit</button>
                        </div>
                        <div class="space-y-1 text-text-muted">
                            <p><span class="text-text-main font-semibold">Driver's License:</span> {{ $drivers_license ? $drivers_license->getClientOriginalName() : 'Attached' }}</p>
                            <p><span class="text-text-main font-semibold">Vehicle OR/CR:</span> {{ $vehicle_or_cr ? $vehicle_or_cr->getClientOriginalName() : 'Attached' }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" wire:click="backToStep(4)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button 
                        type="button" 
                        wire:click="register" 
                        wire:loading.attr="disabled" 
                        class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer active:scale-[0.99] disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="register">Create Rider Account</span>
                        <span wire:loading wire:target="register">Creating Rider Account...</span>
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>