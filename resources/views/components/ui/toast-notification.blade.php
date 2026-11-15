@php
    $initialToasts = [];
    if (session('success')) {
        $initialToasts[] = [
            'title' => 'Success',
            'message' => (string) session('success'),
            'type' => 'success',
        ];
    }
    if (session('status')) {
        $initialToasts[] = [
            'title' => 'Update',
            'message' => (string) session('status'),
            'type' => 'success',
        ];
    }
    if (session('info')) {
        $initialToasts[] = [
            'title' => 'Notice',
            'message' => (string) session('info'),
            'type' => 'info',
        ];
    }
    if (session('warning')) {
        $initialToasts[] = [
            'title' => 'Attention',
            'message' => (string) session('warning'),
            'type' => 'warning',
        ];
    }
    if (session('error')) {
        $initialToasts[] = [
            'title' => 'Error',
            'message' => (string) session('error'),
            'type' => 'danger',
        ];
    }
    if (isset($errors) && $errors->any()) {
        foreach ($errors->all() as $errText) {
            $initialToasts[] = [
                'title' => 'Validation Alert',
                'message' => (string) $errText,
                'type' => 'danger',
            ];
        }
    }
@endphp

<div
    x-data="{
        toasts: [],
        counter: 0,
        init() {
            const preload = @js($initialToasts);
            preload.forEach((t, idx) => {
                setTimeout(() => {
                    this.trigger(t.title, t.message, t.type);
                }, idx * 140);
            });
        },
        trigger(eventTitle, eventMessage, eventType = 'success') {
            if (!eventMessage) return;
            const normType = ['danger', 'error'].includes(eventType)
                ? 'danger'
                : (eventType === 'success' ? 'success' : 'info');
            const id = ++this.counter;
            const duration = normType === 'danger' ? 5500 : 4200;
            this.toasts.push({
                id,
                title: eventTitle || (normType === 'danger' ? 'Alert' : (normType === 'success' ? 'Success' : 'Notice')),
                message: eventMessage,
                type: normType,
                visible: true
            });
            if (this.toasts.length > 4) {
                this.toasts.shift();
            }
            setTimeout(() => {
                this.dismiss(id);
            }, duration);
        },
        dismiss(id) {
            const idx = this.toasts.findIndex(t => t.id === id);
            if (idx !== -1) {
                this.toasts[idx].visible = false;
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 220);
            }
        }
    }"
    @toast.window="trigger($event.detail?.title, $event.detail?.message, $event.detail?.type || 'success')"
    @storkia-toast.window="trigger($event.detail?.title, $event.detail?.message, $event.detail?.type || 'success')"
    class="fixed top-5 right-5 z-[350] flex flex-col gap-2.5 max-w-sm w-[calc(100vw-2.5rem)] sm:w-96 pointer-events-none select-none"
>
    <template x-for="item in toasts" :key="item.id">
        <div
            x-show="item.visible"
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 -translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
            class="pointer-events-auto relative overflow-hidden flex items-start gap-3.5 px-4 py-3.5 bg-surface/95 backdrop-blur-md border border-border-subtle rounded-2xl shadow-2xl shadow-black/10"
        >
            <div
                class="absolute left-0 top-0 bottom-0 w-1.5"
                :class="item.type === 'success' ? 'bg-success' : (item.type === 'danger' ? 'bg-danger' : 'bg-primary')"
            ></div>

            <template x-if="item.type === 'success'">
                <div class="w-8 h-8 rounded-xl bg-success/10 text-success flex items-center justify-center shrink-0 ml-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </template>

            <template x-if="item.type === 'danger'">
                <div class="w-8 h-8 rounded-xl bg-danger/10 text-danger flex items-center justify-center shrink-0 ml-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </template>

            <template x-if="item.type === 'info'">
                <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 ml-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </template>

            <div class="flex-1 min-w-0 pr-1">
                <h4 class="text-xs font-extrabold text-primary-dark uppercase tracking-wider font-sans" x-text="item.title"></h4>
                <p class="text-xs text-text-main mt-0.5 leading-relaxed font-medium break-words" x-text="item.message"></p>
            </div>

            <button
                type="button"
                @click="dismiss(item.id)"
                class="text-text-muted hover:text-primary transition-colors p-1 rounded-lg hover:bg-surface-subtle focus:outline-none cursor-pointer shrink-0"
                aria-label="Close notification"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>