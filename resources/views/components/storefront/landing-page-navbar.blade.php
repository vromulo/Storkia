@php
    $navLinks = [
        ['id' => 'shops',     'label' => 'Stores'],
        ['id' => 'products',  'label' => 'Products'],
        ['id' => 'logistics', 'label' => 'Logistics'],
        ['id' => 'about',     'label' => 'About Us'],
    ];
@endphp

<div x-data="{
        mobileMenuOpen: false,
        scrolled: false,
        active: '',
        ind: { x: 0, w: 0 },
        placed: false,
        moveIndicator() {
            const pill = this.$refs.pill;
            if (!pill || !this.active) return;
            const link = pill.querySelector('[data-nav=' + this.active + ']');
            if (!link || !link.offsetWidth) return;
            this.ind = { x: link.offsetLeft, w: link.offsetWidth };
            // first placement jumps into position; later ones glide
            if (!this.placed) requestAnimationFrame(() => requestAnimationFrame(() => { this.placed = true; }));
        },
        init() {
            this.scrolled = window.scrollY > 16;

            // Scrollspy: highlight the link of the section crossing the middle of the viewport
            const ids = @js(array_column($navLinks, 'id'));
            const io = new IntersectionObserver((entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) this.active = e.target.id;
                    else if (this.active === e.target.id) this.active = '';
                });
            }, { rootMargin: '-45% 0px -50% 0px' });
            ids.forEach((id) => { const el = document.getElementById(id); if (el) io.observe(el); });

            // Glide the highlight to the newly active chip
            this.$watch('active', () => this.moveIndicator());
            if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => this.moveIndicator());
        }
     }"
     @scroll.window.passive="scrolled = window.scrollY > 16"
     @resize.window="if (window.innerWidth >= 768) mobileMenuOpen = false; moveIndicator()"
     @keydown.escape.window="mobileMenuOpen = false"
     @click.outside="mobileMenuOpen = false"
     :class="scrolled
        ? 'bg-surface/75 shadow-[inset_0_-1px_0_0_rgba(231,229,228,0.6),0_8px_24px_-12px_rgba(0,0,0,0.12)]'
        : 'bg-surface/40'"
     class="fixed top-0 inset-x-0 z-50 w-full backdrop-blur-lg transition-[background-color,box-shadow] duration-300">

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-12">
        <div class="flex justify-between items-center gap-2 sm:gap-3 h-20">

            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
                <a href="{{ route('landing') }}" aria-label="Storkia home" class="flex items-center text-primary hover:opacity-80 transition-opacity rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">
                    <img src="{{ asset('assets/pink-storkia-minimized.webp') }}" alt="Storkia" class="block md:hidden h-8 w-auto object-contain">
                    <img src="{{ asset('assets/pink-storkia-maximized.webp') }}" alt="Storkia" class="hidden md:block h-9 w-auto object-contain">
                </a>
            </div>

            <!-- Desktop navigation pill (md and up) -->
            <nav x-ref="pill" aria-label="Primary" class="hidden md:flex relative items-center gap-1 bg-surface-subtle/40 p-1.5 rounded-full border border-border-subtle/30 backdrop-blur-xs">
                <!-- Sliding highlight: glides and resizes to whichever chip is active -->
                <span aria-hidden="true"
                      class="pointer-events-none absolute left-0 top-1.5 bottom-1.5 rounded-full bg-primary/10 shadow-[inset_0_0_0_1px_rgba(207,65,115,0.12)] motion-reduce:transition-none"
                      :class="[active ? 'opacity-100' : 'opacity-0', placed ? 'transition-[transform,width,opacity] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]' : '']"
                      :style="'width:' + ind.w + 'px; transform:translateX(' + ind.x + 'px)'"></span>

                @foreach ($navLinks as $link)
                    <a href="#{{ $link['id'] }}"
                       data-nav="{{ $link['id'] }}"
                       :aria-current="active === '{{ $link['id'] }}' ? 'location' : null"
                       :class="active === '{{ $link['id'] }}' ? 'text-primary' : 'text-text-muted hover:text-primary'"
                       class="relative z-10 px-4 py-2 rounded-full text-[14px] font-bold transition-colors duration-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            <!-- Right side: Get Started + hamburger (phones) -->
            <div class="flex flex-shrink-0 items-center gap-2 sm:gap-3">
                <a href="{{ route('home') }}" class="inline-flex whitespace-nowrap text-[12px] sm:text-[13px] md:text-[14px] font-bold bg-primary text-white px-3.5 sm:px-5 md:px-6 py-2.5 rounded-full hover:bg-primary-dark transition-all duration-300 shadow-[0_4px_14px_0_rgba(207,65,115,0.39)] hover:shadow-[0_6px_20px_rgba(207,65,115,0.23)] hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    Get Started
                </a>

                <button @click="mobileMenuOpen = !mobileMenuOpen"
                        type="button"
                        class="md:hidden inline-flex h-10 w-10 items-center justify-center rounded-full text-text-main hover:text-primary transition-colors duration-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        aria-controls="mobile-menu"
                        :aria-expanded="mobileMenuOpen.toString()"
                        :aria-label="mobileMenuOpen ? 'Close navigation menu' : 'Open navigation menu'">
                    <span class="relative block h-5 w-5" aria-hidden="true">
                        <svg class="absolute inset-0 h-5 w-5 transition-all duration-300"
                             :class="mobileMenuOpen ? 'rotate-90 scale-0 opacity-0' : 'rotate-0 scale-100 opacity-100'"
                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg class="absolute inset-0 h-5 w-5 transition-all duration-300"
                             :class="mobileMenuOpen ? 'rotate-0 scale-100 opacity-100' : '-rotate-90 scale-0 opacity-0'"
                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Hamburger dropdown: the nav links, right-aligned under the button (phones only) -->
    <div x-show="mobileMenuOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
         class="md:hidden absolute top-[calc(100%+0.5rem)] right-4 sm:right-6 w-64 origin-top-right z-40"
         id="mobile-menu"
         x-cloak>
        <nav aria-label="Mobile" class="bg-surface/95 backdrop-blur-xl rounded-[1.75rem] border border-border-subtle/50 shadow-2xl p-2 space-y-1">
            @foreach ($navLinks as $link)
                <a href="#{{ $link['id'] }}"
                   @click="mobileMenuOpen = false"
                   class="group flex items-center justify-between px-4 py-3 rounded-2xl hover:bg-primary/10 transition-colors focus-visible:outline-2 focus-visible:outline-primary">
                    <span class="text-base font-bold text-text-main group-hover:text-primary transition-colors">{{ $link['label'] }}</span>
                    <svg class="w-4 h-4 text-text-muted group-hover:text-primary transition-all group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            @endforeach
        </nav>
    </div>
</div>