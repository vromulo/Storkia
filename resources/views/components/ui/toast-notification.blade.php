<div 
    x-data="{
        show: false,
        title: '',
        message: '',
        type: 'success',
        timeout: null,
        trigger(eventTitle, eventMessage, eventType = 'success') {
            this.title = eventTitle;
            this.message = eventMessage;
            this.type = eventType;
            this.show = true;
            clearTimeout(this.timeout);
            this.timeout = setTimeout(() => {
                this.show = false;
            }, 4500);
        }
    }"
    x-init="
        @if (session('success'))
            trigger('Success', '{{ session('success') }}', 'success');
        @elseif (session('info'))
            trigger('Notice', '{{ session('info') }}', 'info');
        @elseif (session('error'))
            trigger('Error', '{{ session('error') }}', 'danger');
        @endif
    "
    @toast.window="trigger($event.detail.title, $event.detail.message, $event.detail.type || 'success')"
    x-show="show"
    x-cloak
    x-transition:enter="transition ease-out duration-300 transform"
    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:translate-x-4 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0 scale-100"
    x-transition:leave="transition ease-in duration-200 transform"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 translate-y-2 scale-95"
    class="fixed bottom-6 right-6 z-[100] max-w-sm w-[calc(100vw-3rem)] sm:w-auto cursor-default"
>
    <!-- Toast Island -->
    <div class="flex items-center gap-3.5 px-4 py-3.5 bg-surface border border-border-subtle rounded-2xl shadow-xl shadow-black/5 backdrop-blur-md">
        <!-- Success Icon -->
        <template x-if="type === 'success'">
            <div class="w-8 h-8 rounded-full bg-success/10 text-success flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>
        </template>

        <!-- Info Icon (Used for Logout) -->
        <template x-if="type === 'info'">
            <div class="w-8 h-8 rounded-full bg-info/10 text-info flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </template>

        <!-- Content -->
        <div class="flex-1 pr-2">
            <h4 class="text-xs font-bold text-primary-dark uppercase tracking-wider font-sans" x-text="title"></h4>
            <p class="text-xs text-text-muted mt-0.5 leading-snug font-sans" x-text="message"></p>
        </div>

        <!-- Dismiss -->
        <button 
            type="button" 
            @click="show = false" 
            class="text-text-muted/60 hover:text-text-main transition-colors p-1 rounded-lg focus:outline-none cursor-pointer"
            aria-label="Close notification"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>