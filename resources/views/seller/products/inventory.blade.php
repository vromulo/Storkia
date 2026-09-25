@extends('layouts.seller', ['title' => 'Storkia - Inventory'])

@section('content')
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-primary-dark mb-8">Inventory Management</h1>
        <div class="bg-white rounded-2xl shadow-sm border border-border-subtle overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-brand-light/30 text-text-muted text-sm border-b border-border-subtle">
                        <th class="p-4 font-bold">Product Name</th>
                        <th class="p-4 font-bold">Stock Quantity</th>
                        <th class="p-4 font-bold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr class="border-b border-border-subtle hover:bg-brand-light/10 transition-colors text-sm">
                            <td class="p-4 font-medium text-primary-dark">{{ $product->name }}</td>
                            <td class="p-4 font-serif text-lg">{{ $product->stock_quantity }}</td>
                            <td class="p-4">
                                @if($product->stock_quantity > 10)
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">In Stock</span>
                                @elseif($product->stock_quantity > 0)
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold">Low Stock</span>
                                @else
                                    <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">Out of Stock</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-8 text-center text-text-muted">No inventory data available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection