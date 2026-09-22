<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminOtpMail;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our administrative records.',
            ]);
        }

        $otp = (string) random_int(100000, 999999);

        $admin->update([
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $request->session()->put('admin_auth_id', $admin->id);
        $request->session()->put('admin_auth_remember', $request->filled('remember'));

        Mail::to($admin->email)->send(new AdminOtpMail($otp));

        return redirect()->route('admin.otp.form');
    }

    public function showOtpForm(Request $request)
    {
        if (! $request->session()->has('admin_auth_id')) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.otp');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'code' => ['required', 'numeric', 'digits:6'],
        ]);

        $adminId = $request->session()->get('admin_auth_id');
        $admin = Admin::find($adminId);

        if (! $admin || ! $admin->otp_expires_at || $admin->otp_expires_at->isPast() || ! Hash::check($request->code, $admin->otp_code)) {
            throw ValidationException::withMessages([
                'code' => 'The provided verification code is invalid or has expired.',
            ]);
        }

        $admin->update([
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        $remember = $request->session()->pull('admin_auth_remember', false);
        Auth::guard('admin')->login($admin, $remember);
        $request->session()->forget('admin_auth_id');
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function resendOtp(Request $request)
    {
        if (! $request->session()->has('admin_auth_id')) {
            return redirect()->route('admin.login');
        }

        $admin = Admin::find($request->session()->get('admin_auth_id'));

        $otp = (string) random_int(100000, 999999);
        $admin->update([
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($admin->email)->send(new AdminOtpMail($otp));

        return back()->with('status', 'A new verification code has been sent.');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}