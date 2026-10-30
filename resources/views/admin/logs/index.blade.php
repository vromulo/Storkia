@extends('layouts.admin', ['title' => 'Storkia Admin - Audit & Security Logs'])

@section('content')
<div class="p-8 lg:p-10 max-w-7xl mx-auto space-y-8 select-none">

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div>
            <span class="px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-extrabold uppercase">
                Immutable Security &amp; Operational Trail
            </span>
            <h1 class="text-3xl font-bold text-primary-dark mt-2">Platform Audit &amp; Security Logs</h1>
            <p class="text-sm text-text-muted mt-1">
                Every manual status override, account suspension/reactivation, shipping rate update, remittance verification, and platform setting change is permanently logged with IP Address, Actor, Old/New State, and Reason. Log entries cannot be edited or deleted.
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="bg-surface rounded-3xl border border-border-subtle p-5 shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3.5 items-end text-xs">
        <div>
            <label class="block font-bold text-text-muted uppercase text-[10px] mb-1">Actor Role</label>
            <select name="actor_type" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                @foreach(['all' => 'All Roles', 'Admin' => 'Admin', 'Logistics' => 'Logistics', 'Seller' => 'Seller', 'Courier' => 'Courier', 'Rider' => 'Rider', 'Buyer' => 'Buyer', 'System' => 'System'] as $k => $lbl)
                    <option value="{{ $k }}" {{ $actorType === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block font-bold text-text-muted uppercase text-[10px] mb-1">Action Type</label>
            <select name="action_type" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-semibold">
                <option value="all">All Actions</option>
                @foreach($distinctActions as $act)
                    <option value="{{ $act }}" {{ $actionFilter === $act ? 'selected' : '' }}>{{ $act }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block font-bold text-text-muted uppercase text-[10px] mb-1">IP Address</label>
            <input type="text" name="ip" value="{{ $ipSearch }}" placeholder="e.g. 127.0.0.1" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface font-mono">
        </div>

        <div>
            <label class="block font-bold text-text-muted uppercase text-[10px] mb-1">From Date</label>
            <input type="date" name="from_date" value="{{ $fromDate }}" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
        </div>

        <div>
            <label class="block font-bold text-text-muted uppercase text-[10px] mb-1">To Date</label>
            <input type="date" name="to_date" value="{{ $toDate }}" class="w-full p-2.5 rounded-xl border border-border-subtle bg-surface">
        </div>

        <div class="flex items-center gap-2">
            <input type="text" name="search" value="{{ $search }}" placeholder="Ref #, actor, reason..." class="flex-1 p-2.5 rounded-xl border border-border-subtle bg-surface">
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary-dark text-white font-bold cursor-pointer shrink-0">Filter</button>
        </div>
    </form>

    <div class="bg-surface rounded-3xl border border-border-subtle shadow-xs overflow-hidden">
        @if($logs->isEmpty())
            <div class="p-14 text-center text-xs text-text-muted">
                No audit log entries match the selected filters.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle bg-surface-subtle/60 text-text-muted uppercase text-[11px] font-bold">
                            <th class="py-3.5 px-5">Timestamp &amp; IP</th>
                            <th class="py-3.5 px-4">Actor &amp; Role</th>
                            <th class="py-3.5 px-4">Action &amp; Target</th>
                            <th class="py-3.5 px-4">Old &rarr; New State</th>
                            <th class="py-3.5 px-5">Reason &amp; User-Agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @foreach($logs as $log)
                            <tr class="hover:bg-surface-subtle/30">
                                <td class="py-4 px-5 align-top">
                                    <div class="font-bold text-text-main">{{ $log->occurred_at?->format('M d, Y h:i:s A') }}</div>
                                    <div class="font-mono text-[11px] text-primary-dark mt-0.5">IP: {{ $log->ip_address }}</div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <span class="px-2 py-0.5 rounded-full bg-brand-light/50 text-primary-dark font-extrabold text-[10px] uppercase">{{ $log->actor_type }}</span>
                                    <div class="font-bold text-text-main mt-1">{{ $log->actor_name ?: 'ID #' . $log->actor_id }}</div>
                                </td>
                                <td class="py-4 px-4 align-top">
                                    <div class="font-mono font-bold text-primary">{{ $log->action }}</div>
                                    <div class="text-text-muted mt-0.5">
                                        {{ $log->target_type }}: <strong>{{ $log->target_reference ?: '#' . $log->target_id }}</strong>
                                    </div>
                                </td>
                                <td class="py-4 px-4 align-top font-mono text-[11px] space-y-1 max-w-xs">
                                    @if(!empty($log->old_values))
                                        <div class="p-1.5 rounded bg-red-50 text-red-800 border border-red-200 truncate" title="{{ json_encode($log->old_values) }}">
                                            Old: {{ json_encode($log->old_values) }}
                                        </div>
                                    @endif
                                    @if(!empty($log->new_values))
                                        <div class="p-1.5 rounded bg-green-50 text-green-800 border border-green-200 truncate" title="{{ json_encode($log->new_values) }}">
                                            New: {{ json_encode($log->new_values) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-4 px-5 align-top max-w-sm">
                                    @if($log->reason)
                                        <p class="font-semibold text-text-main">"{{ $log->reason }}"</p>
                                    @endif
                                    <p class="text-[10px] text-text-muted truncate mt-1" title="{{ $log->user_agent }}">{{ $log->user_agent }}</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-border-subtle">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection