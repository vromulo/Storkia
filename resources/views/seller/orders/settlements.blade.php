@extends('layouts.seller', ['title' => 'Storkia - Seller E-Wallet, Payouts & Financial Report'])

@section('content')
@php
    $payoutRequests = $payoutRequests ?? collect();
    $hasPendingPayout = $payoutRequests->where('status', 'pending')->isNotEmpty();
    $availableBalance = (float) ($metrics['wallet_available'] ?? $metrics['settled_total']);
    $arrearsAmount = (float) ($metrics['wallet_arrears'] ?? ($availableBalance < 0 ? abs($availableBalance) : 0));
@endphp

<div x-data="{
    payoutModalOpen: false,
    arrearsModalOpen: false,
    payoutMethod: 'GCASH',
    isSubmittingPayout: false,
    isSubmittingArrears: false
}" class="max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Seller E-Wallet, Payouts &amp; Financial Report</h1>
            <p class="text-sm text-text-muted mt-1">
                Split-Base Policy: You earn <strong>90%</strong> of your Commissionable Product Subtotal (10% Admin Commission). Direct-to-Seller GCash orders automatically offset their 10% Admin Commission + Shipping Fee against your E-Wallet.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('seller.reports.formal-pdf', ['from_date' => $fromDate ?? now()->startOfMonth()->toDateString(), 'to_date' => $toDate ?? now()->toDateString(), 'preset' => 'custom']) }}"
               target="_blank"
               class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold shadow-xs transition-colors">
                Open Formal Printable PDF Statement
            </a>

            <button type="button"
                    @click="payoutModalOpen = true"
                    @if($availableBalance < 100 || $hasPendingPayout) disabled @endif
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold shadow-xs transition-colors {{ ($availableBalance >= 100 && !$hasPendingPayout) ? 'bg-green-600 hover:bg-green-700 text-white cursor-pointer' : 'bg-gray-200 text-gray-500 cursor-not-allowed' }}">
                {{ $hasPendingPayout ? 'Payout Request Pending' : 'Request Payout (Min ₱100)' }}
            </button>

            @if($arrearsAmount > 0)
                <button type="button"
                        @click="arrearsModalOpen = true"
                        class="px-4 py-2.5 rounded-2xl bg-danger text-white text-xs font-bold shadow-xs cursor-pointer">
                    Settle GCash Arrears (₱{{ number_format($arrearsAmount, 2) }})
                </button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-green-50 border border-green-200 text-green-700 text-xs sm:text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm space-y-1">
            @foreach($errors->all() as $err)
                <p class="font-semibold">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="p-5 bg-gradient-to-br from-primary-dark to-primary text-white rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold text-white/80 uppercase">Available Real-Money E-Wallet</span>
            <p class="text-2xl font-serif font-extrabold mt-2">₱{{ number_format($availableBalance, 2) }}</p>
            <span class="text-[10px] text-white/85 mt-1 block">Withdrawable when ≥ ₱100.00</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Pending Escrow Balance</span>
            <p class="text-2xl font-serif font-bold text-amber-600 mt-2">₱{{ number_format($metrics['wallet_pending_escrow'] ?? $metrics['pending_settlement'], 2) }}</p>
            <span class="text-[10px] text-text-muted mt-1 block">Delivered — Awaiting Buyer Confirm</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Gross Product Subtotal</span>
            <p class="text-2xl font-serif font-bold text-text-main mt-2">₱{{ number_format($metrics['gross_product_sales'], 2) }}</p>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Admin Commission (10%)</span>
            <p class="text-2xl font-serif font-bold text-danger mt-2">-₱{{ number_format($metrics['admin_commission_total'], 2) }}</p>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold text-text-muted uppercase">Total Net Earnings (90%)</span>
            <p class="text-2xl font-serif font-bold text-green-700 mt-2">₱{{ number_format($metrics['net_seller_earnings'], 2) }}</p>
        </div>
    </div>

    @if($payoutRequests->isNotEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
            <div class="p-5 border-b border-border-subtle bg-surface-subtle/50">
                <h2 class="text-base font-bold text-primary-dark">Seller Payout Withdrawal Requests (FIFO)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3 px-5">Payout Ref</th>
                            <th class="py-3 px-4">Destination</th>
                            <th class="py-3 px-4">Requested Amount</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-5 text-right">Requested At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($payoutRequests as $pr)
                            <tr>
                                <td class="py-3 px-5 font-mono font-bold text-primary-dark">{{ $pr->payout_reference }}</td>
                                <td class="py-3 px-4">{{ $pr->payout_method }} — {{ $pr->account_name }} ({{ $pr->account_number }})</td>
                                <td class="py-3 px-4 font-bold text-green-700">₱{{ number_format((float) $pr->requested_amount, 2) }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $pr->status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $pr->status }}
                                    </span>
                                </td>
                                <td class="py-3 px-5 text-right text-text-muted">{{ \Carbon\Carbon::parse($pr->created_at)->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        <div class="p-5 border-b border-border-subtle bg-surface-subtle/50 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-bold text-primary-dark">Per-Order Commission &amp; E-Wallet Earnings Ledger (FIFO)</h2>
            <form method="GET" action="{{ route('seller.settlements.index') }}" class="flex items-center gap-2 text-xs">
                <input type="date" name="from_date" value="{{ $fromDate ?? now()->startOfMonth()->toDateString() }}" class="px-2.5 py-1.5 rounded-xl border border-border-subtle bg-surface">
                <span>to</span>
                <input type="date" name="to_date" value="{{ $toDate ?? now()->toDateString() }}" class="px-2.5 py-1.5 rounded-xl border border-border-subtle bg-surface">
                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-primary-dark text-white font-bold cursor-pointer">Filter</button>
            </form>
        </div>

        @if($commissionRecords->isEmpty())
            <div class="p-10 text-center text-xs text-text-muted">
                Commission records are generated automatically when orders are delivered and completed.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/40 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Seller Order #</th>
                            <th class="py-3.5 px-4">Commissionable Subtotal</th>
                            <th class="py-3.5 px-4">Admin 10%</th>
                            <th class="py-3.5 px-4">Your Net Profit (90%)</th>
                            <th class="py-3.5 px-4">E-Wallet Status</th>
                            <th class="py-3.5 px-5 text-right">Recorded At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($commissionRecords as $rec)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-3.5 px-5 font-mono font-bold text-primary-dark">
                                    <a href="{{ route('seller.orders.show', $rec->seller_order_id) }}" class="hover:underline">
                                        {{ $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id }}
                                    </a>
                                </td>
                                <td class="py-3.5 px-4">₱{{ number_format((float) $rec->product_subtotal, 2) }}</td>
                                <td class="py-3.5 px-4 text-danger">-₱{{ number_format((float) $rec->admin_commission_amount, 2) }}</td>
                                <td class="py-3.5 px-4 font-bold text-green-700">₱{{ number_format((float) $rec->seller_net_earning, 2) }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rec->status === 'settled' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $rec->status === 'settled' ? 'Released to E-Wallet' : 'Pending Escrow' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 text-right text-text-muted">{{ $rec->calculated_at?->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-border-subtle">
                {{ $commissionRecords->links() }}
            </div>
        @endif
    </div>

    <!-- Request Payout Modal -->
    <template x-teleport="body">
        <div x-show="payoutModalOpen" x-cloak class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="payoutModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-primary-dark">Request Seller E-Wallet Payout</h3>
                    <button type="button" @click="payoutModalOpen = false" class="p-1 text-text-muted cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('seller.payouts.store') }}"
                      method="POST"
                      @submit="isSubmittingPayout = true"
                      class="space-y-4 text-xs">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <div class="p-3 rounded-xl bg-green-50 border border-green-200 text-green-900 font-bold flex justify-between">
                        <span>Available Balance:</span>
                        <span>₱{{ number_format(max(0, $availableBalance), 2) }}</span>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Withdrawal Amount (₱100.00 – ₱{{ number_format(max(0, $availableBalance), 2) }}) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="100" max="{{ max(100, $availableBalance) }}" name="requested_amount" value="{{ number_format(max(0, $availableBalance), 2, '.', '') }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold text-sm">
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Payout Method <span class="text-danger">*</span></label>
                        <select name="payout_method" x-model="payoutMethod" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                            <option value="GCASH">GCash Wallet</option>
                            <option value="BANK">Bank Transfer</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Account Holder Name <span class="text-danger">*</span></label>
                            <input type="text" name="account_name" value="{{ $profile->gcashAccount->account_name ?? auth()->user()->full_name }}" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Account / GCash No. <span class="text-danger">*</span></label>
                            <input type="text" name="account_number" value="{{ $profile->gcashAccount->gcash_number ?? '' }}" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="payoutModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="isSubmittingPayout" class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold cursor-pointer disabled:opacity-50">
                            Submit Payout Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- Settle GCash Platform Arrears Modal -->
    <template x-teleport="body">
        <div x-show="arrearsModalOpen" x-cloak class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="arrearsModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-md w-full p-6 shadow-2xl border border-border-subtle space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-base font-bold text-danger">Settle Direct-GCash Platform Arrears</h3>
                    <button type="button" @click="arrearsModalOpen = false" class="p-1 text-text-muted cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('seller.arrears.remit') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      @submit="isSubmittingArrears = true"
                      class="space-y-4 text-xs">
                    @csrf
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-900">
                        Outstanding Platform &amp; Shipping Payable from Direct GCash Orders: <strong>₱{{ number_format($arrearsAmount, 2) }}</strong>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Amount Remitted (₱) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="amount_remitted" value="{{ number_format($arrearsAmount, 2, '.', '') }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Sender GCash Number <span class="text-danger">*</span></label>
                            <input type="text" name="sender_gcash_number" maxlength="11" placeholder="09171234567" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">GCash Reference # <span class="text-danger">*</span></label>
                            <input type="text" name="gcash_reference_number" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono uppercase">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Upload Receipt Screenshot</label>
                        <input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" class="w-full p-2 rounded-xl border border-border-subtle bg-surface">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="arrearsModalOpen = false" class="px-4 py-2 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" :disabled="isSubmittingArrears" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold cursor-pointer">
                            Submit Remittance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection