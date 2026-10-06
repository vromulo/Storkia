@extends('buyer.personal-center', [
    'pageTitle' => 'My Address Book',
    'pageSubtitle' => 'Manage your saved addresses and delivery preferences.'
])

@section('option-content')
<div class="space-y-8 pt-0 select-none">

    <!-- Centered Header Title (Matches Screenshot Typography & Weight) -->
    <div class="text-center pt-2">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-text-main uppercase tracking-tight">
            MY ADDRESS BOOK
        </h2>
    </div>

    <!-- Action Section: + ADD NEW ADDRESS Button -->
    <div class="pt-2 flex items-center justify-end">
        <button type="button"
                class="px-5 py-2.5 bg-text-main hover:bg-black text-surface text-xs sm:text-sm font-bold uppercase tracking-wider rounded-xl transition-all duration-150 cursor-pointer active:scale-95 shadow-none inline-flex items-center gap-1.5 select-none">
            <span>+</span>
            <span>ADD NEW ADDRESS</span>
        </button>
    </div>

    <hr class="border-border-subtle pt-2" />

    <!-- Address Cards List -->
    <!-- Address Cards: 1 column on mobile (<640px), 2 columns on tablet and desktop (sm:grid-cols-2) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
        {{-- Example 1: Default Address (Badge shown) --}}
        <x-buyer.address-card 
            :isDefault="true"
            firstName="Van"
            lastName="Romulo"
            phoneNumber="9762241547"
            houseDetails="Block 10 Lot 11, Lynville Diamond"
            barangay="Gatid"
            city="Santa Cruz"
            province="Laguna"
            postcode="4009"
        />

        {{-- Example 2: Secondary Address (Badge hidden) --}}
        <x-buyer.address-card 
            :isDefault="false"
            firstName="Van"
            lastName="Romulo"
            phoneNumber="9762241547"
            houseDetails="Unit 402, Tower B"
            street="Ayala Avenue"
            barangay="Bel-Air"
            city="Makati"
            province="Metro Manila"
            postcode="1209"
        />
    </div>

    <!-- Empty State / Placeholder Area -->
    <div class="pt-6 pb-12 text-center">
        <div class="w-12 h-12 rounded-full bg-surface-subtle text-text-muted/60 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>
        <p class="text-xs text-text-muted font-medium">No saved addresses yet. Click "+ ADD NEW ADDRESS" to add your primary delivery destination.</p>
    </div>

</div>
@endsection