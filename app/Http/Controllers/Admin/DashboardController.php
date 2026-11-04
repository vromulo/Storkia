<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Livewire\Admin\Dashboard as LivewireAdminDashboard;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = (string) $request->query('period', '7d');
        if (! in_array($period, ['today', '7d', '30d', 'year'], true)) {
            $period = '7d';
        }
        $offset = max(0, (int) $request->query('offset', 0));
        $refDate = trim((string) $request->query('ref_date', '')) ?: null;

        $payload = LivewireAdminDashboard::buildDashboardPayload($period, $offset, $refDate);
        $payload['chartMetric'] = (string) $request->query('metric', 'gmv');

        return view('admin.dashboard', $payload);
    }
}