@props([
    'title' => 'Sign In',
    'subtitle' => 'Enter your credentials to access your account',
    'expectedRole' => 'Buyer',
    'registerRoute' => route('register'),
    'registerText' => 'Create an account',
    'showRegister' => true,
    'secondaryRegisterRoute' => null,
    'secondaryRegisterPrompt' => 'Want to be a rider?',
    'secondaryRegisterText' => 'Apply here',
    'showSecondaryRegister' => false,
    'showRiderAppButton' => false
])

<div class="hidden lg:block lg:w-1/2 h-full">
    <x-ui.carousel />
</div>

<div class="w-full lg:w-1/2 h-full flex flex-col justify-between p-8 sm:p-14 lg:p-20 overflow-y-auto bg-surface relative">
    @if ($showRiderAppButton)
        <a href="{{ route('rider.download-app') }}" wire:navigate class="absolute top-6 right-6 sm:top-8 sm:right-8 flex items-center justify-center gap-2 px-4 py-1.5 bg-transparent border-2 border-text-main text-text-main hover:bg-text-main hover:text-surface font-bold text-[10px] uppercase tracking-wider rounded-full transition-colors cursor-pointer z-50">
            <span>Rider App</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M4 16v1h16v-1M12 4v10m0 0l-4-4m4 4l4-4"/>
            </svg>
        </a>
    @endif

    <div class="w-full max-w-md mx-auto text-center pt-2">
        <a href="/shop" wire:navigate class="inline-block group cursor-pointer focus:outline-none">
            <span class="font-serif text-4xl sm:text-5xl font-bold text-primary-dark tracking-tight transition-colors duration-200 group-hover:text-primary">
                Storkia
            </span>
        </a>
    </div>

    <div class="w-full max-w-md mx-auto my-auto py-8">
        <livewire:auth.login-form 
            :title="$title"
            :subtitle="$subtitle"
            :expected-role="$expectedRole"
            :register-route="$registerRoute"
            :register-text="$registerText"
            :show-register="$showRegister"
            :secondary-register-route="$secondaryRegisterRoute"
            :secondary-register-prompt="$secondaryRegisterPrompt"
            :secondary-register-text="$secondaryRegisterText"
            :show-secondary-register="$showSecondaryRegister"
        />
    </div>

    <div class="w-full max-w-md mx-auto text-center pb-2">
        <p class="text-[11px] text-text-muted/70">
            &copy; {{ date('Y') }} Storkia. All rights reserved.
        </p>
    </div>
</div>