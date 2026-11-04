@extends('buyer.account-settings', [
    'pageTitle' => 'Recently Viewed',
    'pageSubtitle' => 'Products you recently explored across Storkia stores.'
])

@section('option-content')
<div class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">
    @if($recentProducts->isEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-3">
            <div class="w-14 h-14 rounded-2xl bg-surface-subtle text-text-muted flex items-center justify-center mx-auto border border-border-subtle">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-text-main">No Recently Viewed Products Yet</h3>
            <p class="text-xs text-text-muted max-w-md mx-auto">
                Products you open on the storefront will automatically appear here for quick access.
            </p>
            <a href="{{ route('home') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-dark transition-colors">
                Browse Storefront
            </a>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-5">
            @foreach($recentProducts as $product)
                <div class="bg-surface rounded-2xl p-3 border border-border-subtle/70 hover:border-primary/40 transition-all">
                    <x-storefront.seller-product-card :product="$product" />
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection