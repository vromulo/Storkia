@props([
    'title',
    'subtitle' => null,
])

<div class="hidden lg:block lg:w-1/2 h-full">
    <x-ui.carousel />
</div>

<div class="w-full lg:w-1/2 h-full flex flex-col justify-between p-8 sm:p-14 lg:p-20 overflow-y-auto bg-surface relative">
    <div class="w-full max-w-md mx-auto text-center pt-2">
        <a href="/shop" class="inline-block group cursor-pointer focus:outline-none">
            <span class="font-serif text-4xl sm:text-5xl font-bold text-primary-dark tracking-tight transition-colors duration-200 group-hover:text-primary">
                Storkia
            </span>
        </a>
    </div>

    <div class="w-full max-w-md mx-auto my-auto py-8">
        <div class="mb-10 text-center">
            <h1 class="text-2xl sm:text-3xl font-serif font-normal tracking-tight text-primary-dark">
                {{ $title }}
            </h1>
            @if ($subtitle)
                <p class="mt-2 text-sm text-text-muted font-normal leading-relaxed">
                    {{ $subtitle }}
                </p>
            @endif
        </div>

        {{ $slot }}
    </div>

    <div class="w-full max-w-md mx-auto text-center pb-2">
        <p class="text-[11px] text-text-muted/70">
            &copy; {{ date('Y') }} Storkia. All rights reserved.
        </p>
    </div>
</div>