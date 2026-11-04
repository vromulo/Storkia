export default function imageUploader(maxImages = 10) {
    return {
        isDragging: false,
        maxImages: maxImages,
        files: [],
        previews: [],
        errorMessage: '',
        draggingIndex: null,

        handleSelect(event) {
            const selectedFiles = Array.from(event.target.files || []);
            this.appendFiles(selectedFiles);
        },

        handleDrop(event) {
            this.isDragging = false;
            const droppedFiles = Array.from(event.dataTransfer?.files || []);
            this.appendFiles(droppedFiles);
        },

        appendFiles(incomingFiles) {
            this.errorMessage = '';
            if (!incomingFiles.length) {
                this.syncInputFiles();
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            const maxFileSize = 5 * 1024 * 1024;

            for (const file of incomingFiles) {
                if (this.files.length >= this.maxImages) {
                    this.errorMessage = `Maximum of ${this.maxImages} main product pictures allowed. Remove an image if you wish to replace it.`;
                    break;
                }

                if (!allowedTypes.includes(file.type)) {
                    this.errorMessage = `"${file.name}" was skipped. Only JPG, PNG, and WEBP images are allowed.`;
                    continue;
                }

                if (file.size > maxFileSize) {
                    this.errorMessage = `"${file.name}" exceeds the 5MB per-image limit.`;
                    continue;
                }

                const isDuplicate = this.files.some(
                    existing => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified
                );
                if (isDuplicate) {
                    continue;
                }

                this.files.push(file);
                const objectUrl = URL.createObjectURL(file);
                this.previews.push(objectUrl);
            }

            this.syncInputFiles();
        },

        removeImage(index) {
            if (index < 0 || index >= this.files.length) return;
            this.errorMessage = '';

            const removedPreview = this.previews[index];
            if (removedPreview && removedPreview.startsWith('blob:')) {
                URL.revokeObjectURL(removedPreview);
            }

            this.files.splice(index, 1);
            this.previews.splice(index, 1);
            this.syncInputFiles();
        },

        promoteToCover(index) {
            if (index <= 0 || index >= this.files.length) return;
            const [file] = this.files.splice(index, 1);
            const [preview] = this.previews.splice(index, 1);
            this.files.unshift(file);
            this.previews.unshift(preview);
            this.syncInputFiles();
        },

        moveImage(fromIndex, toIndex) {
            if (
                fromIndex === toIndex ||
                fromIndex < 0 ||
                toIndex < 0 ||
                fromIndex >= this.files.length ||
                toIndex >= this.files.length
            ) {
                return;
            }

            const [movedFile] = this.files.splice(fromIndex, 1);
            const [movedPreview] = this.previews.splice(fromIndex, 1);
            this.files.splice(toIndex, 0, movedFile);
            this.previews.splice(toIndex, 0, movedPreview);
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

        syncInputFiles() {
            const input = this.$refs.mainFileInput;
            if (!input || typeof DataTransfer === 'undefined') return;

            const dt = new DataTransfer();
            this.files.forEach(file => dt.items.add(file));
            input.files = dt.files;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };
}