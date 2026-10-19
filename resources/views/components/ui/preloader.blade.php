<div 
    x-data="{ showPreloader: true }" 
    x-init="
        if (document.readyState === 'complete') {
            showPreloader = false;
        } else {
            window.addEventListener('load', () => showPreloader = false);
        }
    "
    x-show="showPreloader"
    x-transition:leave="transition ease-in-out duration-500"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-white"
>
    <style>
        @keyframes fillUp {
            0% {
                clip-path: inset(100% 0 0 0);
                opacity: 0.2;
            }
            50% {
                clip-path: inset(50% 0 0 0);
                opacity: 0.6;
            }
            100% {
                clip-path: inset(0 0 0 0);
                opacity: 1;
            }
        }
        .animate-logo-fill {
            animation: fillUp 1.5s cubic-bezier(0.4, 0, 0.2, 1) infinite alternate;
        }
    </style>

    <div class="relative w-24 h-24 md:w-32 md:h-32 flex items-center justify-center">
        <img 
            src="{{ asset('assets/pink-storkia-minimized.webp') }}" 
            alt="{{ config('app.name') }}" 
            class="absolute inset-0 w-full h-full object-contain opacity-20"
        />
        <img 
            src="{{ asset('assets/pink-storkia-minimized.webp') }}" 
            alt="{{ config('app.name') }}" 
            class="absolute inset-0 w-full h-full object-contain animate-logo-fill"
        />
    </div>
</div>