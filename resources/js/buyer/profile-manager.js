export default function profileManager(initialEdit = null, data = {}) {
    return {
        activeEdit: initialEdit,

        // --- Avatar State ---
        avatarUrl: data.avatar_url || '',
        isUploadingAvatar: false,
        isRemovingAvatar: false,
        avatarError: null,

        // --- Username State ---
        username: data.username || '',
        usernameInput: data.username || '',
        canChangeUsername: Boolean(data.can_change),
        cooldownText: data.cooldown_text || '',
        isSavingUsername: false,
        usernameError: null,

        toggle(field) {
            this.activeEdit = this.activeEdit === field ? null : field;
            if (this.activeEdit === 'username') {
                this.usernameInput = this.username;
                this.usernameError = null;
            }
        },

        isEditing() {
            return this.activeEdit !== null;
        },

        isDimmed(field) {
            return this.isEditing() && this.activeEdit !== field;
        },

        handleAvatarSelect(event) {
            const file = event.target?.files?.[0];
            if (file) {
                this.uploadAvatarFile(file);
            }
            event.target.value = '';
        },

        async uploadAvatarFile(file) {
            this.avatarError = null;

            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                this.avatarError = 'Please select a valid JPG, PNG, or WEBP image.';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                this.avatarError = 'Avatar size must not exceed 5MB.';
                return;
            }

            this.isUploadingAvatar = true;
            const formData = new FormData();
            formData.append('avatar', file);

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                document.querySelector('input[name="_token"]')?.value;

            try {
                const response = await fetch('/user/profile/avatar', {
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
                    this.avatarUrl = resData.avatar_url;
                    window.dispatchEvent(new CustomEvent('buyer-avatar-updated', {
                        detail: { avatar_url: resData.avatar_url }
                    }));
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: resData.message, type: 'success' }
                    }));
                } else if (response.status === 422) {
                    this.avatarError = resData.errors?.avatar?.[0] || resData.message;
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Error', message: resData.message || 'Unable to upload avatar.', type: 'danger' }
                    }));
                }
            } catch (err) {
                console.error(err);
                this.avatarError = 'A network error occurred while uploading.';
            } finally {
                this.isUploadingAvatar = false;
            }
        },

        async removeAvatar() {
            if (!this.avatarUrl || this.isRemovingAvatar) return;

            this.isRemovingAvatar = true;
            this.avatarError = null;

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                document.querySelector('input[name="_token"]')?.value;

            try {
                const response = await fetch('/user/profile/avatar', {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                });

                const resData = await response.json();
                if (response.ok && resData.success) {
                    this.avatarUrl = '';
                    window.dispatchEvent(new CustomEvent('buyer-avatar-updated', {
                        detail: { avatar_url: '' }
                    }));
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { title: 'Success', message: resData.message, type: 'info' }
                    }));
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.isRemovingAvatar = false;
            }
        },

        async submitUsername(event) {
            const form = event.target;
            const formData = new FormData(form);

            this.isSavingUsername = true;
            this.usernameError = null;

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || formData.get('_token');

            try {
                const response = await fetch(form.action, {
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
                    this.username = resData.username;
                    this.usernameInput = resData.username;
                    this.canChangeUsername = Boolean(resData.can_change);
                    this.cooldownText = resData.cooldown_text || '';
                    this.activeEdit = null;

                    window.dispatchEvent(new CustomEvent('buyer-username-updated', {
                        detail: { username: resData.username }
                    }));

                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: {
                            title: 'Success',
                            message: resData.message || 'Username updated successfully.',
                            type: 'success'
                        }
                    }));
                } else if (response.status === 422) {
                    if (resData.errors?.username) {
                        this.usernameError = resData.errors.username[0];
                    } else {
                        this.usernameError = resData.message;
                    }
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: {
                            title: 'Error',
                            message: resData.message || 'Unable to update username.',
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
                this.isSavingUsername = false;
            }
        }
    };
}

// Global exposure so Alpine can always resolve x-data="profileManager(...)"
window.profileManager = profileManager;