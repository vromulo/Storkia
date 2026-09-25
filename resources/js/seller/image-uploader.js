export default function imageUploader() {
    return {
        isDragging: false,
        errorMessage: '',
        previews: [],
        handleDrop(e) {
            this.isDragging = false;
            const files = e.dataTransfer ? e.dataTransfer.files : null;
            if (files) this.processFiles(files);
        },
        handleSelect(e) {
            this.processFiles(e.target.files);
        },
        processFiles(files) {
            this.errorMessage = '';
            const dt = new DataTransfer();
            let hasInvalid = false;
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (file.type && file.type.startsWith('image/')) {
                    dt.items.add(file);
                } else {
                    hasInvalid = true;
                }
            }
            if (hasInvalid) {
                this.errorMessage = 'Only image files (PNG, JPG, WEBP, GIF, SVG) are allowed. PDFs and documents were rejected.';
            }
            this.$refs.mainFileInput.files = dt.files;
            this.previews = [];
            for (let i = 0; i < dt.files.length; i++) {
                let reader = new FileReader();
                reader.onload = (e) => this.previews.push(e.target.result);
                reader.readAsDataURL(dt.files[i]);
            }
        }
    };
}