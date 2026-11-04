@extends('layouts.admin', ['title' => 'Storkia Admin - Executive Dashboard'])

@section('content')
    @include('livewire.admin.dashboard', [
        'period'            => $period ?? request()->query('period', '7d'),
        'offset'            => $offset ?? max(0, (int) request()->query('offset', 0)),
        'refDate'           => $refDate ?? request()->query('ref_date', now()->toDateString()),
        'maxDate'           => $maxDate ?? now()->toDateString(),
        'canGoNext'         => $canGoNext ?? false,
        'periodLabel'       => $periodLabel ?? 'Last 7 Days',
        'dateRangeLabel'    => $dateRangeLabel ?? '',
        'kpis'              => $kpis ?? [],
        'roleCounts'        => $roleCounts ?? [],
        'chartSeries'       => $chartSeries ?? [],
        'recentOrders'      => $recentOrders ?? collect(),
        'recentWithdrawals' => $recentWithdrawals ?? collect(),
        'chartMetric'       => $chartMetric ?? 'gmv',
    ])
@endsection