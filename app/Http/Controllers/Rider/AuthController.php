<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showRegister()
    {
        return redirect()->route('logistics.login');
    }

    public function downloadApp()
    {
        return view('rider.download-app');
    }

    public function logout(Request $request)
    {
        Auth::guard('rider')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logistics.login');
    }
}