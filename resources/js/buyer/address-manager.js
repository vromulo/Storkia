export default function addressManager(initialAddresses = [], provinces = []) {
    return {
        addresses: initialAddresses,
        provinces: provinces,
        municipalities: [],
        
        isModalOpen: false,
        isDeleteModalOpen: false,
        isSubmitting: false,
        isDeleting: false,
        isLoadingMunicipalities: false,
        
        targetDeleteId: null,
        editingId: null,
        
        form: {
            first_name: '',
            last_name: '',
            phone_number: '',
            province_code: '',
            province: '',
            municipality_code: '',
            municipality: '',
            postcode: '',
            street_address: '',
            building_details: '',
            is_default: false,
        },
        
        errors: {},

        openAddModal() {
            this.editingId = null;
            this.errors = {};
            this.municipalities = [];
            this.form = {
                first_name: '',
                last_name: '',
                phone_number: '',
                province_code: '',
                province: '',
                municipality_code: '',
                municipality: '',
                postcode: '',
                street_address: '',
                building_details: '',
                is_default: this.addresses.length === 0,
            };
            this.isModalOpen = true;
        },

        async openEditModal(address) {
            this.editingId = address.id;
            this.errors = {};
            this.form = {
                first_name: address.first_name || '',
                last_name: address.last_name || '',
                phone_number: (address.phone_number || '').replace(/^\+?63/, '').replace(/^0/, ''),
                province_code: address.province_code || '',
                province: address.province || '',
                municipality_code: address.municipality_code || '',
                municipality: address.municipality || '',
                postcode: address.postcode || '',
                street_address: address.street_address || '',
                building_details: address.building_details || '',
                is_default: Boolean(address.is_default),
            };

            if (this.form.province_code) {
                await this.fetchMunicipalities(this.form.province_code);
            }
            this.isModalOpen = true;
        },

        closeModal() {
            if (!this.isSubmitting) {
                this.isModalOpen = false;
                this.errors = {};
            }
        },

        async onProvinceChange() {
            this.form.municipality_code = '';
            this.form.municipality = '';
            this.municipalities = [];
            
            const match = this.provinces.find(p => String(p.code) === String(this.form.province_code));
            this.form.province = match ? match.name : '';

            if (this.form.province_code) {
                await this.fetchMunicipalities(this.form.province_code);
            }
        },

        onMunicipalityChange() {
            const match = this.municipalities.find(m => String(m.code) === String(this.form.municipality_code));
            this.form.municipality = match ? match.name : '';
        },

        async fetchMunicipalities(provinceCode) {
            this.isLoadingMunicipalities = true;
            try {
                const res = await fetch(`/user/addresses/psgc/municipalities/${provinceCode}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    this.municipalities = await res.json();
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.isLoadingMunicipalities = false;
            }
        },

        promptDelete(id) {
            this.targetDeleteId = id;
            this.isDeleteModalOpen = true;
        },

        closeDeleteModal() {
            if (!this.isDeleting) {
                this.isDeleteModalOpen = false;
                this.targetDeleteId = null;
            }
        },

        async submitForm() {
            this.isSubmitting = true;
            this.errors = {};

            const url = this.editingId 
                ? `/user/addresses/${this.editingId}`
                : '/user/addresses';
            
            const method = this.editingId ? 'PUT' : 'POST';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        _method: method,
                        ...this.form,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.addresses = data.addresses;
                    this.isModalOpen = false;
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: data.message, type: 'success' }
                    }));
                } else if (response.status === 422) {
                    this.errors = data.errors || {};
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: data.message || 'Failed to save address.', type: 'danger' }
                    }));
                }
            } catch (err) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { title: 'Error', message: 'A network error occurred.', type: 'danger' }
                }));
            } finally {
                this.isSubmitting = false;
            }
        },

        async confirmDelete() {
            if (!this.targetDeleteId) return;
            this.isDeleting = true;

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const response = await fetch(`/user/addresses/${this.targetDeleteId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ _method: 'DELETE' }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.addresses = data.addresses;
                    this.isDeleteModalOpen = false;
                    this.targetDeleteId = null;
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Deleted', message: data.message, type: 'success' }
                    }));
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: data.message || 'Could not delete address.', type: 'danger' }
                    }));
                }
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { title: 'Error', message: 'Network error occurred.', type: 'danger' }
                }));
            } finally {
                this.isDeleting = false;
            }
        }
    };
}