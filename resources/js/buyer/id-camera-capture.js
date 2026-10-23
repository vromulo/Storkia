export default function idCameraCapture(requiresBack = true, wire = null) {
    return {
        step: 'front',
        requiresBack: Boolean(requiresBack),
        stream: null,
        cameraError: null,
        isUploading: false,

        init() {
            this.initCamera();
        },

        destroy() {
            this.stopCamera();
        },

        get wire() {
            return this.$wire || wire;
        },

        initCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.cameraError = 'Camera access requires a secure connection (HTTPS or localhost). Please upload an existing photo instead.';
                return;
            }

            navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment', width: { ideal: 1280 } },
                audio: false
            })
            .then((s) => {
                this.stream = s;
                if (this.$refs.videoEl) {
                    this.$refs.videoEl.srcObject = s;
                }
            })
            .catch((err) => {
                this.cameraError = 'Unable to access camera: ' + (err.message || 'Permission denied.');
            });
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
        },

        cancel() {
            this.stopCamera();
            if (this.wire) {
                this.wire.cancelCamera();
            }
        },

        tryUpload() {
            this.stopCamera();
            if (this.wire) {
                this.wire.selectMethod('upload');
            }
        },

        capture() {
            if (this.isUploading) return;

            const video = this.$refs.videoEl;
            if (!video || !video.videoWidth || !video.videoHeight) {
                alert('Camera feed is not ready yet. Please wait a moment.');
                return;
            }

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);

            this.isUploading = true;

            canvas.toBlob((blob) => {
                if (!blob) {
                    this.isUploading = false;
                    return;
                }

                const file = new File([blob], `${this.step}_capture.jpg`, { type: 'image/jpeg' });
                const wireInstance = this.wire;

                if (!wireInstance) {
                    this.isUploading = false;
                    alert('Livewire instance not found. Please refresh and try again.');
                    return;
                }

                if (this.step === 'front') {
                    wireInstance.upload('front_image', file, () => {
                        this.isUploading = false;
                        if (this.requiresBack) {
                            // Seamless transition to back step without closing stream
                            this.step = 'back';
                        } else {
                            this.stopCamera();
                            wireInstance.proceedToReview();
                        }
                    }, () => {
                        this.isUploading = false;
                        alert('Failed to upload front image. Please try again.');
                    });
                } else {
                    wireInstance.upload('back_image', file, () => {
                        this.isUploading = false;
                        this.stopCamera();
                        wireInstance.proceedToReview();
                    }, () => {
                        this.isUploading = false;
                        alert('Failed to upload back image. Please try again.');
                    });
                }
            }, 'image/jpeg', 0.95);
        }
    };
}