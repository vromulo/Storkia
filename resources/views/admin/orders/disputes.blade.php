@extends('layouts.admin', ['title' => 'Storkia Admin - Delivery Disputes'])

@section('content')
<div class="p-8 lg:p-10 max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Delivery Complaints &amp; Disputes</h1>
            <p class="text-sm text-text-muted mt-1">
                Review buyer-reported delivery issues. Unresolved disputes pause the 72-hour automatic order completion timer.
            </p>
        </div>

        <div class="flex items-center gap-2">
            @foreach(['unresolved' => 'Unresolved (' . $counts['unresolved'] . ')', 'resolved' => 'Resolved (' . $counts['resolved'] . ')', 'all' => 'All (' . $counts['all'] . ')'] as $key => $label)
                <a href="{{ route('admin.complaints.index', ['status' => $key]) }}"
                   class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $status === $key ? 'bg-primary-dark text-surface border-primary-dark' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main' }}">
                    {{ $label }}
                </a>
            @endforeach
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

    @if($disputedOrders->isEmpty())
        <div class="bg-surface rounded-3xl border border-border-subtle p-14 text-center text-xs text-text-muted">
            No delivery disputes match the selected filter.
        </div>
    @else
        <div class="space-y-4">
            @foreach($disputedOrders as $so)
                @php
                    $proof = $so->shipment?->latestDeliveryProof;
                @endphp
                <div class="bg-surface rounded-3xl border border-border-subtle p-6 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-border-subtle">
                        <div class="flex items-center gap-2.5">
                            <span class="font-mono font-bold text-sm text-primary-dark bg-brand-light/40 px-3 py-1 rounded-xl">
                                {{ $so->seller_order_number }}
                            </span>
                            <span class="text-xs font-bold text-text-main">
                                Seller: {{ $so->sellerProfile->business_name ?? 'N/A' }} &bull; Buyer: {{ $so->buyer?->full_name }}
                            </span>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $so->has_unresolved_dispute ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                            {{ $so->has_unresolved_dispute ? 'Unresolved Dispute (Auto-Completion Paused)' : 'Resolved (' . $so->status_label . ')' }}
                        </span>
                    </div>

                    <div class="p-4 rounded-2xl bg-red-50/70 border border-red-200 text-xs space-y-1">
                        <span class="font-bold text-red-900 block">Buyer Dispute Reason (Reported {{ $so->dispute_reported_at?->format('M d, Y h:i A') }}):</span>
                        <p class="text-red-800 italic">"{{ $so->dispute_reason }}"</p>
                    </div>

                    @if($so->has_unresolved_dispute)
                        <form action="{{ route('admin.complaints.resolve', $so->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end pt-2">
                            @csrf
                            <div>
                                <label class="block text-xs font-bold text-text-muted mb-1">Admin Decision <span class="text-danger">*</span></label>
                                <select name="resolution" required class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface text-xs font-semibold">
                                    <option value="complete_order">Release &amp; Complete Order (Valid Delivery)</option>
                                    <option value="return_and_cancel">Return to Seller &amp; Restore Stock</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-text-muted mb-1">Resolution Remarks <span class="text-danger">*</span></label>
                                <input type="text" name="remarks" required minlength="5" placeholder="Enter investigation notes..." class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface text-xs">
                            </div>
                            <div class="flex items-center gap-2">
                                @if($proof)
                                    <a href="{{ route('user.orders.proof-image', $proof->id) }}" target="_blank" class="px-3.5 py-2.5 rounded-xl border border-border-subtle bg-surface-subtle text-xs font-bold text-primary hover:underline shrink-0">
                                        View Proof Photo
                                    </a>
                                @endif
                                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-primary-dark hover:bg-text-main text-white text-xs font-bold cursor-pointer">
                                    Resolve Dispute
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach

            <div>
                {{ $disputedOrders->links() }}
            </div>
        </div>
    @endif

</div>
@endsection