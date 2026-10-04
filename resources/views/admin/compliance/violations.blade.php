@extends('layouts.admin', ['title' => 'Product Violations'])

@section('content')
<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-red-600 mb-2">Violations</h1>
        <p class="text-sm text-text-muted font-medium">Products rejected due to explicit, offensive, or strictly prohibited content.</p>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm border border-border-subtle overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-red-50 text-red-800 text-sm border-b border-border-subtle">
                    <th class="p-4 font-bold">Product Details</th>
                    <th class="p-4 font-bold">Seller</th>
                    <th class="p-4 font-bold">Admin Message / Feedback</th>
                    <th class="p-4 font-bold">Reviewed By</th>
                    <th class="p-4 font-bold">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse($violations as $violation)
                    <tr class="hover:bg-brand-light/10 text-sm transition-colors">
                        <td class="p-4 align-top">
                            <span class="font-bold text-primary-dark block">{{ $violation->product->name ?? 'Deleted Product' }}</span>
                        </td>
                        <td class="p-4 align-top font-medium">{{ $violation->seller->sellerProfile->business_name ?? 'Unknown' }}</td>
                        <td class="p-4 align-top w-1/3">
                            <p class="text-text-main text-xs leading-relaxed bg-red-50/50 p-3 rounded-xl border border-red-100">{{ $violation->remarks }}</p>
                        </td>
                        <td class="p-4 align-top text-text-muted font-medium">{{ $violation->reviewer->name ?? 'System' }}</td>
                        <td class="p-4 align-top text-text-muted text-xs whitespace-nowrap">{{ $violation->updated_at->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-12 text-center text-text-muted font-medium">No violation records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($violations->hasPages())
            <div class="p-4 border-t border-border-subtle">{{ $violations->links() }}</div>
        @endif
    </div>
</div>
@endsection