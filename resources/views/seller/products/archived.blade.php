@extends('layouts.seller', ['title' => 'Storkia - Archived Products'])

@section('content')
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-primary-dark mb-8">Archived Products</h1>
        <div class="bg-white rounded-2xl shadow-sm border border-border-subtle overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-brand-light/30 text-text-muted text-sm border-b border-border-subtle">
                        <th class="p-4 font-bold">Product Name</th>
                        <th class="p-4 font-bold">Date Archived</th>
                        <th class="p-4 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr class="border-b border-border-subtle hover:bg-brand-light/10 transition-colors text-sm">
                            <td class="p-4 font-medium text-text-muted">{{ $product->name }}</td>
                            <td class="p-4 text-text-muted">{{ $product->deleted_at->format('M d, Y') }}</td>
                            <td class="p-4 text-right space-x-4">
                                <form action="{{ route('seller.products.unarchive', $product->id) }}" method="POST" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="text-primary hover:text-primary-dark font-medium cursor-pointer">Unarchive</button>
                                </form>
                                <form action="{{ route('seller.products.forceDelete', $product->id) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700 font-medium cursor-pointer" onclick="return confirm('Are you sure? This cannot be undone.')">Delete Permanently</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-8 text-center text-text-muted">No archived products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection