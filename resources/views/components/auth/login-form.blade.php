@props([
    'title' => 'Sign In',
    'subtitle' => 'Enter your credentials to access your account',
    'expectedRole' => 'Buyer',
    'registerRoute' => route('register'),
    'registerText' => 'Create an account',
    'showRegister' => true
])

<!-- Split Layout Canvas -->
<div class="hidden lg:block lg:w-1/2 h-full">
    <x-ui.carousel />
</div>

<!-- Right Canvas: Minimalist, Containerless Column -->
<div class="w-full lg:w-1/2 h-full flex flex-col justify-between p-8 sm:p-14 lg:p-20 overflow-y-auto bg-surface">
    <!-- Centered, Prominent Brand Title -->
    <div class="w-full max-w-md mx-auto text-center pt-2">
        <a href="/" wire:navigate class="inline-block group cursor-pointer focus:outline-none">
            <span class="font-serif text-4xl sm:text-5xl font-bold text-primary-dark tracking-tight transition-colors duration-200 group-hover:text-primary">
                Storkia
            </span>
        </a>
    </div>

    <!-- Center Form Body (Livewire Component) -->
    <div class="w-full max-w-md mx-auto my-auto py-8">
        <livewire:auth.login-form 
            :title="$title"
            :subtitle="$subtitle"
            :expected-role="$expectedRole"
            :register-route="$registerRoute"
            :register-text="$registerText"
            :show-register="$showRegister"
        />
    </div>

    <!-- Minimalist Bottom Footer -->
    <div class="w-full max-w-md mx-auto text-center pb-2">
        <p class="text-[11px] text-text-muted/70">
            &copy; {{ date('Y') }} Storkia. All rights reserved.
        </p>
    </div>
</div>