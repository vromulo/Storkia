export default function sellerStoreSettingsManager(initialTab = 'store', initialEdit = null, data = {}) {
    const baseUrl = data.base_url || '/seller/store-settings';

    return {
        activeTab: initialTab,
        activeEdit: initialEdit,

        // --- Store / Hub Profile State ---
        businessName: data.business_name || '',
        businessNameInput: data.business_name || '',
        isSavingStoreName: false,
        storeNameError: null,

        photoUrl: data.photo_url || '',
        isUploadingPhoto: false,
        isRemovingPhoto: false,
        photoError: null,
        isDraggingPhoto: false,

        // --- Document Preview Modal State ---
        docModalOpen: false,
        docModalTitle: '',
        docModalUrl: '',
        docModalType: 'image',

        // --- 1. Full Name State ---
        isSavingName: false,
        firstName: data.first_name || '',
        lastName: data.last_name || '',
        fullName: data.full_name || '',
        nameErrors: { first_name: null, last_name: null },

        // --- 2. Email State ---
        isSavingEmail: false,
        email: data.email || '',
        maskedEmail: data.masked_email || '',
        emailErrors: { email: null },

        // --- 3. Phone State ---
        isSavingPhone: false,
        phoneRaw: data.phone_raw || '',
        formattedPhone: data.formatted_phone || '',
        maskedPhone: data.masked_phone || '',
        hasPhone: Boolean(data.has_phone),
        phoneErrors: { phone: null },

        // --- 4. Password State ---
        isSavingPassword: false,
        passwordLastUpdated: data.password_last_updated || 'Never updated',
        passwordUnlockToken: null,
        newPassword: '',
        confirmPassword: '',
        passwordErrors: { password: null, password_confirmation: null },

        // --- Email OTP Modal State ---
        showOtpModal: false,
        otpCode: '',
        otpMaskedEmail: '',
        isVerifyingOtp: false,
        isResendingOtp: false,
        otpError: null,
        resendCooldown: 0,
        timerInterval: null,

        // --- Password OTP Modal State ---
        showPasswordOtpModal: false,
        passwordOtpCode: '',
        passwordOtpMaskedEmail: '',
        isVerifyingPasswordOtp: false,
        isResendingPasswordOtp: false,
        passwordOtpError: null,
        passwordResendCooldown: 0,
        passwordTimerInterval: null,

        setTab(tab) {
            if (this.isAnySaving()) return;
            this.activeTab = tab;
            this.activeEdit = null;
        },

        isAnySaving() {
            return (
                this.isSavingStoreName ||
                this.isUploadingPhoto ||
                this.isSavingName ||
                this.isSavingEmail ||
                this.isSavingPhone ||
                this.isSavingPassword
            );
        },

        toggle(field) {
            this.activeEdit = this.activeEdit === field ? null : field;

            if (this.activeEdit !== 'store_name') {
                this.storeNameError = null;
                this.businessNameInput = this.businessName;
            }
            if (this.activeEdit !== 'username') {
                this.nameErrors.first_name = null;
                this.nameErrors.last_name = null;
            }
            if (this.activeEdit !== 'email') {
                this.emailErrors.email = null;
            }
            if (this.activeEdit !== 'phone') {
                this.phoneErrors.phone = null;
                this.formattedPhone = this.formatNumber(this.phoneRaw);
            }
            if (this.activeEdit !== 'password') {
                this.passwordErrors.password = null;
                this.passwordErrors.password_confirmation = null;
                this.newPassword = '';
                this.confirmPassword = '';
            }
        },

        isEditing() {
            return this.activeEdit !== null;
        },

        isDimmed(field) {
            return this.isEditing() && this.activeEdit !== field;
        },

        // --- Store / Hub Photo Upload & Removal ---
        handlePhotoDrop(event) {
            this.isDraggingPhoto = false;
            const file = event.dataTransfer?.files?.[0];
            if (file) {
                this.uploadPhotoFile(file);
            }
        },

        handlePhotoSelect(event) {
            const file = event.target?.files?.[0];
            if (file) {
                this.uploadPhotoFile(file);
            }
            event.target.value = '';
        },

        async uploadPhotoFile(file) {
            this.photoError = null;

            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                this.photoError = 'Please select a valid JPEG, PNG, or WEBP image.';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                this.photoError = 'Photo size must not exceed 5MB.';
                return;
            }

            this.isUploadingPhoto = true;
            const formData = new FormData();
            formData.append('photo', file);

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                document.querySelector('input[name="_token"]')?.value;

            try {
                const response = await fetch(`${baseUrl}/photo`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: formData,
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.photoUrl = resData.photo_url;
                    window.dispatchEvent(new CustomEvent('seller-photo-updated', {
                        detail: { photo_url: resData.photo_url }
                    }));
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: resData.message, type: 'success' }
                    }));
                } else if (response.status === 422) {
                    this.photoError = resData.errors?.photo?.[0] || resData.message;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: resData.message || 'Unable to upload photo.', type: 'danger' }
                    }));
                }
            } catch (err) {
                console.error(err);
                this.photoError = 'A network error occurred while uploading.';
            } finally {
                this.isUploadingPhoto = false;
            }
        },

        async removePhoto() {
            if (!this.photoUrl || this.isRemovingPhoto) return;
            this.isRemovingPhoto = true;
            this.photoError = null;

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                document.querySelector('input[name="_token"]')?.value;

            try {
                const response = await fetch(`${baseUrl}/photo`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.photoUrl = '';
                    window.dispatchEvent(new CustomEvent('seller-photo-updated', {
                        detail: { photo_url: '' }
                    }));
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: resData.message, type: 'info' }
                    }));
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isRemovingPhoto = false;
            }
        },

        // --- Store / Hub Name Submission ---
        async submitStoreName(event) {
            const form = event.target;
            const formData = new FormData(form);
            this.isSavingStoreName = true;
            this.storeNameError = null;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': formData.get('_token'),
                    },
                    body: formData,
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.businessName = resData.business_name;
                    this.businessNameInput = resData.business_name;
                    this.activeEdit = null;

                    window.dispatchEvent(new CustomEvent('seller-store-name-updated', {
                        detail: { business_name: resData.business_name }
                    }));
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: resData.message, type: 'success' }
                    }));
                } else if (response.status === 422) {
                    this.storeNameError = resData.errors?.business_name?.[0] || resData.message;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: resData.message || 'Unable to update name.', type: 'danger' }
                    }));
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isSavingStoreName = false;
            }
        },

        // --- Document Inspection Modal ---
        openDocPreview(title, url, ext) {
            this.docModalTitle = title;
            this.docModalUrl = url;
            this.docModalType = ext === 'pdf' ? 'pdf' : 'image';
            this.docModalOpen = true;
        },

        // --- Phone Formatting & Submission ---
        formatPhoneInput(event) {
            let digits = event.target.value.replace(/\D/g, '');
            if (digits.startsWith('63')) {
                digits = digits.substring(2);
            } else if (digits.startsWith('0')) {
                digits = digits.substring(1);
            }
            digits = digits.slice(0, 10);

            this.phoneRaw = digits;
            this.formattedPhone = this.formatNumber(digits);
            event.target.value = this.formattedPhone;

            if (this.phoneErrors) {
                this.phoneErrors.phone = null;
            }
        },

        formatNumber(digits) {
            if (!digits) return '';
            let clean = String(digits).replace(/\D/g, '').slice(0, 10);
            let formatted = '';
            if (clean.length > 0) formatted += clean.substring(0, Math.min(3, clean.length));
            if (clean.length > 3) formatted += ' ' + clean.substring(3, Math.min(6, clean.length));
            if (clean.length > 6) formatted += ' ' + clean.substring(6, 10);
            return formatted;
        },

        async submitPhone(event) {
            const form = event.target;
            this.isSavingPhone = true;
            this.phoneErrors.phone = null;

            const digits = this.phoneRaw || this.formattedPhone.replace(/\D/g, '');
            if (!digits) {
                this.phoneErrors.phone = 'Please enter your phone number.';
                this.isSavingPhone = false;
                return;
            }
            if (!/^9\d{9}$/.test(digits)) {
                this.phoneErrors.phone = 'Please enter a valid 10-digit mobile number starting with 9.';
                this.isSavingPhone = false;
                return;
            }

            const formData = new FormData(form);
            formData.set('contact_no', digits);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': formData.get('_token'),
                    },
                    body: formData,
                });

                const resData = await response.json();

                if (response.ok && resData.success) {
                    this.hasPhone = true;
                    this.maskedPhone = resData.masked_phone;
                    this.phoneRaw = resData.raw_phone;
                    this.formattedPhone = this.formatNumber(resData.raw_phone);
                    this.activeEdit = null;

                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: resData.message, type: 'success' }
                    }));
                } else if (response.status === 422) {
                    this.phoneErrors.phone = resData.errors?.contact_no?.[0] || resData.errors?.clean_contact?.[0] || resData.message;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: resData.message || 'Unable to update phone number.', type: 'danger' }
                    }));
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isSavingPhone = false;
            }
        },

        // --- Full Name Submission ---
        async submitName(event) {
            const form = event.target;
            const formData = new FormData(form);
            this.isSavingName = true;
            this.nameErrors.first_name = null;
            this.nameErrors.last_name = null;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': formData.get('_token'),
                    },
                    body: formData,
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.firstName = resData.first_name;
                    this.lastName = resData.last_name;
                    this.fullName = resData.full_name;
                    this.activeEdit = null;

                    window.dispatchEvent(new CustomEvent('seller-owner-name-updated', {
                        detail: { full_name: resData.full_name }
                    }));
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: resData.message, type: 'success' }
                    }));
                } else if (response.status === 422 && resData.errors) {
                    this.nameErrors.first_name = resData.errors.first_name?.[0] || null;
                    this.nameErrors.last_name = resData.errors.last_name?.[0] || null;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: resData.message || 'Unable to update name.', type: 'danger' }
                    }));
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isSavingName = false;
            }
        },

        // --- Email OTP & Verification ---
        startCooldown(seconds) {
            this.resendCooldown = seconds;
            if (this.timerInterval) clearInterval(this.timerInterval);
            this.timerInterval = setInterval(() => {
                if (this.resendCooldown > 0) this.resendCooldown--;
                else clearInterval(this.timerInterval);
            }, 1000);
        },

        async submitEmail(event) {
            const form = event.target;
            const formData = new FormData(form);
            this.isSavingEmail = true;
            this.emailErrors.email = null;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': formData.get('_token'),
                    },
                    body: formData,
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.otpMaskedEmail = resData.masked_email;
                    this.otpCode = '';
                    this.otpError = null;
                    this.showOtpModal = true;
                    this.startCooldown(resData.cooldown || 60);

                    this.$nextTick(() => {
                        document.getElementById('seller_otp_hidden_input')?.focus();
                    });
                } else if (response.status === 422) {
                    this.emailErrors.email = resData.errors?.email?.[0] || resData.message;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: resData.message || 'Unable to send code.', type: 'danger' }
                    }));
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isSavingEmail = false;
            }
        },

        onOtpInput(event) {
            this.otpCode = event.target.value.replace(/\D/g, '').slice(0, 6);
            event.target.value = this.otpCode;
            this.otpError = null;
            if (this.otpCode.length === 6 && !this.isVerifyingOtp) this.verifyOtp();
        },

        async verifyOtp() {
            this.isVerifyingOtp = true;
            this.otpError = null;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                    document.querySelector('input[name="_token"]')?.value;
                const response = await fetch(`${baseUrl}/email/verify-otp`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ code: this.otpCode }),
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.email = resData.email;
                    this.maskedEmail = resData.masked_email;
                    this.showOtpModal = false;
                    this.activeEdit = null;

                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: resData.message, type: 'success' }
                    }));
                } else {
                    this.otpError = resData.message || 'Invalid verification code.';
                    this.otpCode = '';
                    const input = document.getElementById('seller_otp_hidden_input');
                    if (input) { input.value = ''; input.focus(); }
                }
            } catch (err) {
                this.otpError = 'Network error. Please try again.';
            } finally {
                this.isVerifyingOtp = false;
            }
        },

        async resendOtp() {
            if (this.resendCooldown > 0 || this.isResendingOtp) return;
            this.isResendingOtp = true;
            this.otpError = null;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                    document.querySelector('input[name="_token"]')?.value;
                const response = await fetch(`${baseUrl}/email/request-otp`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ email: this.email }),
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.otpCode = '';
                    this.startCooldown(resData.cooldown || 60);
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Notice', message: 'A new code has been sent.', type: 'info' }
                    }));
                } else {
                    this.otpError = resData.message || 'Unable to resend code.';
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isResendingOtp = false;
            }
        },

        closeOtpModal() {
            this.showOtpModal = false;
            this.otpCode = '';
            this.otpError = null;
        },

        // --- Password Flow ---
        async handlePasswordClick() {
            if (this.activeEdit === 'password') {
                this.toggle('password');
                return;
            }

            if (this.passwordUnlockToken) {
                this.activeEdit = 'password';
                return;
            }

            this.isSavingPassword = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                    document.querySelector('input[name="_token"]')?.value;
                const response = await fetch(`${baseUrl}/password/request-otp`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    }
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.passwordOtpMaskedEmail = resData.masked_email;
                    this.passwordOtpCode = '';
                    this.passwordOtpError = null;
                    this.showPasswordOtpModal = true;
                    this.startPasswordCooldown(resData.cooldown || 60);

                    this.$nextTick(() => {
                        document.getElementById('seller_password_otp_hidden_input')?.focus();
                    });
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Notice', message: resData.message || 'Please wait before requesting a new code.', type: 'info' }
                    }));
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isSavingPassword = false;
            }
        },

        startPasswordCooldown(seconds) {
            this.passwordResendCooldown = seconds;
            if (this.passwordTimerInterval) clearInterval(this.passwordTimerInterval);
            this.passwordTimerInterval = setInterval(() => {
                if (this.passwordResendCooldown > 0) this.passwordResendCooldown--;
                else clearInterval(this.passwordTimerInterval);
            }, 1000);
        },

        onPasswordOtpInput(event) {
            this.passwordOtpCode = event.target.value.replace(/\D/g, '').slice(0, 6);
            event.target.value = this.passwordOtpCode;
            this.passwordOtpError = null;
            if (this.passwordOtpCode.length === 6 && !this.isVerifyingPasswordOtp) this.verifyPasswordOtp();
        },

        async verifyPasswordOtp() {
            this.isVerifyingPasswordOtp = true;
            this.passwordOtpError = null;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                    document.querySelector('input[name="_token"]')?.value;
                const response = await fetch(`${baseUrl}/password/verify-otp`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ code: this.passwordOtpCode }),
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.passwordUnlockToken = resData.unlock_token;
                    this.showPasswordOtpModal = false;
                    this.activeEdit = 'password';
                } else {
                    this.passwordOtpError = resData.message || 'Invalid code.';
                    this.passwordOtpCode = '';
                    const input = document.getElementById('seller_password_otp_hidden_input');
                    if (input) { input.value = ''; input.focus(); }
                }
            } catch (err) {
                this.passwordOtpError = 'Network error. Please try again.';
            } finally {
                this.isVerifyingPasswordOtp = false;
            }
        },

        async resendPasswordOtp() {
            if (this.passwordResendCooldown > 0 || this.isResendingPasswordOtp) return;
            this.isResendingPasswordOtp = true;
            this.passwordOtpError = null;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                    document.querySelector('input[name="_token"]')?.value;
                const response = await fetch(`${baseUrl}/password/request-otp`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    }
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.passwordOtpCode = '';
                    this.startPasswordCooldown(resData.cooldown || 60);
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Notice', message: 'A new code has been sent.', type: 'info' }
                    }));
                } else {
                    this.passwordOtpError = resData.message || 'Unable to resend code.';
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isResendingPasswordOtp = false;
            }
        },

        closePasswordOtpModal() {
            this.showPasswordOtpModal = false;
            this.passwordOtpCode = '';
            this.passwordOtpError = null;
        },

        validatePasswordClientSide() {
            this.passwordErrors.password = null;
            this.passwordErrors.password_confirmation = null;

            if (this.confirmPassword && this.newPassword !== this.confirmPassword) {
                this.passwordErrors.password_confirmation = 'The password confirmation does not match.';
            }
        },

        async submitPassword(event) {
            const form = event.target;
            this.isSavingPassword = true;
            this.passwordErrors.password = null;
            this.passwordErrors.password_confirmation = null;

            const formData = new FormData(form);
            formData.append('unlock_token', this.passwordUnlockToken);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': formData.get('_token'),
                    },
                    body: formData,
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.passwordLastUpdated = resData.formatted_time || 'Last updated a few seconds ago';
                    this.activeEdit = null;
                    this.newPassword = '';
                    this.confirmPassword = '';
                    this.passwordUnlockToken = null;

                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: resData.message, type: 'success' }
                    }));
                } else if (response.status === 422 && resData.errors) {
                    this.passwordErrors.password = resData.errors.password?.[0] || null;
                    this.passwordErrors.password_confirmation = resData.errors.password_confirmation?.[0] || null;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: resData.message || 'Unable to update password.', type: 'danger' }
                    }));
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isSavingPassword = false;
            }
        }
    };
}