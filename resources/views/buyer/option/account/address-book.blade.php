@extends('buyer.account-settings', [
    'pageTitle' => 'Address Book',
    'pageSubtitle' => 'Manage up to 10 verified Philippine delivery addresses for faster checkout.'
])

@section('option-content')
<div x-data="addressBookManager(@js($addresses), @js($provinces))"
     @edit-address.window="openEditModal($event.detail.id)"
     @delete-address.window="openDeleteModal($event.detail.id)"
     class="space-y-6 select-none w-full min-w-0 max-w-full">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <p class="text-xs sm:text-sm text-text-muted">
                Saved Addresses: <strong class="text-text-main" x-text="addresses.length"></strong> / 10
            </p>
        </div>
        <button type="button"
                @click="openAddModal()"
                :disabled="addresses.length >= 10"
                :class="addresses.length >= 10 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-text-main hover:bg-dark-grey text-surface cursor-pointer active:scale-95'"
                class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all inline-flex items-center gap-2 self-start sm:self-auto shadow-xs">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>Add New Address</span>
        </button>
    </div>

    <template x-if="addresses.length === 0">
        <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-3">
            <div class="w-14 h-14 rounded-2xl bg-surface-subtle text-text-muted flex items-center justify-center mx-auto border border-border-subtle">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-text-main">No shipping addresses saved yet</h3>
            <p class="text-xs text-text-muted max-w-md mx-auto">
                Add your Philippine delivery address to calculate accurate shipping rates and speed up checkout.
            </p>
            <button type="button"
                    @click="openAddModal()"
                    class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-xs font-bold transition-colors cursor-pointer">
                + Add Your First Address
            </button>
        </div>
    </template>

    <div x-show="addresses.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <template x-for="addr in addresses" :key="addr.id">
            <div class="relative bg-surface border border-border-subtle p-5 sm:p-6 overflow-hidden flex flex-col justify-between h-full">
                <div class="absolute left-0 top-0 bottom-0 w-2.5 sm:w-3 pointer-events-none"
                     style="background: repeating-linear-gradient(180deg, #4A779D 0px, #4A779D 14px, transparent 14px, transparent 22px, #C45050 22px, #C45050 36px, transparent 36px, transparent 44px);">
                </div>

                <div class="pl-4 sm:pl-5 space-y-3 flex-1 flex flex-col justify-between">
                    <div class="space-y-2.5">
                        <div class="flex flex-wrap items-baseline gap-2.5">
                            <span class="font-bold text-text-main text-base" x-text="`${addr.first_name || ''} ${addr.last_name || ''}`.trim()"></span>
                            <span class="text-text-muted text-xs sm:text-sm font-mono" x-text="'+63 ' + String(addr.phone_number || '').replace(/^0+/, '')"></span>
                        </div>

                        <div class="space-y-1 text-xs sm:text-sm text-text-main leading-relaxed">
                            <p x-text="[addr.building_details, addr.street_address, addr.barangay ? 'Brgy. ' + addr.barangay : ''].filter(Boolean).join(', ')"></p>
                            <p class="uppercase text-text-muted font-medium"
                               x-text="[addr.municipality, addr.province, 'Philippines', addr.postcode].filter(Boolean).join(', ')"></p>
                        </div>
                    </div>

                    <div class="pt-4 mt-3 border-t border-border-subtle/50 flex items-center justify-between gap-2">
                        <div>
                            <template x-if="addr.is_default">
                                <span class="inline-block px-2.5 py-1 text-[11px] font-bold text-emerald-700 border border-emerald-600 bg-emerald-50/50">
                                    Default Address
                                </span>
                            </template>
                        </div>

                        <div class="flex items-center gap-3 text-xs sm:text-sm font-semibold">
                            <template x-if="!addr.is_default">
                                <button type="button"
                                        @click="openDeleteModal(addr.id)"
                                        class="text-danger hover:underline cursor-pointer">
                                    Delete
                                </button>
                            </template>
                            <button type="button"
                                    @click="openEditModal(addr.id)"
                                    class="text-primary hover:underline cursor-pointer">
                                Edit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Add / Edit Address Modal -->
    <template x-teleport="body">
        <div x-show="showModal"
             x-cloak
             @keydown.escape.window="closeModal()"
             class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div @click.away="closeModal()"
                 class="bg-surface rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-border-subtle space-y-5 max-h-[92vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <h3 class="text-lg font-bold text-text-main" x-text="modalMode === 'edit' ? 'Edit Shipping Address' : 'Add New Shipping Address'"></h3>
                        <p class="text-xs text-text-muted mt-0.5">All fields marked with <span class="text-danger">*</span> are required.</p>
                    </div>
                    <button type="button" @click="closeModal()" class="p-1.5 text-text-muted hover:text-text-main cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="generalError">
                    <div class="p-3.5 rounded-2xl bg-red-50 border border-red-200 text-danger text-xs font-bold" x-text="generalError"></div>
                </template>

                <form @submit.prevent="submitForm()" class="space-y-4 text-xs sm:text-sm" novalidate>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-text-muted uppercase mb-1">First Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   x-model="form.first_name"
                                   @blur="validateField('first_name')"
                                   maxlength="60"
                                   placeholder="e.g. Juan"
                                   :class="errors.first_name ? 'border-danger' : 'border-border-subtle focus:border-text-main'"
                                   class="w-full p-3 rounded-xl border bg-surface text-text-main outline-none">
                            <template x-if="errors.first_name">
                                <p class="text-danger text-xs mt-1 font-medium" x-text="errors.first_name"></p>
                            </template>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-muted uppercase mb-1">Last Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   x-model="form.last_name"
                                   @blur="validateField('last_name')"
                                   maxlength="60"
                                   placeholder="e.g. Dela Cruz"
                                   :class="errors.last_name ? 'border-danger' : 'border-border-subtle focus:border-text-main'"
                                   class="w-full p-3 rounded-xl border bg-surface text-text-main outline-none">
                            <template x-if="errors.last_name">
                                <p class="text-danger text-xs mt-1 font-medium" x-text="errors.last_name"></p>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-muted uppercase mb-1">Mobile Number <span class="text-danger">*</span></label>
                        <div class="flex items-center rounded-xl border bg-surface px-3"
                             :class="errors.phone_number ? 'border-danger' : 'border-border-subtle focus-within:border-text-main'">
                            <span class="text-text-muted font-bold pr-2 border-r border-border-subtle select-none">+63</span>
                            <input type="tel"
                                   :value="formattedPhone"
                                   @input="formatPhoneInput($event)"
                                   @blur="validateField('phone_number')"
                                   maxlength="12"
                                   placeholder="917 123 4567"
                                   class="w-full p-3 bg-transparent outline-none font-mono text-text-main">
                        </div>
                        <template x-if="errors.phone_number">
                            <p class="text-danger text-xs mt-1 font-medium" x-text="errors.phone_number"></p>
                        </template>
                    </div>

                    <!-- Province / Municipality / Barangay Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Province -->
                        <div class="relative" @click.away="provinceDropdownOpen = false">
                            <label class="block text-xs font-bold text-text-muted uppercase mb-1">Province <span class="text-danger">*</span></label>
                            <button type="button"
                                    @click="provinceDropdownOpen = !provinceDropdownOpen"
                                    :class="errors.province ? 'border-danger' : 'border-border-subtle'"
                                    class="w-full p-3 rounded-xl border bg-surface text-left flex items-center justify-between gap-2 cursor-pointer">
                                <span class="truncate" :class="form.province ? 'text-text-main font-medium' : 'text-text-muted'" x-text="form.province || 'Select Province'"></span>
                                <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="provinceDropdownOpen" x-cloak class="absolute z-50 left-0 right-0 mt-1 bg-surface border border-border-subtle rounded-2xl shadow-xl overflow-hidden">
                                <div class="p-2 border-b border-border-subtle">
                                    <input type="text" x-model="provinceSearch" placeholder="Search province..." class="w-full p-2 rounded-lg bg-surface-subtle text-xs outline-none">
                                </div>
                                <div class="max-h-48 overflow-y-auto divide-y divide-border-subtle/40 custom-scrollbar">
                                    <template x-for="prov in filteredProvinces" :key="prov.code">
                                        <button type="button"
                                                @click="selectProvince(prov)"
                                                class="w-full px-3 py-2 text-left text-xs hover:bg-brand-light/30 flex items-center justify-between cursor-pointer">
                                            <span x-text="prov.name"></span>
                                            <span x-show="form.province_code === prov.code" class="text-primary font-bold">&check;</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <template x-if="errors.province">
                                <p class="text-danger text-xs mt-1 font-medium" x-text="errors.province"></p>
                            </template>
                        </div>

                        <!-- Municipality / City -->
                        <div class="relative" @click.away="municipalityDropdownOpen = false">
                            <label class="block text-xs font-bold text-text-muted uppercase mb-1">City / Municipality <span class="text-danger">*</span></label>
                            <button type="button"
                                    :disabled="!form.province_code || isLoadingMunicipalities"
                                    @click="municipalityDropdownOpen = !municipalityDropdownOpen"
                                    :class="errors.municipality ? 'border-danger' : 'border-border-subtle'"
                                    class="w-full p-3 rounded-xl border bg-surface text-left flex items-center justify-between gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="truncate"
                                      :class="form.municipality ? 'text-text-main font-medium' : 'text-text-muted'"
                                      x-text="isLoadingMunicipalities ? 'Loading...' : (form.municipality || 'Select City/Municipality')"></span>
                                <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="municipalityDropdownOpen" x-cloak class="absolute z-50 left-0 right-0 mt-1 bg-surface border border-border-subtle rounded-2xl shadow-xl overflow-hidden">
                                <div class="p-2 border-b border-border-subtle">
                                    <input type="text" x-model="municipalitySearch" placeholder="Search city/municipality..." class="w-full p-2 rounded-lg bg-surface-subtle text-xs outline-none">
                                </div>
                                <div class="max-h-48 overflow-y-auto divide-y divide-border-subtle/40 custom-scrollbar">
                                    <template x-for="mun in filteredMunicipalities" :key="mun.code">
                                        <button type="button"
                                                @click="selectMunicipality(mun)"
                                                class="w-full px-3 py-2 text-left text-xs hover:bg-brand-light/30 flex items-center justify-between cursor-pointer">
                                            <span x-text="mun.name"></span>
                                            <span x-show="form.municipality_code === mun.code" class="text-primary font-bold">&check;</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <template x-if="errors.municipality">
                                <p class="text-danger text-xs mt-1 font-medium" x-text="errors.municipality"></p>
                            </template>
                        </div>

                        <!-- Barangay -->
                        <div class="relative" data-barangay-selector @click.away="barangayDropdownOpen = false">
                            <label class="block text-xs font-bold text-text-muted uppercase mb-1">Barangay <span class="text-danger">*</span></label>
                            <button type="button"
                                    :disabled="!form.municipality_code || isLoadingBarangays"
                                    @click="barangayDropdownOpen = !barangayDropdownOpen"
                                    :class="errors.barangay ? 'border-danger' : 'border-border-subtle'"
                                    class="w-full p-3 rounded-xl border bg-surface text-left flex items-center justify-between gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="truncate"
                                      :class="form.barangay ? 'text-text-main font-medium' : 'text-text-muted'"
                                      x-text="isLoadingBarangays ? 'Loading...' : (form.barangay || 'Select Barangay')"></span>
                                <svg class="w-4 h-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="barangayDropdownOpen" x-cloak class="absolute z-50 left-0 right-0 mt-1 bg-surface border border-border-subtle rounded-2xl shadow-xl overflow-hidden">
                                <div class="p-2 border-b border-border-subtle">
                                    <input type="text" x-model="barangaySearch" placeholder="Search barangay..." class="w-full p-2 rounded-lg bg-surface-subtle text-xs outline-none">
                                </div>
                                <div class="max-h-48 overflow-y-auto divide-y divide-border-subtle/40 custom-scrollbar">
                                    <template x-for="brgy in filteredBarangays" :key="brgy.code">
                                        <button type="button"
                                                @click="selectBarangay(brgy)"
                                                class="w-full px-3 py-2 text-left text-xs hover:bg-brand-light/30 flex items-center justify-between cursor-pointer">
                                            <span x-text="brgy.name"></span>
                                            <span x-show="form.barangay_code === brgy.code" class="text-primary font-bold">&check;</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <template x-if="errors.barangay">
                                <p class="text-danger text-xs mt-1 font-medium" x-text="errors.barangay"></p>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-text-muted uppercase mb-1">Street Name / Subdivision <span class="text-danger">*</span></label>
                            <input type="text"
                                   x-model="form.street_address"
                                   @blur="validateField('street_address')"
                                   maxlength="150"
                                   placeholder="e.g. 123 Rizal Street, San Lorenzo Village"
                                   :class="errors.street_address ? 'border-danger' : 'border-border-subtle focus:border-text-main'"
                                   class="w-full p-3 rounded-xl border bg-surface text-text-main outline-none">
                            <template x-if="errors.street_address">
                                <p class="text-danger text-xs mt-1 font-medium" x-text="errors.street_address"></p>
                            </template>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-text-muted uppercase mb-1">Postal Code (4 Digits) <span class="text-danger">*</span></label>
                            <input type="text"
                                   :value="form.postcode"
                                   @input="formatPostcodeInput($event)"
                                   @blur="validateField('postcode')"
                                   maxlength="4"
                                   placeholder="e.g. 4009"
                                   :class="errors.postcode ? 'border-danger' : 'border-border-subtle focus:border-text-main'"
                                   class="w-full p-3 rounded-xl border bg-surface font-mono text-text-main outline-none">
                            <template x-if="errors.postcode">
                                <p class="text-danger text-xs mt-1 font-medium" x-text="errors.postcode"></p>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-text-muted uppercase mb-1">House / Unit / Building / Landmark (Optional)</label>
                        <input type="text"
                               x-model="form.building_details"
                               maxlength="150"
                               placeholder="e.g. Unit 4B, Green Gate near Barangay Hall"
                               class="w-full p-3 rounded-xl border border-border-subtle bg-surface text-text-main outline-none focus:border-text-main">
                    </div>

                    <div class="pt-1">
                        <label class="inline-flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" x-model="form.is_default" class="w-4 h-4 rounded text-primary focus:ring-primary">
                            <span class="text-xs sm:text-sm font-semibold text-text-main">Set as default shipping address</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-border-subtle">
                        <button type="button"
                                @click="closeModal()"
                                class="px-5 py-2.5 rounded-xl border border-border-subtle bg-surface-subtle text-text-muted font-bold text-xs sm:text-sm cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="isSubmitting"
                                class="px-6 py-2.5 rounded-xl bg-text-main hover:bg-dark-grey text-surface font-bold text-xs sm:text-sm shadow-sm cursor-pointer disabled:opacity-50">
                            <span x-show="!isSubmitting" x-text="modalMode === 'edit' ? 'Update Address' : 'Save Address'"></span>
                            <span x-show="isSubmitting" x-cloak>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Delete Confirmation Modal -->
    <template x-teleport="body">
        <div x-show="showDeleteModal"
             x-cloak
             class="fixed inset-0 z-[205] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div @click.away="closeDeleteModal()"
                 class="bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4 text-center">
                <h3 class="text-base font-bold text-text-main">Delete Shipping Address?</h3>
                <p class="text-xs text-text-muted leading-relaxed">
                    Are you sure you want to remove this shipping address from your Address Book?
                </p>
                <template x-if="deleteError">
                    <p class="text-danger text-xs font-bold" x-text="deleteError"></p>
                </template>
                <div class="flex justify-center gap-3 pt-2">
                    <button type="button" @click="closeDeleteModal()" class="px-5 py-2.5 rounded-xl bg-surface-subtle text-text-muted text-xs font-bold cursor-pointer">
                        Cancel
                    </button>
                    <button type="button"
                            @click="confirmDelete()"
                            :disabled="isDeleting"
                            class="px-5 py-2.5 rounded-xl bg-danger text-white text-xs font-bold cursor-pointer disabled:opacity-50">
                        <span x-show="!isDeleting">Delete Address</span>
                        <span x-show="isDeleting" x-cloak>Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection