@extends('layouts.seller', ['title' => 'Storkia - Inventory'])

@section('content')
    <div x-data="{
            searchQuery: '',
            stockFilter: 'all',
            visibleLimit: 10,
            matchesFilter(name, stock) {
                const q = this.searchQuery.trim().toLowerCase();
                if (q && !String(name || '').toLowerCase().includes(q)) return false;
                const s = Number(stock || 0);
                if (this.stockFilter === 'in_stock') return s > 10;
                if (this.stockFilter === 'low_stock') return s > 0 && s <= 10;
                if (this.stockFilter === 'out_of_stock') return s <= 0;
                return true;
            }
         }"
         class="max-w-7xl mx-auto space-y-6 select-none">

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-primary-dark">Inventory Management</h1>
                <p class="text-sm text-text-muted mt-1">Monitor real-time stock quantities, low-stock alerts, and approval statuses across your catalog.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="text"
                       x-model="searchQuery"
                       placeholder="Search product inventory..."
                       class="px-4 py-2.5 rounded-2xl border border-border-subtle bg-surface text-xs text-text-main outline-none focus:border-primary w-60">
                <a href="{{ route('seller.products.index') }}"
                   class="px-4 py-2.5 rounded-2xl bg-primary hover:bg-primary-dark text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    Update Stock in Products &rarr;
                </a>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @foreach([
                'all'          => 'All Items',
                'in_stock'     => 'In Stock (> 10)',
                'low_stock'    => 'Low Stock (1–10)',
                'out_of_stock' => 'Out of Stock (0)',
            ] as $fKey => $fLabel)
                <button type="button"
                        @click="stockFilter = '{{ $fKey }}'"
                        :class="stockFilter === '{{ $fKey }}' ? 'bg-primary-dark text-white border-primary-dark shadow-xs' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main'"
                        class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all cursor-pointer">
                    {{ $fLabel }}
                </button>
            @endforeach
        </div>

        <div class="bg-surface rounded-3xl shadow-xs border border-border-subtle overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-subtle/60 text-text-muted text-xs uppercase tracking-wider border-b border-border-subtle">
                            <th class="p-4 font-bold">Product Name</th>
                            <th class="p-4 font-bold">Stock Quantity</th>
                            <th class="p-4 font-bold">Stock Status</th>
                            <th class="p-4 font-bold">Approval Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-sm">
                        @forelse($products as $idx => $product)
                            @php
                                $approvalStatus = $product->approval->status ?? 'Pending';
                                $stockQty = (int) ($product->stock_quantity ?? 0);
                            @endphp
                            <tr x-show="matchesFilter('{{ addslashes($product->name) }}', {{ $stockQty }}) && ({{ $idx }} < visibleLimit || searchQuery !== '' || stockFilter !== 'all')"
                                class="hover:bg-brand-light/10 transition-colors">
                                <td class="p-4 font-bold text-primary-dark">{{ $product->name }}</td>
                                <td class="p-4 font-serif font-extrabold text-lg text-text-main">{{ number_format($stockQty) }}</td>
                                <td class="p-4">
                                    @if($stockQty > 10)
                                        <span class="px-3 py-1 bg-success/15 text-success border border-success/25 rounded-full text-xs font-bold">In Stock</span>
                                    @elseif($stockQty > 0)
                                        <span class="px-3 py-1 bg-brand-light/80 text-primary-dark border border-border-subtle rounded-full text-xs font-bold">Low Stock</span>
                                    @else
                                        <span class="px-3 py-1 bg-danger/10 text-danger border border-danger/25 rounded-full text-xs font-bold">Out of Stock</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold border {{ $approvalStatus === 'Approved' ? 'bg-success/15 text-success border-success/25' : ($approvalStatus === 'Disapproved' ? 'bg-danger/10 text-danger border-danger/25' : 'bg-brand-light/70 text-primary-dark border-border-subtle') }}">
                                        {{ $approvalStatus }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-12 text-center text-text-muted text-xs">No inventory data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($products->count() > 10)
                <div x-show="searchQuery === '' && stockFilter === 'all'" class="p-4 border-t border-border-subtle bg-surface-subtle/30 flex justify-center">
                    <button type="button"
                            @click="visibleLimit = visibleLimit >= {{ $products->count() }} ? 10 : visibleLimit + 10"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="visibleLimit >= {{ $products->count() }} ? 'See Less Inventory' : 'See More Inventory (' + ({{ $products->count() }} - visibleLimit) + ' remaining)'"></span>
                    </button>
                </div>
            @endif
        </div>
    </div>
@endsection