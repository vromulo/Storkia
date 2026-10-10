export default function accountManager(initialEdit = null, userData = {}) {
    return {
        activeEdit: initialEdit,

        // ----------------------------------------------------
        // Name State
        // ----------------------------------------------------
        isSavingName: false,
        firstName: userData.first_name || '',
        lastName: userData.last_name || '',
        fullName: userData.full_name || '',
        nameErrors: { first_name: null, last_name: null },

        // ----------------------------------------------------
        // Email State
        // ----------------------------------------------------
        isSavingEmail: false,
        email: userData.email || '',
        maskedEmail: userData.masked_email || '',
        emailErrors: { email: null },

        // Email OTP Modal State
        showOtpModal: false,
        otpCode: '',
        otpMaskedEmail: '',
        isVerifyingOtp: false,
        isResendingOtp: false,
        otpError: null,
        resendCooldown: 0,
        timerInterval: null,

        // ----------------------------------------------------
        // Password State
        // ----------------------------------------------------
        isSendingPasswordCode: false,
        isSavingPassword: false,
        passwordLastUpdated: userData.password_last_updated || 'Never updated',
        newPassword: '',
        confirmPassword: '',
        passwordUnlockToken: null,
        passwordErrors: { password: null, password_confirmation: null },

        // Password OTP Modal State
        showPasswordOtpModal: false,
        passwordOtpCode: '',
        passwordOtpMaskedEmail: '',
        isVerifyingPasswordOtp: false,
        isResendingPasswordOtp: false,
        passwordOtpError: null,
        passwordResendCooldown: 0,
        passwordTimerInterval: null,

        // ----------------------------------------------------
        // Toggle & View State Helpers
        // ----------------------------------------------------
        toggle(field) {
            this.activeEdit = (this.activeEdit === field) ? null : field;
            if (this.activeEdit !== 'username') {
                this.nameErrors.first_name = null;
                this.nameErrors.last_name = null;
            }
            if (this.activeEdit !== 'email') {
                this.emailErrors.email = null;
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

        getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="_token"]')?.value
                || '';
        },

        // ----------------------------------------------------
        // Full Name Actions
        // ----------------------------------------------------
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
                        'X-CSRF-TOKEN': this.getCsrfToken() || formData.get('_token'),
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
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { title: 'Error', message: 'A network error occurred.', type: 'danger' }
                }));
            } finally {
                this.isSavingName = false;
            }
        },

        // ----------------------------------------------------
        // Email Actions & OTP
        // ----------------------------------------------------
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
                        'X-CSRF-TOKEN': this.getCsrfToken() || formData.get('_token'),
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
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { title: 'Error', message: 'A network error occurred.', type: 'danger' }
                }));
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
                const response = await fetch('/user/account-management/email/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
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
                const response = await fetch('/user/account-management/email/request-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
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

        // ----------------------------------------------------
        // Change Password Workflow
        // ----------------------------------------------------
        async handlePasswordClick() {
            if (this.activeEdit === 'password') {
                this.activeEdit = null;
                this.newPassword = '';
                this.confirmPassword = '';
                this.passwordErrors = { password: null, password_confirmation: null };
                return;
            }

            if (this.passwordUnlockToken) {
                this.activeEdit = 'password';
                return;
            }

            await this.requestPasswordOtp();
        },

        startPasswordCooldown(seconds) {
            this.passwordResendCooldown = seconds;
            if (this.passwordTimerInterval) clearInterval(this.passwordTimerInterval);
            this.passwordTimerInterval = setInterval(() => {
                if (this.passwordResendCooldown > 0) {
                    this.passwordResendCooldown--;
                } else {
                    clearInterval(this.passwordTimerInterval);
                }
            }, 1000);
        },

        async requestPasswordOtp() {
            if (this.isSendingPasswordCode) return;

            this.isSendingPasswordCode = true;
            this.passwordOtpError = null;

            try {
                const response = await fetch('/user/account-management/password/request-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: data.message || 'Unable to send code.', type: 'danger' }
                    }));
                    return;
                }

                this.passwordOtpMaskedEmail = data.masked_email;
                this.passwordOtpCode = '';
                this.showPasswordOtpModal = true;
                this.startPasswordCooldown(data.cooldown || 60);

                this.$nextTick(() => {
                    const el = document.getElementById('password_otp_hidden_input');
                    if (el) el.focus();
                });
            } catch (err) {
                console.error(err);
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { title: 'Error', message: 'Network error. Please try again.', type: 'danger' }
                }));
            } finally {
                this.isSendingPasswordCode = false;
            }
        },

        closePasswordOtpModal() {
            this.showPasswordOtpModal = false;
            this.passwordOtpCode = '';
            this.passwordOtpError = null;
        },

        async resendPasswordOtp() {
            if (this.passwordResendCooldown > 0 || this.isResendingPasswordOtp) return;
            this.isResendingPasswordOtp = true;
            this.passwordOtpError = null;

            try {
                const response = await fetch('/user/account-management/password/request-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    }
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.passwordOtpCode = '';
                    const el = document.getElementById('password_otp_hidden_input');
                    if (el) {
                        el.value = '';
                        el.focus();
                    }
                    this.startPasswordCooldown(data.cooldown || 60);
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Notice', message: 'A new code has been sent.', type: 'info' }
                    }));
                } else {
                    this.passwordOtpError = data.message || 'Failed to resend code.';
                }
            } catch (err) {
                console.error(err);
                this.passwordOtpError = 'Network error while resending.';
            } finally {
                this.isResendingPasswordOtp = false;
            }
        },

        onPasswordOtpInput(event) {
            this.passwordOtpCode = event.target.value.replace(/\D/g, '').slice(0, 6);
            event.target.value = this.passwordOtpCode;
            this.passwordOtpError = null;

            if (this.passwordOtpCode.length === 6 && !this.isVerifyingPasswordOtp) {
                this.verifyPasswordOtp();
            }
        },

        async verifyPasswordOtp() {
            if (this.isVerifyingPasswordOtp) return;
            this.isVerifyingPasswordOtp = true;
            this.passwordOtpError = null;

            try {
                const response = await fetch('/user/account-management/password/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    },
                    body: JSON.stringify({ code: this.passwordOtpCode }),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    this.passwordOtpError = data.message || 'Verification failed.';
                    this.passwordOtpCode = '';
                    const el = document.getElementById('password_otp_hidden_input');
                    if (el) {
                        el.value = '';
                        el.focus();
                    }
                    return;
                }

                this.passwordUnlockToken = data.unlock_token;
                this.closePasswordOtpModal();
                this.activeEdit = 'password';

                this.$nextTick(() => {
                    const el = document.getElementById('new_password');
                    if (el) el.focus();
                });
            } catch (err) {
                console.error(err);
                this.passwordOtpError = 'Network error during verification.';
            } finally {
                this.isVerifyingPasswordOtp = false;
            }
        },

        validatePasswordClientSide() {
            this.passwordErrors = { password: null, password_confirmation: null };

            if (this.newPassword && this.newPassword.length < 8) {
                this.passwordErrors.password = 'Password must be at least 8 characters long.';
            }

            if (this.confirmPassword && this.newPassword !== this.confirmPassword) {
                this.passwordErrors.password_confirmation = 'The password confirmation does not match.';
            }
        },

        async submitPassword(event) {
            this.validatePasswordClientSide();
            if (this.passwordErrors.password || this.passwordErrors.password_confirmation) {
                return;
            }

            this.isSavingPassword = true;
            const form = event.target;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                    },
                    body: JSON.stringify({
                        _method: 'PATCH',
                        unlock_token: this.passwordUnlockToken,
                        password: this.newPassword,
                        password_confirmation: this.confirmPassword,
                    }),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    if (data.errors) {
                        this.passwordErrors.password = data.errors.password?.[0] || null;
                        this.passwordErrors.password_confirmation = data.errors.password_confirmation?.[0] || null;
                    } else {
                        window.dispatchEvent(new CustomEvent('toast', {
                            detail: { title: 'Error', message: data.message || 'Unable to update password.', type: 'danger' }
                        }));
                    }
                    return;
                }

                this.passwordLastUpdated = 'Last updated a few seconds ago';
                this.activeEdit = null;
                this.newPassword = '';
                this.confirmPassword = '';
                this.passwordUnlockToken = null;

                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { title: 'Success', message: data.message || 'Password updated successfully.', type: 'success' }
                }));
            } catch (err) {
                console.error(err);
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { title: 'Error', message: 'Network error. Please try again.', type: 'danger' }
                }));
            } finally {
                this.isSavingPassword = false;
            }
        }
    };
}