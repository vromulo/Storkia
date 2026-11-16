export default function imageUploader(maxImages = 10, initialBase64List = []) {
    return {
        isDragging: false,
        isProcessing: false,
        maxImages: maxImages,
        files: [],
        previews: [],
        base64Images: [],
        errorMessage: '',
        draggingIndex: null,

        init() {
            if (Array.isArray(initialBase64List) && initialBase64List.length > 0) {
                initialBase64List.slice(0, this.maxImages).forEach((b64, idx) => {
                    if (typeof b64 === 'string' && b64.startsWith('data:image/')) {
                        this.files.push({
                            name: `restored_${idx + 1}.webp`,
                            size: b64.length,
                            lastModified: Date.now() + idx
                        });
                        this.previews.push(b64);
                        this.base64Images.push(b64);
                    }
                });
            }
            this.$nextTick(() => {
                this.notifyCountChanged();
            });
        },

        notifyError(msg) {
            if (!msg) return;
            if (window.StorkiaLive && typeof window.StorkiaLive.showToast === 'function') {
                window.StorkiaLive.showToast(msg, 'error');
            } else {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { title: 'Upload Error', message: msg, type: 'danger' }
                }));
            }
        },

        handleSelect(event) {
            const selectedFiles = Array.from(event.target.files || []);
            this.appendFiles(selectedFiles);
        },

        handleDrop(event) {
            this.isDragging = false;
            const droppedFiles = Array.from(event.dataTransfer?.files || []);
            this.appendFiles(droppedFiles);
        },

        compressToWebpDataUrl(file) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const dataUrl = e.target.result;
                    const img = new Image();
                    img.onload = () => {
                        try {
                            const maxDim = 1600;
                            let width = img.width;
                            let height = img.height;
                            if (width > maxDim || height > maxDim) {
                                if (width > height) {
                                    height = Math.round((height * maxDim) / width);
                                    width = maxDim;
                                } else {
                                    width = Math.round((width * maxDim) / height);
                                    height = maxDim;
                                }
                            }
                            const canvas = document.createElement('canvas');
                            canvas.width = width;
                            canvas.height = height;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, width, height);
                            const webpUrl = canvas.toDataURL('image/webp', 0.84);
                            resolve(webpUrl && webpUrl.startsWith('data:image/') ? webpUrl : dataUrl);
                        } catch (_) {
                            resolve(dataUrl);
                        }
                    };
                    img.onerror = () => resolve(dataUrl);
                    img.src = dataUrl;
                };
                reader.onerror = () => resolve('');
                reader.readAsDataURL(file);
            });
        },

        async appendFiles(incomingFiles) {
            this.errorMessage = '';
            if (!incomingFiles.length) {
                this.syncInputFiles();
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            const maxFileSize = 5 * 1024 * 1024;
            this.isProcessing = true;
            this.notifyCountChanged();

            for (const file of incomingFiles) {
                if (this.previews.length >= this.maxImages) {
                    this.errorMessage = `Maximum of ${this.maxImages} main product pictures allowed. Remove an image if you wish to replace it.`;
                    this.notifyError(this.errorMessage);
                    break;
                }

                if (!allowedTypes.includes((file.type || '').toLowerCase())) {
                    this.errorMessage = `"${file.name}" was skipped. Only JPG, PNG, and WEBP images are allowed.`;
                    this.notifyError(this.errorMessage);
                    continue;
                }

                if (file.size > maxFileSize) {
                    this.errorMessage = `"${file.name}" exceeds the 5MB per-image limit.`;
                    this.notifyError(this.errorMessage);
                    continue;
                }

                const isDuplicate = this.files.some(
                    (existing) => existing && existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified
                );
                if (isDuplicate) {
                    continue;
                }

                const webpDataUri = await this.compressToWebpDataUrl(file);
                if (!webpDataUri) {
                    this.errorMessage = `Could not process "${file.name}". Please try another image.`;
                    this.notifyError(this.errorMessage);
                    continue;
                }

                this.files.push({
                    name: file.name,
                    size: file.size,
                    lastModified: file.lastModified
                });
                this.previews.push(webpDataUri);
                this.base64Images.push(webpDataUri);
            }

            this.isProcessing = false;
            this.syncInputFiles();
        },

        removeImage(index) {
            if (index < 0 || index >= this.previews.length) return;
            this.errorMessage = '';

            const removedPreview = this.previews[index];
            if (removedPreview && removedPreview.startsWith('blob:')) {
                URL.revokeObjectURL(removedPreview);
            }

            this.files.splice(index, 1);
            this.previews.splice(index, 1);
            this.base64Images.splice(index, 1);
            this.syncInputFiles();
        },

        promoteToCover(index) {
            if (index <= 0 || index >= this.previews.length) return;
            const [file] = this.files.splice(index, 1);
            const [preview] = this.previews.splice(index, 1);
            const [b64] = this.base64Images.splice(index, 1);
            this.files.unshift(file);
            this.previews.unshift(preview);
            this.base64Images.unshift(b64);
            this.syncInputFiles();
        },

        moveImage(fromIndex, toIndex) {
            if (
                fromIndex === toIndex ||
                fromIndex < 0 ||
                toIndex < 0 ||
                fromIndex >= this.previews.length ||
                toIndex >= this.previews.length
            ) {
                return;
            }

            const [movedFile] = this.files.splice(fromIndex, 1);
            const [movedPreview] = this.previews.splice(fromIndex, 1);
            const [movedB64] = this.base64Images.splice(fromIndex, 1);
            this.files.splice(toIndex, 0, movedFile);
            this.previews.splice(toIndex, 0, movedPreview);
            this.base64Images.splice(toIndex, 0, movedB64);
            this.syncInputFiles();
        },

        onThumbnailDragStart(index) {
            this.draggingIndex = index;
        },

        onThumbnailDrop(targetIndex) {
            if (this.draggingIndex === null) return;
            this.moveImage(this.draggingIndex, targetIndex);
            this.draggingIndex = null;
        },

        notifyCountChanged() {
            this.$dispatch('main-pictures-changed', {
                count: this.previews.length,
                processing: this.isProcessing
            });
        },

        syncInputFiles() {
            const input = this.$refs.mainFileInput;
            if (input) {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            this.notifyCountChanged();
        }
    };
}