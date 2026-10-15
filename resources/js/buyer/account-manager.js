export default function accountManager(initialEdit = null, userData = {}) {
    return {
        activeEdit: initialEdit,

        // --- 1. Full Name State ---
        isSavingName: false,
        firstName: userData.first_name || '',
        lastName: userData.last_name || '',
        fullName: userData.full_name || '',
        nameErrors: { first_name: null, last_name: null },

        // --- 2. Email State ---
        isSavingEmail: false,
        email: userData.email || '',
        maskedEmail: userData.masked_email || '',
        emailErrors: { email: null },

        // --- 3. Phone State ---
        isSavingPhone: false,
        phoneRaw: userData.phone_raw || '',
        formattedPhone: userData.formatted_phone || '',
        maskedPhone: userData.masked_phone || '',
        hasPhone: Boolean(userData.has_phone),
        phoneErrors: { phone: null },

        // --- 4. Password State ---
        isSavingPassword: false,
        passwordLastUpdated: userData.password_last_updated || 'Never updated',
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

        toggle(field) {
            this.activeEdit = (this.activeEdit === field) ? null : field;
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
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                            || formData.get('_token'),
                    },
                    body: formData,
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.hasPhone = true;
                    this.maskedPhone = data.masked_phone;
                    this.phoneRaw = data.raw_phone;
                    this.formattedPhone = this.formatNumber(data.raw_phone);
                    this.activeEdit = null;

                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: {
                            title: 'Success',
                            message: data.message || 'Phone number updated successfully.',
                            type: 'success'
                        }
                    }));
                } else if (response.status === 422) {
                    if (data.errors) {
                        this.phoneErrors.phone = data.errors.contact_no ? data.errors.contact_no[0] : (data.errors.clean_contact ? data.errors.clean_contact[0] : null);
                    } else if (data.message) {
                        this.phoneErrors.phone = data.message;
                    }
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: {
                            title: 'Error',
                            message: data.message || 'Unable to update phone number.',
                            type: 'danger'
                        }
                    }));
                }
            } catch (err) {
                console.error(err);
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        title: 'Error',
                        message: 'A network error occurred. Please try again.',
                        type: 'danger'
                    }
                }));
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
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                            || formData.get('_token'),
                    },
                    body: formData,
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    this.firstName = data.first_name;
                    this.lastName = data.last_name;
                    this.fullName = data.full_name;
                    this.activeEdit = null;

                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: data.message, type: 'success' }
                    }));
                } else if (response.status === 422 && data.errors) {
                    this.nameErrors.first_name = data.errors.first_name?.[0] || null;
                    this.nameErrors.last_name = data.errors.last_name?.[0] || null;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: data.message || 'Unable to update name.', type: 'danger' }
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

                const data = await response.json();
                if (response.ok && data.success) {
                    this.otpMaskedEmail = data.masked_email;
                    this.otpCode = '';
                    this.otpError = null;
                    this.showOtpModal = true;
                    this.startCooldown(data.cooldown || 60);

                    this.$nextTick(() => {
                        document.getElementById('otp_hidden_input')?.focus();
                    });
                } else if (response.status === 422) {
                    this.emailErrors.email = data.errors?.email?.[0] || data.message;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: data.message || 'Unable to send code.', type: 'danger' }
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
                const token = document.querySelector('input[name="_token"]')?.value;
                const response = await fetch('/user/account-management/email/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ code: this.otpCode }),
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    this.email = data.email;
                    this.maskedEmail = data.masked_email;
                    this.showOtpModal = false;
                    this.activeEdit = null;

                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: data.message, type: 'success' }
                    }));
                } else {
                    this.otpError = data.message || 'Invalid verification code.';
                    this.otpCode = '';
                    const input = document.getElementById('otp_hidden_input');
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
                const token = document.querySelector('input[name="_token"]')?.value;
                const response = await fetch('/user/account-management/email/request-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ email: this.email }),
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    this.otpCode = '';
                    this.startCooldown(data.cooldown || 60);
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Notice', message: 'A new code has been sent.', type: 'info' }
                    }));
                } else {
                    this.otpError = data.message || 'Unable to resend code.';
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
                const token = document.querySelector('input[name="_token"]')?.value;
                const response = await fetch('/user/account-management/password/request-otp', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    }
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    this.passwordOtpMaskedEmail = data.masked_email;
                    this.passwordOtpCode = '';
                    this.passwordOtpError = null;
                    this.showPasswordOtpModal = true;
                    this.startPasswordCooldown(data.cooldown || 60);

                    this.$nextTick(() => {
                        document.getElementById('password_otp_hidden_input')?.focus();
                    });
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Notice', message: data.message || 'Please wait before requesting a new code.', type: 'info' }
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
                const token = document.querySelector('input[name="_token"]')?.value;
                const response = await fetch('/user/account-management/password/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ code: this.passwordOtpCode }),
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    this.passwordUnlockToken = data.unlock_token;
                    this.showPasswordOtpModal = false;
                    this.activeEdit = 'password';
                } else {
                    this.passwordOtpError = data.message || 'Invalid code.';
                    this.passwordOtpCode = '';
                    const input = document.getElementById('password_otp_hidden_input');
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
                const token = document.querySelector('input[name="_token"]')?.value;
                const response = await fetch('/user/account-management/password/request-otp', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    }
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    this.passwordOtpCode = '';
                    this.startPasswordCooldown(data.cooldown || 60);
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Notice', message: 'A new code has been sent.', type: 'info' }
                    }));
                } else {
                    this.passwordOtpError = data.message || 'Unable to resend code.';
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

                const data = await response.json();
                if (response.ok && data.success) {
                    this.passwordLastUpdated = data.formatted_time || 'Last updated a few seconds ago';
                    this.activeEdit = null;
                    this.newPassword = '';
                    this.confirmPassword = '';
                    this.passwordUnlockToken = null;

                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: data.message, type: 'success' }
                    }));
                } else if (response.status === 422 && data.errors) {
                    this.passwordErrors.password = data.errors.password?.[0] || null;
                    this.passwordErrors.password_confirmation = data.errors.password_confirmation?.[0] || null;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: data.message || 'Unable to update password.', type: 'danger' }
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