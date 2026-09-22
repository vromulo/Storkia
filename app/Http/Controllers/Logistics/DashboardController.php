<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsApplication;
use App\Models\LogisticsProfile;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $profile = LogisticsProfile::where('user_id', Auth::id())->first();
        $latestApp = LogisticsApplication::where('user_id', Auth::id())->latest('version')->first();

        return view('logistics.logistics-dashboard', compact('profile', 'latestApp'));
    }

    public function showReapply()
    {
        $latestApp = LogisticsApplication::where('user_id', Auth::id())->latest('version')->first();

        if (! $latestApp || $latestApp->status !== 'rejected') {
            return redirect()->route('logistics.logistics-dashboard');
        }

        return view('logistics.auth.reapply', compact('latestApp'));
    }
}