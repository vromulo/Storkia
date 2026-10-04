@extends('layouts.admin', ['title' => 'Product Warnings'])

@section('content')
<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-yellow-600 mb-2">Warnings</h1>
        <p class="text-sm text-text-muted font-medium">Products rejected for not following assigned product categories or listing guidelines.</p>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm border border-border-subtle overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-yellow-50 text-yellow-800 text-sm border-b border-border-subtle">
                    <th class="p-4 font-bold">Product Details</th>
                    <th class="p-4 font-bold">Seller</th>
                    <th class="p-4 font-bold">Admin Message / Feedback</th>
                    <th class="p-4 font-bold">Reviewed By</th>
                    <th class="p-4 font-bold">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse($warnings as $warning)
                    <tr class="hover:bg-brand-light/10 text-sm transition-colors">
                        <td class="p-4 align-top">
                            <span class="font-bold text-primary-dark block">{{ $warning->product->name ?? 'Deleted Product' }}</span>
                            <div class="text-xs text-text-muted font-medium mt-1">Attempted: {{ $warning->product->category ?? 'None' }} &bull; {{ $warning->product->subcategory ?? 'None' }}</div>
                        </td>
                        <td class="p-4 align-top font-medium">{{ $warning->seller->sellerProfile->business_name ?? 'Unknown' }}</td>
                        <td class="p-4 align-top w-1/3">
                            <p class="text-text-main text-xs leading-relaxed bg-yellow-50/50 p-3 rounded-xl border border-yellow-100">{{ $warning->remarks }}</p>
                        </td>
                        <td class="p-4 align-top text-text-muted font-medium">{{ $warning->reviewer->name ?? 'System' }}</td>
                        <td class="p-4 align-top text-text-muted text-xs whitespace-nowrap">{{ $warning->updated_at->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-12 text-center text-text-muted font-medium">No warning records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($warnings->hasPages())
            <div class="p-4 border-t border-border-subtle">{{ $warnings->links() }}</div>
        @endif
    </div>
</div>
@endsection