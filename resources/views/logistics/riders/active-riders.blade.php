@extends('layouts.logistics', ['title' => 'Storkia - Active Riders'])

@section('content')
<div class="max-w-7xl mx-auto space-y-6 select-none">

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-primary-dark">Approved Last-Mile Riders</h1>
            <p class="text-sm text-text-muted mt-1">
                Monitor rider availability, active last-mile delivery assignments, and completed deliveries for {{ $profile->business_name }}.
            </p>
        </div>

        <div class="flex items-center gap-2">
            @foreach(['all' => 'All Approved', 'available' => 'Available', 'on_delivery' => 'On Delivery'] as $key => $label)
                <a href="{{ route('logistics.riders.active', ['filter' => $key]) }}"
                   class="px-4 py-2 rounded-2xl text-xs font-bold border transition-all {{ $filter === $key ? 'bg-primary-dark text-surface border-primary-dark' : 'bg-surface text-text-muted border-border-subtle hover:text-text-main' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($riders->isEmpty())
            <div class="p-14 text-center text-xs text-text-muted">
                No approved riders match this filter.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-[11px] uppercase font-bold text-text-muted">
                            <th class="py-3.5 px-5">Rider</th>
                            <th class="py-3.5 px-4">Vehicle & Plate</th>
                            <th class="py-3.5 px-4">Active Deliveries</th>
                            <th class="py-3.5 px-4">Completed Deliveries</th>
                            <th class="py-3.5 px-5 text-right">Availability</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($riders as $rider)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5">
                                    <div class="font-bold text-sm text-text-main">{{ $rider->full_name }}</div>
                                    <div class="text-text-muted">{{ $rider->email }} &bull; {{ $rider->contact_no }}</div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-bold text-text-main">{{ $rider->vehicle_type }}</div>
                                    <div class="font-mono text-primary-dark">{{ $rider->plate_number }}</div>
                                </td>
                                <td class="py-4 px-4 font-bold text-sm text-primary-dark">{{ $rider->active_deliveries_count }}</td>
                                <td class="py-4 px-4 font-bold text-sm text-green-700">{{ $rider->completed_deliveries_count }}</td>
                                <td class="py-4 px-5 text-right">
                                    @if($rider->active_deliveries_count > 0)
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700">On Delivery</span>
                                    @else
                                        <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-green-100 text-green-700">Available</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-border-subtle">
                {{ $riders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection