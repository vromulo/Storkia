<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('rider.auth.register');
    }

    public function downloadApp()
    {
        return view('rider.download-app');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('rider_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logistics.login');
    }
}