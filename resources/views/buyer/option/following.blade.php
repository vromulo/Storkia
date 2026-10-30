@extends('buyer.account-settings', [
    'pageTitle' => 'Following Stores',
    'pageSubtitle' => 'Manage the official stores you follow and view your 1-time Follower Welcome Vouchers.'
])

@section('option-content')
<div class="space-y-6 select-none w-full min-w-0 max-w-full overflow-x-hidden">

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if($follows->isEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-12 text-center space-y-3">
            <h3 class="text-base font-bold text-text-main">You are not following any stores yet</h3>
            <p class="text-xs text-text-muted max-w-md mx-auto">
                Follow your favorite verified merchants to automatically receive a 1-time ₱30 OFF Store Follower Welcome Voucher in your wallet!
            </p>
            <a href="{{ route('sellers.index') }}" class="inline-flex px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-dark transition-colors">
                Explore Official Stores
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($follows as $follow)
                @php
                    $store = $follow->sellerProfile;
                    if (! $store) continue;
                    $metrics = \App\Http\Controllers\Buyer\SellerController::buildStoreMetrics($store);
                @endphp
                <div class="p-5 rounded-3xl bg-surface border border-border-subtle shadow-xs flex flex-col justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('sellers.show', $store->id) }}" class="w-16 h-16 rounded-full overflow-hidden border border-border-subtle bg-surface-subtle flex items-center justify-center shrink-0">
                            @if($store->logo_path)
                                <img src="{{ Storage::url($store->logo_path) }}" alt="{{ $store->business_name }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-xl font-extrabold text-primary">{{ strtoupper(substr($store->business_name, 0, 1)) }}</span>
                            @endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('sellers.show', $store->id) }}" class="text-base font-bold text-text-main hover:text-primary truncate block">
                                {{ $store->business_name }}
                            </a>
                            <p class="text-xs text-text-muted">{{ $store->line_of_business }} &bull; {{ $store->municipality }}, {{ $store->province }}</p>
                            <div class="flex flex-wrap items-center gap-3 mt-1.5 text-[11px] text-text-muted">
                                <span>Rating: <strong class="text-primary">{{ $metrics['ratings'] }} ★</strong></span>
                                <span>Followers: <strong class="text-text-main">{{ number_format($metrics['followers']) }}</strong></span>
                                <span>Chat: <strong class="text-primary">{{ $metrics['chat_response_rate'] }}%</strong></span>
                            </div>
                        </div>
                    </div>

                    @if($follow->reward_voucher_code)
                        <div class="p-3 rounded-2xl bg-brand-light/30 border border-border-subtle flex items-center justify-between text-xs">
                            <div>
                                <span class="text-[10px] font-bold uppercase text-primary-dark block">1-Time Follower Reward Voucher</span>
                                <span class="font-mono font-extrabold text-primary">{{ $follow->reward_voucher_code }}</span>
                                <span class="text-text-muted text-[11px] ml-1">(₱30 OFF &bull; Min. ₱150)</span>
                            </div>
                            <a href="{{ route('user.coupons') }}" class="text-[11px] font-bold text-primary hover:underline">In Wallet &rarr;</a>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border-subtle">
                        <a href="{{ route('sellers.show', $store->id) }}" class="px-4 py-2 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white text-xs font-bold transition-colors">
                            Visit Store
                        </a>
                        <form action="{{ route('sellers.follow', $store->id) }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-xl border border-border-subtle hover:border-danger text-text-muted hover:text-danger text-xs font-bold transition-colors cursor-pointer">
                                Unfollow
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection