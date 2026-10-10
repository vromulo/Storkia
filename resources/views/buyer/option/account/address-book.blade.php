@extends('buyer.personal-center', [
    'pageTitle' => 'My Address Book',
    'pageSubtitle' => 'Manage your saved addresses and delivery preferences.'
])

@section('option-content')
<div x-data="addressManager({{ Js::from($addresses) }}, {{ Js::from($provinces) }})" class="space-y-8 pt-0 select-none">

    <!-- Centered Header Title -->
    <div class="text-center pt-2">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-text-main uppercase tracking-tight">
            MY ADDRESS BOOK
        </h2>
    </div>

    <!-- Action Section: + ADD NEW ADDRESS Button -->
    <div class="pt-2 flex items-center justify-end">
        <button type="button"
                @click="openAddModal()"
                class="px-5 py-2.5 bg-text-main hover:bg-black text-surface text-xs sm:text-sm font-bold uppercase tracking-wider rounded-xl transition-all duration-150 cursor-pointer active:scale-95 inline-flex items-center gap-1.5">
            <span>+</span>
            <span>ADD NEW ADDRESS</span>
        </button>
    </div>

    <hr class="border-border-subtle" />

    <!-- Address Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2" x-show="addresses.length > 0">
        <template x-for="addr in addresses" :key="addr.id">
            <div class="relative bg-surface border border-border-subtle rounded-none p-5 sm:p-6 overflow-hidden select-none">
                <!-- Airmail Border -->
                <div class="absolute left-0 top-0 bottom-0 w-2.5 sm:w-3 pointer-events-none"
                     style="background: repeating-linear-gradient(180deg, #4A779D 0px, #4A779D 14px, transparent 14px, transparent 22px, #C45050 22px, #C45050 36px, transparent 36px, transparent 44px);">
                </div>

                <div class="pl-4 sm:pl-5 space-y-3.5">
                    <div class="flex items-baseline gap-3 text-sm sm:text-base font-normal">
                        <span class="font-bold text-text-main text-base sm:text-lg" x-text="`${addr.first_name} ${addr.last_name}`"></span>
                        <span class="text-text-muted text-sm sm:text-base" x-text="`+63 ${addr.phone_number}`"></span>
                    </div>

                    <div class="space-y-1 text-xs sm:text-sm text-text-main leading-relaxed">
                        <p class="font-normal" x-text="[addr.building_details, addr.street_address].filter(Boolean).join(', ')"></p>
                        <p class="font-normal uppercase" x-text="[addr.municipality, addr.province, addr.country, addr.postcode].filter(Boolean).join(' ')"></p>
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <div>
                            <span x-show="addr.is_default" class="inline-block px-2.5 py-1 text-xs font-medium text-emerald-700 border border-emerald-600 rounded-none bg-emerald-50/40">
                                Default Address
                            </span>
                        </div>
                        <div class="flex items-center gap-3 text-xs sm:text-sm font-medium">
                            <button type="button" @click="promptDelete(addr.id)" class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer">
                                Delete
                            </button>
                            <button type="button" @click="openEditModal(addr)" class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer">
                                Edit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Empty State -->
    <div x-show="addresses.length === 0" class="pt-6 pb-12 text-center">
        <div class="w-12 h-12 rounded-full bg-surface-subtle text-text-muted/60 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>
        <p class="text-xs text-text-muted font-medium">No saved addresses yet. Click "+ ADD NEW ADDRESS" to add your primary delivery destination.</p>
    </div>

    <!-- Shipping Address Form Modal -->
    <!-- Outer backdrop overlay (Clicks outside will not close, no blur) -->
    <div x-show="isModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
        
        <!-- Outer Card: Maintains rounded-2xl, overflow-hidden protects corners, click.away removed -->
        <div class="bg-surface rounded-2xl border border-border-subtle max-w-2xl w-full shadow-2xl relative overflow-hidden flex flex-col max-h-[75vh]">
            
            <!-- Inner Scrollable Viewport -->
            <div class="overflow-y-auto p-6 sm:p-8 flex-1 [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-button]:hidden [&::-webkit-scrollbar-button]:h-0 [&::-webkit-scrollbar-button]:w-0 [&::-webkit-scrollbar-button]:[display:none]">
                <!-- Header Row: Title & Close Button shared on the same line via flex-grow -->
                <div class="flex items-start justify-between mb-6">
                    <h3 class="text-2xl font-bold text-text-main font-sans flex-1">
                        Shipping Address
                    </h3>
                    <button type="button" 
                            @click="closeModal()" 
                            class="text-text-muted hover:text-text-main p-1 transition-colors cursor-pointer rounded-lg -mt-1.5 sm:-mt-2 shrink-0"
                            aria-label="Close modal">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitForm()" class="space-y-4">
                    <!-- Location Field (Readonly Philippines) -->
                    <div>
                        <label class="block text-xs text-text-muted font-medium mb-1">Location*</label>
                        <div class="flex items-center justify-between w-full p-3.5 bg-surface-subtle border border-border-subtle rounded-xl text-sm font-medium text-text-main">
                            <span>Philippines</span>
                            <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>

                    <!-- First & Last Name -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <input type="text" x-model="form.first_name" placeholder="First Name*" class="w-full p-3.5 bg-surface border border-border-subtle rounded-xl text-sm outline-none focus:border-text-main">
                            <template x-if="errors.first_name"><p class="text-danger text-xs mt-1" x-text="errors.first_name[0]"></p></template>
                        </div>
                        <div>
                            <input type="text" x-model="form.last_name" placeholder="Last Name*" class="w-full p-3.5 bg-surface border border-border-subtle rounded-xl text-sm outline-none focus:border-text-main">
                            <template x-if="errors.last_name"><p class="text-danger text-xs mt-1" x-text="errors.last_name[0]"></p></template>
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <div class="flex border border-border-subtle rounded-xl overflow-hidden focus-within:border-text-main">
                            <div class="px-4 py-3.5 bg-surface-subtle border-r border-border-subtle text-sm text-text-main font-medium shrink-0">
                                PH +63
                            </div>
                            <input type="text" x-model="form.phone_number" maxlength="10" placeholder="Phone Number*" class="w-full p-3.5 bg-surface text-sm outline-none">
                        </div>
                        <template x-if="errors.phone_number"><p class="text-danger text-xs mt-1" x-text="errors.phone_number[0]"></p></template>
                    </div>

                    <!-- Province & City/Municipality -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <select x-model="form.province_code" @change="onProvinceChange()" class="w-full p-3.5 bg-surface border border-border-subtle rounded-xl text-sm text-text-main outline-none focus:border-text-main">
                                <option value="">State/Province*</option>
                                <template x-for="p in provinces" :key="p.code">
                                    <option :value="p.code" x-text="p.name" :selected="String(p.code) === String(form.province_code)"></option>
                                </template>
                            </select>
                            <template x-if="errors.province_code"><p class="text-danger text-xs mt-1" x-text="errors.province_code[0]"></p></template>
                        </div>
                        <div>
                            <select x-model="form.municipality_code" @change="onMunicipalityChange()" :disabled="!form.province_code || isLoadingMunicipalities" class="w-full p-3.5 bg-surface border border-border-subtle rounded-xl text-sm text-text-main outline-none focus:border-text-main disabled:bg-surface-subtle disabled:cursor-not-allowed">
                                <option value="" x-text="isLoadingMunicipalities ? 'Loading cities...' : 'City/Municipality*'"></option>
                                <template x-for="m in municipalities" :key="m.code">
                                    <option :value="m.code" x-text="m.name" :selected="String(m.code) === String(form.municipality_code)"></option>
                                </template>
                            </select>
                            <template x-if="errors.municipality_code"><p class="text-danger text-xs mt-1" x-text="errors.municipality_code[0]"></p></template>
                        </div>
                    </div>

                    <!-- Postcode -->
                    <div>
                        <input type="text" x-model="form.postcode" placeholder="Postcode*" class="w-full p-3.5 bg-surface border border-border-subtle rounded-xl text-sm outline-none focus:border-text-main">
                        <template x-if="errors.postcode"><p class="text-danger text-xs mt-1" x-text="errors.postcode[0]"></p></template>
                    </div>

                    <!-- Field 1: Street Address -->
                    <div class="space-y-1">
                        <div class="border border-border-subtle focus-within:border-text-main rounded-xl p-3 bg-surface transition-colors">
                            <label for="street_address" class="block text-xs font-medium text-text-muted text-left">
                                Street Address, Building No, Apt, etc.*
                            </label>
                            <textarea id="street_address"
                                    x-model="form.street_address" 
                                    rows="1" 
                                    placeholder="ABC Street/Road+Apartment/Building name" 
                                    class="w-full bg-transparent outline-none text-text-main text-sm font-normal pt-1 pb-0 min-h-[28px] resize-y placeholder:text-text-muted/40 block leading-snug"></textarea>
                        </div>
                        <template x-if="errors.street_address">
                            <p class="text-danger text-xs mt-1" x-text="errors.street_address[0]"></p>
                        </template>
                    </div>

                    <!-- Field 2: Building Details / Landmark -->
                    <div class="space-y-1">
                        <div class="border border-border-subtle focus-within:border-text-main rounded-xl p-3 bg-surface transition-colors">
                            <label for="building_details" class="block text-xs font-medium text-text-muted text-left">
                                Building No, Apt, etc.
                            </label>
                            <textarea id="building_details"
                                    x-model="form.building_details" 
                                    rows="1" 
                                    placeholder="Phase/Suite/Unit/Floor, Landmark" 
                                    class="w-full bg-transparent outline-none text-text-main text-sm font-normal pt-1 pb-0 min-h-[28px] resize-y placeholder:text-text-muted/40 block leading-snug"></textarea>
                        </div>
                        <template x-if="errors.building_details">
                            <p class="text-danger text-xs mt-1" x-text="errors.building_details[0]"></p>
                        </template>
                    </div>

                    <!-- Footer Checkbox & Links -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
                        <label class="flex items-center gap-2 text-sm text-text-main cursor-pointer">
                            <input type="checkbox" x-model="form.is_default" class="w-4 h-4 rounded text-primary focus:ring-primary border-border-subtle">
                            <span>Make Default</span>
                        </label>
                        <div class="flex items-center gap-4 text-xs text-text-muted underline">
                            <a href="#" class="hover:text-text-main">General Address Tips</a>
                            <a href="#" class="hover:text-text-main">Privacy & Cookie Policy</a>
                        </div>
                    </div>

                    <!-- Security Note -->
                    <div class="pt-2 text-xs text-text-muted space-y-1">
                        <div class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>Security & Privacy</span>
                        </div>
                        <p>We maintain industry-standard physical, technical, and administrative measures to safeguard your personal information.</p>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 flex justify-end">
                        <button type="submit" :disabled="isSubmitting" class="w-full sm:w-auto px-8 py-3.5 bg-text-main hover:bg-black text-surface text-sm font-bold uppercase rounded-xl transition-all cursor-pointer disabled:opacity-50">
                            <span x-show="!isSubmitting">Save Address</span>
                            <span x-show="isSubmitting">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="isDeleteModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div @click.away="closeDeleteModal()" class="bg-surface rounded-2xl border border-border-subtle max-w-md w-full p-6 shadow-2xl text-center space-y-4">
            <h4 class="text-lg font-bold text-text-main">Delete Address</h4>
            <p class="text-sm text-text-muted">Are you sure you want to delete this address?</p>
            <div class="flex items-center justify-center gap-3 pt-2">
                <button type="button" @click="closeDeleteModal()" class="px-5 py-2.5 bg-surface-subtle text-text-main text-xs font-bold rounded-xl hover:bg-border-subtle transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="button" @click="confirmDelete()" :disabled="isDeleting" class="px-5 py-2.5 bg-danger text-white text-xs font-bold rounded-xl hover:bg-red-700 transition-colors cursor-pointer disabled:opacity-50">
                    <span x-show="!isDeleting">Delete</span>
                    <span x-show="isDeleting">Deleting...</span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection