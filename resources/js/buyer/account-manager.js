export default function accountManager(initialEdit = null, userData = {}) {
    return {
        activeEdit: initialEdit,

        // Name State
        isSavingName: false,
        firstName: userData.first_name || '',
        lastName: userData.last_name || '',
        fullName: userData.full_name || '',
        nameErrors: { first_name: null, last_name: null },

        // Email State
        isSavingEmail: false,
        email: userData.email || '',
        maskedEmail: userData.masked_email || '',
        emailErrors: { email: null },

        // OTP Modal State
        showOtpModal: false,
        otpCode: '',
        otpMaskedEmail: '',
        isVerifyingOtp: false,
        isResendingOtp: false,
        otpError: null,
        resendCooldown: 0,
        timerInterval: null,

        toggle(field) {
            this.activeEdit = (this.activeEdit === field) ? null : field;
            if (this.activeEdit !== 'username') {
                this.nameErrors.first_name = null;
                this.nameErrors.last_name = null;
            }
            if (this.activeEdit !== 'email') {
                this.emailErrors.email = null;
            }
        },

        isEditing() {
            return this.activeEdit !== null;
        },

        isDimmed(field) {
            return this.isEditing() && this.activeEdit !== field;
        },

        startCooldown(seconds) {
            this.resendCooldown = seconds;
            if (this.timerInterval) clearInterval(this.timerInterval);
            this.timerInterval = setInterval(() => {
                if (this.resendCooldown > 0) {
                    this.resendCooldown--;
                } else {
                    clearInterval(this.timerInterval);
                }
            }, 1000);
        },

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
                        const input = document.getElementById('otp_hidden_input');
                        if (input) input.focus();
                    });
                } else if (response.status === 422) {
                    if (data.errors?.email) {
                        this.emailErrors.email = data.errors.email[0];
                    } else if (data.message) {
                        this.emailErrors.email = data.message;
                    }
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

            if (this.otpCode.length === 6 && !this.isVerifyingOtp) {
                this.verifyOtp();
            }
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
                    if (input) {
                        input.value = '';
                        input.focus();
                    }
                }
            } catch (err) {
                console.error(err);
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
        }
    };
}