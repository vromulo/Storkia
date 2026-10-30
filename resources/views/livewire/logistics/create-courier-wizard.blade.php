<div class="w-full">
    @if ($creationSuccessful)
        <div class="text-center py-10 animate-fade-in-up">
            <div class="w-16 h-16 bg-success/10 text-success rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="text-3xl font-serif text-primary-dark mb-2">Courier Account Created</h2>
            <p class="text-text-muted text-sm max-w-md mx-auto mb-6 leading-relaxed font-normal">
                Courier <span class="font-semibold text-text-main">{{ $first_name }} {{ $last_name }}</span> has been registered and assigned to <span class="font-semibold text-text-main">{{ $home_hub }}</span>.
            </p>
            <div class="max-w-md mx-auto mb-8 p-4 rounded-2xl bg-surface-subtle border border-border-subtle text-xs space-y-1.5 text-left">
                <div class="flex justify-between">
                    <span class="text-text-muted">Internal Login Email:</span>
                    <span class="font-mono font-bold text-primary-dark">{{ $email }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Assigned Hub:</span>
                    <span class="font-semibold text-text-main">{{ $home_hub }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-muted">Account Status:</span>
                    <span class="font-bold text-success uppercase">Active</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('logistics.couriers.index') }}" class="inline-flex py-3 px-8 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs transition-all cursor-pointer">
                    Back to All Couriers
                </a>
                <a href="{{ route('logistics.employees.index') }}" class="inline-flex py-3 px-6 bg-surface border border-border-subtle hover:border-primary text-text-main text-sm font-semibold rounded-full transition-all cursor-pointer">
                    Employee Management
                </a>
            </div>
        </div>
    @else
        <div class="mb-8 text-center">
            <h1 class="text-2xl sm:text-3xl font-serif font-normal tracking-tight text-primary-dark">
                Add First-Mile Courier
            </h1>
            <p class="mt-2 text-sm text-text-muted font-normal leading-relaxed">
                @if ($currentStep === 1) Personal details &amp; internal @storkia.shop account email
                @elseif ($currentStep === 2) Municipality hub assignment, professional license &amp; vehicle capacity
                @elseif ($currentStep === 3) Upload driver's license, vehicle OR/CR &amp; NBI clearance
                @elseif ($currentStep === 4) Set up the courier's login password
                @elseif ($currentStep === 5) Review and confirm courier account creation
                @endif
            </p>
        </div>

        <div class="flex items-center justify-center gap-1.5 mb-10 overflow-x-auto pb-2 [&::-webkit-scrollbar]:hidden">
            @foreach (['Personal', 'Vehicle', 'Docs', 'Password', 'Review'] as $index => $label)
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
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">First Name*</label>
                    <div class="relative border-b {{ $errors->has('first_name') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.400ms="first_name"
                            maxlength="50"
                            x-on:input="$event.target.value = $event.target.value.replace(/^\s+/, '').replace(/ {2,}/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"
                            class="capitalize w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                            placeholder="Juan"
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
                            placeholder="Dela Cruz"
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
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Email Username*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('email_prefix') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="email_prefix"
                            maxlength="40"
                            x-on:input="$event.target.value = $event.target.value.toLowerCase().replace(/@.*$/, '').replace(/[^a-z0-9._-]/g, '')"
                            class="w-full py-2.5 bg-transparent text-text-main text-base font-mono lowercase outline-none placeholder:text-text-muted/40"
                            placeholder="courier001"
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
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Mobile Number*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('contact_no') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input 
                            type="text" 
                            wire:model.live.debounce.400ms="contact_no" 
                            inputmode="numeric"
                            maxlength="11" 
                            placeholder="09276543210" 
                            class="w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                        >
                    </div>
                    <p class="text-[11px] text-text-muted mt-1">11-digit mobile number starting with 09</p>
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
                <!-- Assigned Municipality Hub (Read-Only if Branch Account; Downward Custom Dropdown if Main Account) -->
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Assigned Municipality Hub*</label>

                    @if($isBranchAccount)
                        <div class="w-full p-3 rounded-xl border border-border-subtle bg-surface-subtle text-xs font-bold text-primary-dark flex items-center justify-between select-none cursor-not-allowed">
                            <span class="truncate">{{ $lockedHubLabel ?: $home_hub }}</span>
                            <span class="px-2 py-0.5 rounded bg-brand-light/60 text-[10px] font-extrabold uppercase shrink-0">Branch Locked</span>
                        </div>
                        <p class="text-[11px] text-text-muted mt-1">Branch Hub Mode: Couriers are automatically locked to your Branch Hub municipality.</p>
                    @else
                        @php
                            $currentHubObj = collect($eligibleHubs)->firstWhere('id', (string) $logistics_hub_id) ?? ($eligibleHubs[0] ?? null);
                            $currentHubLabel = $currentHubObj['label'] ?? 'Select Municipality Hub';
                        @endphp
                        <div x-data="{ hubOpen: false }" @click.away="hubOpen = false" class="relative">
                            <button type="button"
                                    @click="hubOpen = !hubOpen"
                                    class="w-full py-2.5 px-3 rounded-xl border {{ $errors->has('logistics_hub_id') ? 'border-danger' : 'border-border-subtle' }} bg-surface text-left text-xs font-semibold text-text-main flex items-center justify-between gap-2 cursor-pointer hover:border-primary">
                                <span class="truncate">{{ $currentHubLabel }}</span>
                                <svg class="w-4 h-4 text-text-muted transition-transform shrink-0" :class="hubOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="hubOpen"
                                 x-cloak
                                 x-transition
                                 class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-surface border border-border-subtle rounded-2xl shadow-xl overflow-hidden max-h-52 overflow-y-auto divide-y divide-border-subtle/40 custom-scrollbar">
                                @foreach ($eligibleHubs as $h)
                                    <button type="button"
                                            wire:click="$set('logistics_hub_id', '{{ $h['id'] }}')"
                                            @click="hubOpen = false"
                                            class="w-full px-3.5 py-2.5 text-left text-xs hover:bg-primary/10 transition-colors flex items-center justify-between cursor-pointer {{ (string) $logistics_hub_id === (string) $h['id'] ? 'bg-primary/10 text-primary font-bold' : 'text-text-main font-medium' }}">
                                        <span>{{ $h['label'] }}</span>
                                        @if((string) $logistics_hub_id === (string) $h['id'])
                                            <span class="text-primary font-bold">✓</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <p class="text-[11px] text-text-muted mt-1">Select which of your company hubs (Main Hub or Branch Hub) this courier will serve.</p>
                    @endif
                    @error('logistics_hub_id') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div x-data="{ ltOpen: false }" @click.away="ltOpen = false" class="relative">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">License Type*</label>
                        <button type="button"
                                @click="ltOpen = !ltOpen"
                                class="w-full py-2.5 px-3 rounded-xl border {{ $errors->has('license_type') ? 'border-danger' : 'border-border-subtle' }} bg-surface text-left text-xs font-semibold text-text-main flex items-center justify-between gap-2 cursor-pointer">
                            <span>{{ $license_type }}</span>
                            <svg class="w-4 h-4 text-text-muted transition-transform shrink-0" :class="ltOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="ltOpen"
                             x-cloak
                             x-transition
                             class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-surface border border-border-subtle rounded-2xl shadow-xl overflow-hidden divide-y divide-border-subtle/40">
                            @foreach ($licenseTypes as $lt)
                                <button type="button"
                                        wire:click="$set('license_type', '{{ $lt }}')"
                                        @click="ltOpen = false"
                                        class="w-full px-3.5 py-2.5 text-left text-xs hover:bg-primary/10 transition-colors flex items-center justify-between cursor-pointer {{ $license_type === $lt ? 'bg-primary/10 text-primary font-bold' : 'text-text-main font-medium' }}">
                                    <span>{{ $lt }}</span>
                                </button>
                            @endforeach
                        </div>
                        @error('license_type') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
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
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-2">Restriction / DL Codes*</label>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                        @foreach ($availableDlCodes as $code)
                            <label class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border transition-all cursor-pointer text-xs font-bold
                                {{ in_array($code, $dl_codes, true) ? 'border-primary bg-primary/10 text-primary-dark shadow-2xs' : 'border-border-subtle bg-surface text-text-muted hover:border-text-main/40' }}">
                                <input type="checkbox" wire:model.live="dl_codes" value="{{ $code }}" class="rounded text-primary focus:ring-primary">
                                <span class="font-mono">{{ $code }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('dl_codes') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Vehicle Type*</label>
                    <x-ui.searchable-select 
                        wire:model.live="vehicle_type" 
                        :options="$vehicleTypes" 
                        placeholder="Select Linehaul Vehicle Type" 
                        :hasError="$errors->has('vehicle_type')" 
                    />
                    @error('vehicle_type') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Plate Number*</label>
                        <div class="relative border-b {{ $errors->has('plate_number') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                            <input 
                                type="text" 
                                wire:model.live.debounce.400ms="plate_number"
                                maxlength="8"
                                x-on:input="$event.target.value = $event.target.value.toUpperCase()"
                                class="uppercase font-mono w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                                placeholder="ABC1234"
                            >
                        </div>
                        @error('plate_number') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Load Capacity (KG)*</label>
                        <div class="relative flex items-center border-b {{ $errors->has('load_capacity') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                            <input 
                                type="text" 
                                wire:model.live.debounce.400ms="load_capacity"
                                inputmode="numeric"
                                maxlength="5"
                                class="w-full py-2.5 bg-transparent text-text-main text-base outline-none placeholder:text-text-muted/40"
                                placeholder="1000"
                            >
                            <span class="text-xs font-bold text-text-muted pl-2">KG</span>
                        </div>
                        @error('load_capacity') <p class="text-danger text-xs mt-1 font-medium">{{ $message }}</p> @enderror
                    </div>
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
                            Professional Driver's License*
                        </label>
                        <span class="text-[11px] text-text-muted">JPG, PNG, PDF (Max 5MB)</span>
                    </div>

                    <div class="relative rounded-2xl border-2 border-dashed border-border-subtle hover:border-text-main/50 p-4 transition-all duration-200 overflow-hidden bg-surface group">
                        @if ($drivers_license)
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-bold text-text-main truncate">{{ $drivers_license->getClientOriginalName() }}</span>
                                <label for="courier_drivers_license_file" class="text-xs font-bold text-primary underline cursor-pointer shrink-0">Change</label>
                            </div>
                        @else
                            <label for="courier_drivers_license_file" class="flex flex-col items-center justify-center py-4 cursor-pointer">
                                <p class="text-xs text-text-main font-medium"><span class="text-primary font-semibold underline">Browse file</span></p>
                            </label>
                        @endif
                        <input id="courier_drivers_license_file" type="file" wire:model="drivers_license" class="hidden" accept=".jpg,.jpeg,.png,.pdf" />
                    </div>
                    @error('drivers_license') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted">Vehicle OR/CR*</label>
                        <span class="text-[11px] text-text-muted">JPG, PNG, PDF (Max 5MB)</span>
                    </div>
                    <div class="relative rounded-2xl border-2 border-dashed border-border-subtle hover:border-text-main/50 p-4 transition-all duration-200 overflow-hidden bg-surface group">
                        @if ($vehicle_or_cr)
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-bold text-text-main truncate">{{ $vehicle_or_cr->getClientOriginalName() }}</span>
                                <label for="courier_or_cr_file" class="text-xs font-bold text-primary underline cursor-pointer shrink-0">Change</label>
                            </div>
                        @else
                            <label for="courier_or_cr_file" class="flex flex-col items-center justify-center py-4 cursor-pointer">
                                <p class="text-xs text-text-main font-medium"><span class="text-primary font-semibold underline">Browse file</span></p>
                            </label>
                        @endif
                        <input id="courier_or_cr_file" type="file" wire:model="vehicle_or_cr" class="hidden" accept=".jpg,.jpeg,.png,.pdf" />
                    </div>
                    @error('vehicle_or_cr') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs uppercase tracking-wider font-bold text-text-muted">NBI Clearance*</label>
                        <span class="text-[11px] text-text-muted">JPG, PNG, PDF (Max 5MB)</span>
                    </div>
                    <div class="relative rounded-2xl border-2 border-dashed border-border-subtle hover:border-text-main/50 p-4 transition-all duration-200 overflow-hidden bg-surface group">
                        @if ($nbi_clearance)
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-bold text-text-main truncate">{{ $nbi_clearance->getClientOriginalName() }}</span>
                                <label for="courier_nbi_file" class="text-xs font-bold text-primary underline cursor-pointer shrink-0">Change</label>
                            </div>
                        @else
                            <label for="courier_nbi_file" class="flex flex-col items-center justify-center py-4 cursor-pointer">
                                <p class="text-xs text-text-main font-medium"><span class="text-primary font-semibold underline">Browse file</span></p>
                            </label>
                        @endif
                        <input id="courier_nbi_file" type="file" wire:model="nbi_clearance" class="hidden" accept=".jpg,.jpeg,.png,.pdf" />
                    </div>
                    @error('nbi_clearance') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(2)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="nextStep(3)" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs cursor-pointer">Continue</button>
                </div>
            </div>
        @endif

        @if ($currentStep === 4)
            <div class="space-y-5" x-data="{ showPass: false, showConfirm: false }">
                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Password*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('password') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input :type="showPass ? 'text' : 'password'" wire:model.live.debounce.400ms="password" class="w-full py-2.5 pr-8 bg-transparent text-text-main text-base outline-none" placeholder="••••••••">
                        <button type="button" @click="showPass = !showPass" class="absolute right-0 text-text-muted hover:text-text-main text-xs font-bold cursor-pointer">
                            <span x-text="showPass ? 'HIDE' : 'SHOW'"></span>
                        </button>
                    </div>
                    @error('password') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider font-bold text-text-muted mb-1.5">Confirm Password*</label>
                    <div class="relative flex items-center border-b {{ $errors->has('password_confirmation') ? 'border-danger' : 'border-border-subtle focus-within:border-text-main' }} transition-colors">
                        <input :type="showConfirm ? 'text' : 'password'" wire:model.live.debounce.400ms="password_confirmation" class="w-full py-2.5 pr-8 bg-transparent text-text-main text-base outline-none" placeholder="••••••••">
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute right-0 text-text-muted hover:text-text-main text-xs font-bold cursor-pointer">
                            <span x-text="showConfirm ? 'HIDE' : 'SHOW'"></span>
                        </button>
                    </div>
                    @error('password_confirmation') <p class="text-danger text-xs mt-1.5 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="button" wire:click="backToStep(3)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="nextStep(4)" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs cursor-pointer">Continue</button>
                </div>
            </div>
        @endif

        @if ($currentStep === 5)
            <div class="space-y-6 text-xs">
                <div class="space-y-5">
                    <div class="border-b border-border-subtle/70 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="uppercase tracking-wider font-bold text-primary-dark">Personal &amp; Account Info</span>
                            <button type="button" wire:click="backToStep(1)" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Edit</button>
                        </div>
                        <div class="space-y-1 text-text-muted">
                            <p><span class="text-text-main font-semibold">Full Name:</span> {{ $first_name }} {{ $last_name }}</p>
                            <p><span class="text-text-main font-semibold">Internal Email:</span> <span class="font-mono text-primary-dark font-bold">{{ $email }}</span></p>
                            <p><span class="text-text-main font-semibold">Mobile Number:</span> {{ $contact_no }}</p>
                        </div>
                    </div>

                    <div class="border-b border-border-subtle/70 pb-3">
                        <div class="flex justify-between items-center mb-2">
                            <span class="uppercase tracking-wider font-bold text-primary-dark">Hub, License &amp; Vehicle</span>
                            <button type="button" wire:click="backToStep(2)" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Edit</button>
                        </div>
                        <div class="space-y-1 text-text-muted">
                            <p><span class="text-text-main font-semibold">Assigned Hub:</span> {{ $home_hub }}</p>
                            <p><span class="text-text-main font-semibold">License No.:</span> {{ $license_no }} ({{ $license_type }})</p>
                            <p><span class="text-text-main font-semibold">Vehicle Type:</span> {{ $vehicle_type }} ({{ $load_capacity }} kg) — {{ $plate_number }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" wire:click="backToStep(4)" class="py-3 px-5 text-text-muted hover:text-text-main text-sm font-medium transition-colors cursor-pointer">Back</button>
                    <button type="button" wire:click="saveCourier" wire:loading.attr="disabled" class="flex-1 py-3.5 px-6 bg-primary-dark hover:bg-text-main text-surface text-sm font-semibold rounded-full shadow-xs cursor-pointer disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveCourier">Create Courier Account</span>
                        <span wire:loading wire:target="saveCourier">Creating Courier...</span>
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>