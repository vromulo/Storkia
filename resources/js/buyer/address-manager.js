export default (initialAddresses = [], initialProvinces = []) => ({
    addresses: initialAddresses,
    provinces: initialProvinces,
    municipalities: [],
    barangays: [],

    showModal: false,
    modalMode: 'add',
    editingId: null,

    showDeleteModal: false,
    deletingId: null,
    isDeleting: false,
    deleteError: '',

    provinceSearch: '',
    municipalitySearch: '',
    barangaySearch: '',
    provinceDropdownOpen: false,
    municipalityDropdownOpen: false,
    barangayDropdownOpen: false,
    isLoadingMunicipalities: false,
    isLoadingBarangays: false,
    isSubmitting: false,
    generalError: '',

    form: {
        first_name: '',
        last_name: '',
        phone_number: '',
        province_code: '',
        province: '',
        municipality_code: '',
        municipality: '',
        barangay_code: '',
        barangay: '',
        postcode: '',
        street_address: '',
        building_details: '',
        is_default: false,
    },

    formattedPhone: '',
    errors: {},

    get filteredProvinces() {
        if (!this.provinceSearch.trim()) return this.provinces;
        const q = this.provinceSearch.toLowerCase();
        return this.provinces.filter(p => p.name.toLowerCase().includes(q));
    },

    get filteredMunicipalities() {
        if (!this.municipalitySearch.trim()) return this.municipalities;
        const q = this.municipalitySearch.toLowerCase();
        return this.municipalities.filter(m => m.name.toLowerCase().includes(q));
    },

    get filteredBarangays() {
        if (!this.barangaySearch.trim()) return this.barangays;
        const q = this.barangaySearch.toLowerCase();
        return this.barangays.filter(b => b.name.toLowerCase().includes(q));
    },

    resetForm() {
        this.form = {
            first_name: '',
            last_name: '',
            phone_number: '',
            province_code: '',
            province: '',
            municipality_code: '',
            municipality: '',
            barangay_code: '',
            barangay: '',
            postcode: '',
            street_address: '',
            building_details: '',
            is_default: this.addresses.length === 0,
        };
        this.formattedPhone = '';
        this.municipalities = [];
        this.barangays = [];
        this.provinceSearch = '';
        this.municipalitySearch = '';
        this.barangaySearch = '';
        this.provinceDropdownOpen = false;
        this.municipalityDropdownOpen = false;
        this.barangayDropdownOpen = false;
        this.errors = {};
        this.generalError = '';
    },

    openAddModal() {
        if (this.addresses.length >= 10) return;
        this.resetForm();
        this.modalMode = 'add';
        this.editingId = null;
        this.showModal = true;
    },

    async openEditModal(id) {
        const addr = this.addresses.find(a => String(a.id) === String(id));
        if (!addr) return;

        this.resetForm();
        this.modalMode = 'edit';
        this.editingId = addr.id;

        let cleanPhone = String(addr.phone_number || '').replace(/\D/g, '');
        if (cleanPhone.startsWith('63') && cleanPhone.length >= 12) {
            cleanPhone = cleanPhone.slice(2);
        }
        cleanPhone = cleanPhone.replace(/^0+/, '').slice(0, 10);

        this.form = {
            first_name: addr.first_name || '',
            last_name: addr.last_name || '',
            phone_number: cleanPhone,
            province_code: addr.province_code || '',
            province: addr.province || '',
            municipality_code: addr.municipality_code || '',
            municipality: addr.municipality || '',
            barangay_code: addr.barangay_code || '',
            barangay: addr.barangay || '',
            postcode: addr.postcode || '',
            street_address: addr.street_address || '',
            building_details: addr.building_details || '',
            is_default: Boolean(addr.is_default),
        };

        this.formattedPhone = this.buildFormattedPhone(cleanPhone);
        this.showModal = true;

        if (this.form.province_code) {
            await this.fetchMunicipalities(this.form.province_code, true);
        }
        if (this.form.municipality_code) {
            await this.fetchBarangays(this.form.municipality_code, true);
        }
    },

    closeModal() {
        if (this.isSubmitting) return;
        this.showModal = false;
    },

    openDeleteModal(id) {
        this.deletingId = id;
        this.deleteError = '';
        this.showDeleteModal = true;
    },

    closeDeleteModal() {
        if (this.isDeleting) return;
        this.showDeleteModal = false;
        this.deletingId = null;
        this.deleteError = '';
    },

    buildFormattedPhone(digits) {
        let formatted = '';
        if (digits.length > 0) formatted += digits.substring(0, 3);
        if (digits.length > 3) formatted += ' ' + digits.substring(3, 6);
        if (digits.length > 6) formatted += ' ' + digits.substring(6, 10);
        return formatted;
    },

    formatPhoneInput(event) {
        let input = event.target.value.replace(/\D/g, '');
        if (input.startsWith('63') && input.length >= 12) {
            input = input.slice(2);
        }
        if (input.startsWith('0')) {
            input = input.replace(/^0+/, '');
        }
        if (input.length > 0 && !input.startsWith('9')) {
            input = '';
        }
        input = input.substring(0, 10);
        this.form.phone_number = input;
        this.formattedPhone = this.buildFormattedPhone(input);
        event.target.value = this.formattedPhone;

        if (this.errors.phone_number) {
            this.validateField('phone_number');
        }
    },

    formatPostcodeInput(event) {
        const digits = event.target.value.replace(/\D/g, '').substring(0, 4);
        this.form.postcode = digits;
        event.target.value = digits;
        if (this.errors.postcode) {
            this.validateField('postcode');
        }
    },

    async selectProvince(prov) {
        this.form.province_code = prov.code;
        this.form.province = prov.name;
        this.provinceDropdownOpen = false;
        this.provinceSearch = '';

        this.form.municipality_code = '';
        this.form.municipality = '';
        this.form.barangay_code = '';
        this.form.barangay = '';
        this.form.postcode = '';
        this.municipalities = [];
        this.barangays = [];

        delete this.errors.province;
        delete this.errors.municipality;
        delete this.errors.barangay;

        await this.fetchMunicipalities(prov.code);
    },

    async fetchMunicipalities(provinceCode, preserveSelection = false) {
        this.isLoadingMunicipalities = true;
        try {
            const res = await fetch(`/user/addresses/psgc/municipalities/${encodeURIComponent(provinceCode)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                this.municipalities = await res.json();
                if (preserveSelection && this.form.municipality_code) {
                    const matched = this.municipalities.find(m => String(m.code) === String(this.form.municipality_code));
                    if (matched && !this.form.postcode && matched.zip_code) {
                        this.form.postcode = matched.zip_code;
                    }
                }
            }
        } catch (e) {
            this.municipalities = [];
        } finally {
            this.isLoadingMunicipalities = false;
        }
    },

    async selectMunicipality(mun) {
        this.form.municipality_code = mun.code;
        this.form.municipality = mun.name;
        if (mun.zip_code) {
            this.form.postcode = String(mun.zip_code).replace(/\D/g, '').substring(0, 4);
            delete this.errors.postcode;
        }
        this.municipalityDropdownOpen = false;
        this.municipalitySearch = '';

        this.form.barangay_code = '';
        this.form.barangay = '';
        this.barangays = [];

        delete this.errors.municipality;
        delete this.errors.barangay;

        await this.fetchBarangays(mun.code, false);
    },

    async fetchBarangays(municipalityCode, preserveSelection = false) {
        this.isLoadingBarangays = true;
        try {
            const res = await fetch(`/user/addresses/psgc/barangays/${encodeURIComponent(municipalityCode)}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                this.barangays = await res.json();
                if (!preserveSelection && this.barangays.length === 1) {
                    this.selectBarangay(this.barangays[0]);
                } else if (preserveSelection && this.form.barangay_code) {
                    const matched = this.barangays.find(b => String(b.code) === String(this.form.barangay_code));
                    if (matched) {
                        this.form.barangay = matched.name;
                    }
                }
            }
        } catch (e) {
            this.barangays = [];
        } finally {
            this.isLoadingBarangays = false;
        }
    },

    selectBarangay(brgy) {
        this.form.barangay_code = brgy.code;
        this.form.barangay = brgy.name;
        this.barangayDropdownOpen = false;
        this.barangaySearch = '';
        delete this.errors.barangay;
    },

    validateField(field) {
        const nameRegex = /^[\p{L}\s'\-\.]+$/u;

        switch (field) {
            case 'first_name': {
                const v = (this.form.first_name || '').trim();
                if (!v) {
                    this.errors.first_name = 'First name is required.';
                } else if (v.length < 2 || v.length > 60) {
                    this.errors.first_name = 'First name must be between 2 and 60 characters.';
                } else if (!nameRegex.test(v)) {
                    this.errors.first_name = 'First name may only contain letters, spaces, hyphens, and periods.';
                } else {
                    delete this.errors.first_name;
                }
                break;
            }
            case 'last_name': {
                const v = (this.form.last_name || '').trim();
                if (!v) {
                    this.errors.last_name = 'Last name is required.';
                } else if (v.length < 2 || v.length > 60) {
                    this.errors.last_name = 'Last name must be between 2 and 60 characters.';
                } else if (!nameRegex.test(v)) {
                    this.errors.last_name = 'Last name may only contain letters, spaces, hyphens, and periods.';
                } else {
                    delete this.errors.last_name;
                }
                break;
            }
            case 'phone_number':
                if (!this.form.phone_number) {
                    this.errors.phone_number = 'Phone number is required.';
                } else if (!/^9\d{9}$/.test(this.form.phone_number)) {
                    this.errors.phone_number = 'Please enter a valid 10-digit mobile number starting with 9.';
                } else {
                    delete this.errors.phone_number;
                }
                break;
            case 'province':
                if (!this.form.province_code || !this.form.province) {
                    this.errors.province = 'Please select a province.';
                } else {
                    delete this.errors.province;
                }
                break;
            case 'municipality':
                if (!this.form.municipality_code || !this.form.municipality) {
                    this.errors.municipality = 'Please select a city or municipality.';
                } else {
                    delete this.errors.municipality;
                }
                break;
            case 'barangay':
                if (!this.form.barangay_code || !this.form.barangay) {
                    if (this.barangays.length > 0 && !document.querySelector('[data-barangay-selector]')) {
                        this.form.barangay_code = this.barangays[0].code;
                        this.form.barangay = this.barangays[0].name;
                        delete this.errors.barangay;
                    } else {
                        this.errors.barangay = 'Please select a barangay.';
                    }
                } else {
                    delete this.errors.barangay;
                }
                break;
            case 'postcode':
                if (!this.form.postcode.trim()) {
                    this.errors.postcode = 'Postal code is required.';
                } else if (!/^\d{4}$/.test(this.form.postcode.trim())) {
                    this.errors.postcode = 'Postal code must be a valid 4-digit PH zip code.';
                } else {
                    delete this.errors.postcode;
                }
                break;
            case 'street_address': {
                const s = (this.form.street_address || '').trim();
                if (!s) {
                    this.errors.street_address = 'Street address is required.';
                } else if (s.length < 5) {
                    this.errors.street_address = 'Please enter a complete street address (at least 5 characters).';
                } else {
                    delete this.errors.street_address;
                }
                break;
            }
        }
    },

    validateForm() {
        this.errors = {};
        this.generalError = '';
        ['first_name', 'last_name', 'phone_number', 'province', 'municipality', 'barangay', 'postcode', 'street_address'].forEach(f => {
            this.validateField(f);
        });
        return Object.keys(this.errors).length === 0;
    },

    async submitForm() {
        if (!this.validateForm() || this.isSubmitting) return;

        this.isSubmitting = true;
        this.generalError = '';

        const isEdit = this.modalMode === 'edit' && this.editingId;
        const url = isEdit
            ? `/user/addresses/${this.editingId}`
            : `/user/addresses`;

        const payload = {
            ...this.form,
            ...(isEdit ? { _method: 'PATCH' } : {})
        };

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    Object.keys(data.errors).forEach(k => {
                        const firstMsg = Array.isArray(data.errors[k]) ? data.errors[k][0] : data.errors[k];
                        if (k === 'barangay_code' || k === 'barangay') {
                            this.errors.barangay = firstMsg;
                        } else if (k === 'province_code') {
                            this.errors.province = firstMsg;
                        } else if (k === 'municipality_code') {
                            this.errors.municipality = firstMsg;
                        } else {
                            this.errors[k] = firstMsg;
                        }
                    });
                }
                this.generalError = data.message || 'Please check the highlighted address fields and try again.';
                return;
            }

            if (data.addresses) {
                this.addresses = data.addresses;
            }

            this.showModal = false;
            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    title: 'Success',
                    message: data.message || 'Address saved successfully.',
                    type: 'success'
                }
            }));
        } catch (e) {
            this.generalError = 'Network error while saving address. Please try again.';
        } finally {
            this.isSubmitting = false;
        }
    },

    async confirmDelete() {
        if (!this.deletingId || this.isDeleting) return;

        this.isDeleting = true;
        this.deleteError = '';

        try {
            const response = await fetch(`/user/addresses/${this.deletingId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ _method: 'DELETE' })
            });

            const data = await response.json();

            if (!response.ok) {
                this.deleteError = data.message || 'Unable to delete address.';
                return;
            }

            if (data.addresses) {
                this.addresses = data.addresses;
            }

            this.showDeleteModal = false;
            this.deletingId = null;

            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    title: 'Success',
                    message: data.message || 'Address deleted successfully.',
                    type: 'success'
                }
            }));
        } catch (e) {
            this.deleteError = 'Network error occurred. Please try again.';
        } finally {
            this.isDeleting = false;
        }
    }
});