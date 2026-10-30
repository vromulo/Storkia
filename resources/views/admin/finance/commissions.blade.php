@extends('layouts.admin', ['title' => 'Storkia Admin - Platform Commissions & Vouchers'])

@section('content')
<div x-data="{ voucherModalOpen: false, voucherScope: 'platform', discountType: 'fixed' }" class="p-8 lg:p-10 max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Platform Commissions, E-Wallet &amp; Vouchers</h1>
            <p class="text-sm text-text-muted mt-1">
                Split-Base Policy: <strong>10% Admin</strong> &amp; <strong>90% Seller</strong> on Product Subtotal |
                <strong>10% Logistics</strong>, <strong>10% Rider</strong> &amp; <strong>80% Logistics Ops</strong> on Original Shipping Fee.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('admin.reports.sales') }}"
               class="px-4 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors">
                Generate Sales &amp; Role Performance Report
            </a>
            <a href="{{ route('admin.accounts.index') }}"
               class="px-4 py-2.5 rounded-2xl bg-surface border border-border-subtle hover:border-primary text-text-main text-xs font-bold transition-colors">
                User Accounts &amp; Ban Appeals
            </a>
            <button type="button"
                    @click="voucherModalOpen = true"
                    class="px-4 py-2.5 rounded-2xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold cursor-pointer">
                + Create Platform / Shipping Voucher
            </button>
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
            <span class="text-[11px] font-bold uppercase text-white/80">Admin Real-Money E-Wallet</span>
            <p class="text-2xl font-serif font-extrabold mt-1.5">₱{{ number_format($metrics['admin_wallet_available'] ?? $metrics['admin_10_commission_total'], 2) }}</p>
            <span class="text-[10px] text-white/85 block mt-1">Pending Escrow: ₱{{ number_format($metrics['admin_wallet_pending'] ?? 0, 2) }}</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Admin Gross (10% Products)</span>
            <p class="text-2xl font-serif font-bold text-primary mt-1.5">₱{{ number_format($metrics['admin_10_commission_total'], 2) }}</p>
            <span class="text-[10px] text-text-muted">Net After Promo Subsidies: ₱{{ number_format($metrics['admin_net_profit_after_sub'] ?? $metrics['admin_10_commission_total'], 2) }}</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Seller Net Earnings (90%)</span>
            <p class="text-2xl font-serif font-bold text-green-700 mt-1.5">₱{{ number_format($metrics['seller_90_net_total'], 2) }}</p>
            <span class="text-[10px] text-text-muted">Settled: ₱{{ number_format($metrics['settled_seller_payouts'], 2) }}</span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Logistics Earnings (10% + 80%)</span>
            <p class="text-2xl font-serif font-bold text-primary-dark mt-1.5">
                ₱{{ number_format($metrics['logistics_10_commission'] + $metrics['logistics_80_operational'], 2) }}
            </p>
            <span class="text-[10px] text-text-muted">
                10%: ₱{{ number_format($metrics['logistics_10_commission'], 2) }} | 80%: ₱{{ number_format($metrics['logistics_80_operational'], 2) }}
            </span>
        </div>

        <div class="p-5 bg-surface rounded-2xl border border-border-subtle shadow-xs">
            <span class="text-[11px] font-bold uppercase text-text-muted">Rider Commission (10% Ship)</span>
            <p class="text-2xl font-serif font-bold text-blue-700 mt-1.5">₱{{ number_format($metrics['rider_10_commission'], 2) }}</p>
            <span class="text-[10px] text-text-muted">From ₱{{ number_format($metrics['gross_shipping_volume'], 2) }} Shipping Vol.</span>
        </div>
    </div>

    @if(isset($platformVouchers) && $platformVouchers->isNotEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-primary-dark">Admin-Sponsored Platform &amp; Free Shipping Vouchers</h2>
                    <p class="text-xs text-text-muted">Admin funds these discounts so Sellers, Logistics Hubs, and Riders never lose their commission shares.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                @foreach($platformVouchers as $pv)
                    <div class="p-4 rounded-2xl border border-border-subtle bg-surface-subtle/50 flex flex-col justify-between gap-3">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-mono font-extrabold text-primary-dark px-2 py-0.5 rounded bg-brand-light/50">{{ $pv->code }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $pv->isCurrentlyOngoing() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-danger' }}">
                                    {{ $pv->effective_status }}
                                </span>
                            </div>
                            <h4 class="font-bold text-sm text-text-main mt-1.5">{{ $pv->title }} ({{ $pv->badge_label }})</h4>
                            <p class="text-[11px] text-text-muted mt-0.5">
                                Scope: <strong class="uppercase">{{ $pv->scope }}</strong> &bull; Min. Spend: ₱{{ number_format((float) $pv->min_spend, 2) }}
                            </p>
                            <p class="text-[11px] text-primary font-semibold mt-1">{{ $pv->expiration_summary }}</p>
                        </div>
                        <div class="pt-2 border-t border-border-subtle flex items-center justify-between">
                            <span class="text-[11px] text-text-muted">Used: <strong>{{ $pv->used_count }} / {{ $pv->total_usage_limit }}</strong></span>
                            @if($pv->status === 'active')
                                <form action="{{ route('admin.vouchers.end', $pv->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="px-3 py-1 rounded-lg bg-red-50 text-danger font-bold text-[11px] cursor-pointer">End Now</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            @foreach(['all' => 'All Records', 'recorded' => 'Recorded (Pending Escrow)', 'settled' => 'Settled (E-Wallet Released)'] as $key => $label)
                <a href="{{ route('admin.commissions.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search])) }}"
                   class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $status === $key ? 'bg-primary-dark text-surface border-primary-dark' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.commissions.index') }}" class="flex items-center gap-2">
            @if($status !== 'all')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <input type="text"
                   name="search"
                   value="{{ $search }}"
                   placeholder="Order #, seller, logistics hub..."
                   class="px-4 py-2 rounded-2xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-64">
            <button type="submit" class="px-4 py-2 rounded-2xl bg-primary-dark hover:bg-text-main text-surface text-xs font-bold cursor-pointer">
                Search
            </button>
        </form>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($commissions->isEmpty())
            <div class="p-14 text-center text-xs text-text-muted">
                No commission records found. Commissions are recorded automatically upon delivery and released as real money when the Buyer confirms receipt.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase font-bold text-text-muted">
                            <th class="py-3.5 px-5">Seller Order</th>
                            <th class="py-3.5 px-4">Seller &amp; Hub</th>
                            <th class="py-3.5 px-4">Product Split (10% / 90%)</th>
                            <th class="py-3.5 px-4">Shipping Split (10% / 10% / 80%)</th>
                            <th class="py-3.5 px-4">E-Wallet Status</th>
                            <th class="py-3.5 px-5 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($commissions as $rec)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-mono font-bold text-primary-dark">{{ $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id }}</div>
                                    <div class="text-text-muted">Payable: ₱{{ number_format((float) $rec->total_cod_amount, 2) }}</div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <div class="font-bold text-text-main">Seller: {{ $rec->sellerProfile->business_name ?? 'N/A' }}</div>
                                    <div class="text-text-muted">Hub: {{ $rec->logisticsProfile->business_name ?? 'N/A' }}</div>
                                    <div class="text-text-muted">Rider: {{ $rec->rider?->full_name ?? 'N/A' }}</div>
                                </td>
                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div>Base: <strong>₱{{ number_format((float) $rec->product_subtotal, 2) }}</strong></div>
                                    <div class="text-primary font-bold">Admin (10%): ₱{{ number_format((float) $rec->admin_commission_amount, 2) }}</div>
                                    <div class="text-green-700 font-bold">Seller (90%): ₱{{ number_format((float) $rec->seller_net_earning, 2) }}</div>
                                </td>
                                <td class="py-4 px-4 align-top space-y-0.5">
                                    <div>Orig. Ship: <strong>₱{{ number_format((float) $rec->shipping_fee, 2) }}</strong></div>
                                    <div>Logistics (10%): ₱{{ number_format((float) $rec->logistics_commission_amount, 2) }}</div>
                                    <div>Rider (10%): ₱{{ number_format((float) $rec->rider_commission_amount, 2) }}</div>
                                    <div class="text-text-muted">Logistics Ops (80%): ₱{{ number_format((float) $rec->logistics_operational_revenue, 2) }}</div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rec->status === 'settled' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $rec->status === 'settled' ? 'Released to Wallets' : 'Pending Escrow' }}
                                    </span>
                                </td>
                                <td class="py-4 px-5 align-top text-right text-text-muted">
                                    {{ $rec->calculated_at?->format('M d, Y h:i A') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-border-subtle">
                {{ $commissions->links() }}
            </div>
        @endif
    </div>

    <template x-teleport="body">
        <div x-show="voucherModalOpen"
             x-cloak
             class="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div @click="voucherModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
            <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-border-subtle space-y-4 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h3 class="text-lg font-bold text-primary-dark">Create Admin Platform / Free Shipping Voucher</h3>
                    <button type="button" @click="voucherModalOpen = false" class="p-1 text-text-muted hover:text-text-main cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('admin.vouchers.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Voucher Scope <span class="text-danger">*</span></label>
                            <select name="scope" x-model="voucherScope" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                                <option value="platform">Platform Merchandise Voucher</option>
                                <option value="shipping">Free Shipping Voucher</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Voucher Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" required maxlength="15" placeholder="e.g. PLAT150 or FREESHIP80" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-mono uppercase font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-text-muted uppercase mb-1">Display Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" required maxlength="120" placeholder="e.g. Payday Free Shipping ₱80 Off" class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Discount Type</label>
                            <select name="discount_type" x-model="discountType" :disabled="voucherScope === 'shipping'" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                                <option value="fixed">Fixed (₱)</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="discount_value" value="50" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Min. Spend (₱) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="min_spend" value="300" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-bold">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Total Limit <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="total_usage_limit" value="500" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Per Buyer Limit <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="per_buyer_limit" value="2" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Visibility</label>
                            <select name="visibility" class="w-full p-3 rounded-xl border border-border-subtle bg-surface font-semibold">
                                <option value="claimable">Claimable in Wallet</option>
                                <option value="code_only">Secret Code-Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Start Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="starts_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                        </div>
                        <div>
                            <label class="block font-bold text-text-muted uppercase mb-1">Expiration Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="ends_at" value="{{ now()->addMonths(2)->format('Y-m-d\TH:i') }}" required class="w-full p-3 rounded-xl border border-border-subtle bg-surface">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-border-subtle">
                        <button type="button" @click="voucherModalOpen = false" class="px-4 py-2.5 rounded-xl bg-surface-subtle text-text-muted font-bold cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold cursor-pointer">Publish Voucher</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection