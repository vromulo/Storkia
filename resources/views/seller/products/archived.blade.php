@extends('layouts.seller', ['title' => 'Storkia - Archived Products'])

@section('content')
    <div x-data="{
            deleteModalOpen: false,
            deleteProductName: '',
            deleteFormAction: '',
            visibleLimit: 8,
            openDeleteModal(name, actionUrl) {
                this.deleteProductName = name;
                this.deleteFormAction = actionUrl;
                this.deleteModalOpen = true;
            }
         }"
         class="max-w-7xl mx-auto space-y-6 select-none">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-primary-dark">Archived Products</h1>
                <p class="text-sm text-text-muted mt-1">Restore archived products back to your active catalog or permanently delete them.</p>
            </div>
            <a href="{{ route('seller.products.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-surface border border-border-subtle hover:border-primary/40 text-xs font-bold text-text-main transition-colors cursor-pointer self-start sm:self-auto">
                &larr; Back to All Products
            </a>
        </div>

        <div class="bg-surface rounded-3xl shadow-xs border border-border-subtle overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-subtle/60 text-text-muted text-xs uppercase tracking-wider border-b border-border-subtle">
                            <th class="p-4 font-bold">Product Name</th>
                            <th class="p-4 font-bold">Approval Status</th>
                            <th class="p-4 font-bold">Date Archived</th>
                            <th class="p-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle text-sm">
                        @forelse($products as $idx => $product)
                            <tr x-show="{{ $idx }} < visibleLimit"
                                x-transition
                                class="hover:bg-brand-light/10 transition-colors">
                                <td class="p-4 font-bold text-primary-dark">{{ $product->name }}</td>
                                <td class="p-4">
                                    @php $approvalStatus = $product->approval->status ?? 'Pending'; @endphp
                                    <span class="px-2.5 py-1 rounded-full text-[10px] uppercase font-extrabold border {{ $approvalStatus === 'Approved' ? 'bg-success/15 text-success border-success/25' : ($approvalStatus === 'Disapproved' ? 'bg-danger/10 text-danger border-danger/25' : 'bg-brand-light/70 text-primary-dark border-border-subtle') }}">
                                        {{ $approvalStatus }}
                                    </span>
                                </td>
                                <td class="p-4 text-text-muted text-xs font-mono">{{ $product->deleted_at?->format('M d, Y') }}</td>
                                <td class="p-4 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <form action="{{ route('seller.products.unarchive', $product->id) }}" method="POST" class="inline m-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-white text-xs font-bold transition-colors cursor-pointer">
                                                Unarchive
                                            </button>
                                        </form>
                                        <button type="button"
                                                @click="openDeleteModal('{{ addslashes($product->name) }}', '{{ route('seller.products.forceDelete', $product->id) }}')"
                                                class="px-3.5 py-1.5 rounded-xl bg-danger/10 hover:bg-danger text-danger hover:text-white text-xs font-bold transition-colors cursor-pointer">
                                            Delete Permanently
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-12 text-center text-text-muted text-xs">No archived products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($products->count() > 8)
                <div class="p-4 border-t border-border-subtle bg-surface-subtle/30 flex justify-center">
                    <button type="button"
                            @click="visibleLimit = visibleLimit >= {{ $products->count() }} ? 8 : visibleLimit + 8"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/30 text-xs font-extrabold transition-colors cursor-pointer">
                        <span x-text="visibleLimit >= {{ $products->count() }} ? 'See Less' : 'See More Archived Products (' + ({{ $products->count() }} - visibleLimit) + ' remaining)'"></span>
                    </button>
                </div>
            @endif
        </div>

        <template x-teleport="body">
            <div x-show="deleteModalOpen"
                 x-cloak
                 class="fixed inset-0 z-[210] flex items-center justify-center p-4">
                <div @click="deleteModalOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-xs cursor-pointer"></div>
                <div @click.stop class="relative z-10 bg-surface rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-border-subtle text-center space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-danger/10 text-danger flex items-center justify-center mx-auto border border-danger/20">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-primary-dark">Delete Product Permanently?</h3>
                    <p class="text-xs text-text-muted leading-relaxed">
                        Are you sure you want to permanently delete <strong class="text-text-main" x-text="deleteProductName"></strong>? This action cannot be undone.
                    </p>
                    <form :action="deleteFormAction" method="POST" class="flex gap-2.5 pt-2 m-0">
                        @csrf
                        @method('DELETE')
                        <button type="button" @click="deleteModalOpen = false" class="flex-1 py-2.5 rounded-xl bg-surface-subtle hover:bg-brand-light/30 text-text-main text-xs font-bold transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-danger hover:opacity-90 text-white text-xs font-bold shadow-sm transition-opacity cursor-pointer">
                            Delete Forever
                        </button>
                    </form>
                </div>
            </div>
        </template>
    </div>
@endsection