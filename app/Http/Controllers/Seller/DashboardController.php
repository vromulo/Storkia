<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $profile = SellerProfile::where('user_id', Auth::id())->first();
        $latestApp = SellerApplication::where('user_id', Auth::id())->latest('version')->first();

        return view('pages.seller.seller-dashboard', compact('profile', 'latestApp'));
    }

    public function showReapply()
    {
        $latestApp = SellerApplication::where('user_id', Auth::id())->latest('version')->first();

        if (! $latestApp || $latestApp->status !== 'rejected') {
            return redirect()->route('seller.seller-dashboard');
        }

        return view('pages.seller.auth.reapply');
    }
}