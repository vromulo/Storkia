/**
 * Alpine component for Account Management > Delete Account.
 *
 * "Delete" archives the account on the server (AccountArchiveService): nothing
 * is removed from the database, and the account appears under Admin > Account
 * Management > Archived Accounts.
 *
 * Flow:
 *   1. openModal()  -> GET eligibility. The buyer either sees why the account
 *                      cannot be deleted yet (open orders, dispute, refund) or
 *                      the confirmation form.
 *   2. submit()     -> POST reason, optional note, acknowledgement, password.
 *   3. success      -> the server has already signed the buyer out, so the page
 *                      just follows the redirect URL it returned.
 *
 * It is mounted on the Delete Account row in account-management.blade.php,
 * nested inside accountManager, so the row can still read isEditing() and the
 * isSaving* flags from the parent scope.
 */
export default function deleteAccountManager(config = {}) {
    const NOTE_MAX_LENGTH = 300;
    const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please refresh the page and sign in again.';
    const NETWORK_ERROR_MESSAGE = 'A network error occurred. Please try again.';

    return {
        // --- Config (passed in from the blade view) ---
        eligibilityUrl: config.eligibilityUrl || '',
        deleteUrl: config.deleteUrl || '',
        ordersUrl: config.ordersUrl || '',
        csrfToken: config.csrfToken || '',
        reasonOptions: Object.entries(config.reasons || {}).map(([value, label]) => ({ value, label })),
        noteMaxLength: NOTE_MAX_LENGTH,

        // --- Modal State ---
        showModal: false,
        step: 'checking', // 'checking' | 'blocked' | 'form' | 'error'
        blockers: [],
        loadError: null,
        eligibilityRequestId: 0,

        // --- Form State ---
        reason: '',
        note: '',
        acknowledged: false,
        password: '',
        showPassword: false,
        errors: { reason: null, note: null, acknowledged: null, password: null },
        formError: null,
        isDeleting: false,
        isRedirecting: false,

        // --- Helpers ---
        modalTitle() {
            if (this.step === 'blocked') return 'You can’t delete your account yet';
            if (this.step === 'error') return 'Something went wrong';
            return 'Delete your account';
        },

        token() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="_token"]')?.value
                || this.csrfToken;
        },

        headers(extra = {}) {
            return {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.token(),
                ...extra,
            };
        },

        setScrollLock(locked) {
            document.body.classList.toggle('overflow-hidden', locked);
        },

        resetForm() {
            this.step = 'checking';
            this.blockers = [];
            this.loadError = null;
            this.reason = '';
            this.note = '';
            this.acknowledged = false;
            this.password = '';
            this.showPassword = false;
            this.errors = { reason: null, note: null, acknowledged: null, password: null };
            this.formError = null;
        },

        // A blocked account (for example archived from another device) comes
        // back from EnsureAccountActive as a 403 that carries a redirect URL.
        redirectIfAccountBlocked(response, data) {
            if (response.status === 403 && data.redirect) {
                this.isRedirecting = true;
                window.location.assign(data.redirect);
                return true;
            }

            return false;
        },

        // --- Modal Open / Close ---
        openModal() {
            if (this.isDeleting) return;

            this.resetForm();
            this.showModal = true;
            this.setScrollLock(true);
            this.checkEligibility();
        },

        closeModal() {
            // Closing mid-request would hide a deletion that is still running.
            if (this.isDeleting) return;

            this.showModal = false;
            this.eligibilityRequestId++; // ignore an eligibility check that is still in flight
            this.password = '';
            this.showPassword = false;
            this.setScrollLock(false);
        },

        // --- Step 1: Eligibility ---
        async checkEligibility() {
            const requestId = ++this.eligibilityRequestId;
            this.step = 'checking';
            this.loadError = null;

            try {
                const response = await fetch(this.eligibilityUrl, {
                    method: 'GET',
                    headers: this.headers(),
                });

                const data = await response.json().catch(() => ({}));

                if (requestId !== this.eligibilityRequestId || !this.showModal) return;
                if (this.redirectIfAccountBlocked(response, data)) return;

                if (response.ok && data.success) {
                    this.blockers = Array.isArray(data.blockers) ? data.blockers : [];
                    this.step = data.can_delete ? 'form' : 'blocked';

                    if (this.step === 'form') {
                        this.$nextTick(() => {
                            document.querySelector('#delete_account_form input[type="radio"]')?.focus();
                        });
                    }
                    return;
                }

                this.loadError = (response.status === 401 || response.status === 419)
                    ? SESSION_EXPIRED_MESSAGE
                    : (data.message || 'We couldn’t check your account right now. Please try again.');
                this.step = 'error';
            } catch (err) {
                console.error(err);

                if (requestId !== this.eligibilityRequestId || !this.showModal) return;

                this.loadError = NETWORK_ERROR_MESSAGE;
                this.step = 'error';
            }
        },

        // --- Step 2: Confirmation Form ---
        onNoteInput(event) {
            this.note = event.target.value.slice(0, this.noteMaxLength);
            event.target.value = this.note;
            this.errors.note = null;
        },

        validate() {
            this.errors.note = null;
            this.errors.reason = this.reason ? null : 'Please tell us why you are leaving.';
            this.errors.acknowledged = this.acknowledged ? null : 'Please confirm that you understand before continuing.';
            this.errors.password = this.password ? null : 'Please enter your password to confirm.';

            return !this.errors.reason && !this.errors.acknowledged && !this.errors.password;
        },

        applyServerErrors(data) {
            const fields = data.errors || {};

            ['reason', 'note', 'acknowledged', 'password'].forEach((field) => {
                this.errors[field] = fields[field]?.[0] || null;
            });

            if (!Object.values(this.errors).some(Boolean)) {
                this.formError = data.message || 'Please check your details and try again.';
            }
        },

        async submit() {
            if (this.isDeleting) return;

            this.formError = null;
            if (!this.validate()) return;

            this.isDeleting = true;

            try {
                const response = await fetch(this.deleteUrl, {
                    method: 'POST',
                    headers: this.headers({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        reason: this.reason,
                        note: this.note.trim() || null,
                        acknowledged: this.acknowledged,
                        password: this.password,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok && data.success) {
                    // The server has signed the buyer out; there is nothing left to do here.
                    this.password = '';
                    this.isRedirecting = true;
                    window.location.assign(data.redirect || '/');
                    return;
                }

                if (this.redirectIfAccountBlocked(response, data)) return;

                if (response.status === 419 || response.status === 401) {
                    this.formError = SESSION_EXPIRED_MESSAGE;
                    return;
                }

                // An order, dispute or refund appeared after the modal was opened.
                if (response.status === 422 && Array.isArray(data.blockers) && data.blockers.length > 0) {
                    this.password = '';
                    this.blockers = data.blockers;
                    this.step = 'blocked';
                    return;
                }

                if (response.status === 422) {
                    this.applyServerErrors(data);
                } else if (response.status === 429) {
                    this.errors.password = data.message || 'Too many attempts. Please try again later.';
                } else {
                    this.formError = data.message || 'We couldn’t delete your account. Please try again.';
                }

                if (this.errors.password) {
                    this.password = '';
                    this.showPassword = false;
                    this.$nextTick(() => document.getElementById('delete_account_password')?.focus());
                }
            } catch (err) {
                console.error(err);
                this.formError = NETWORK_ERROR_MESSAGE;
            } finally {
                // Keep the button in its loading state while the browser navigates away.
                if (!this.isRedirecting) this.isDeleting = false;
            }
        }
    };
}