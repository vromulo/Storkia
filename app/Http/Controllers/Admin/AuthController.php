<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Mail\RegistrationOtpMail;
use App\Models\Admin;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    public function showLogin()
    {
        return view()->first(['admin.auth.login', 'admin.login']);
    }

    public function showLoginForm()
    {
        return $this->showLogin();
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $normalizedEmail = strtolower(trim($credentials['email']));

        /** @var Admin|null $admin */
        $admin = Admin::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->first();

        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided administrator credentials do not match our records.',
            ]);
        }

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $admin->update([
            'otp_code'       => Hash::make($otpCode),
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $request->session()->put([
            'admin_2fa_id'       => $admin->id,
            'admin_2fa_remember' => $request->boolean('remember'),
        ]);

        try {
            $mailable = class_exists(OtpMail::class)
                ? new OtpMail($otpCode, $admin->name ?? 'Admin')
                : new RegistrationOtpMail($otpCode, 10);

            $this->otpService->deliverMailable($admin->email, $mailable);
        } catch (\Throwable $e) {
            Log::error('Admin 2FA Mail Error: ' . $e->getMessage());
        }

        if (
            view()->exists('admin.auth.otp')
            || view()->exists('admin.auth.verify-2fa')
            || view()->exists('admin.verify-2fa')
        ) {
            return redirect()->route('admin.verify-2fa');
        }

        Auth::guard('admin')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function showVerify2fa(Request $request)
    {
        $adminId = $request->session()->get('admin_2fa_id');
        if (! $adminId) {
            return redirect()->route('admin.login');
        }

        $admin = Admin::find($adminId);
        if (! $admin) {
            $request->session()->forget(['admin_2fa_id', 'admin_2fa_remember']);
            return redirect()->route('admin.login');
        }

        return view()->first(
            ['admin.auth.otp', 'admin.auth.verify-2fa', 'admin.verify-2fa'],
            compact('admin')
        );
    }

    public function verify2fa(Request $request)
    {
        $adminId = $request->session()->get('admin_2fa_id');
        if (! $adminId) {
            return redirect()->route('admin.login');
        }

        /** @var Admin|null $admin */
        $admin = Admin::find($adminId);
        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $rawCode = preg_replace('/\D/', '', (string) ($request->input('otp') ?? $request->input('code') ?? $request->input('otp_code') ?? ''));

        if (strlen($rawCode) !== 6) {
            throw ValidationException::withMessages([
                'otp'  => 'Please enter a valid 6-digit verification code.',
                'code' => 'Please enter a valid 6-digit verification code.',
            ]);
        }

        if (! $admin->otp_expires_at || now()->gt($admin->otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp'  => 'This verification code has expired. Please request a new code.',
                'code' => 'This verification code has expired. Please request a new code.',
            ]);
        }

        $matches = Hash::check($rawCode, (string) $admin->otp_code) || hash_equals((string) $admin->otp_code, $rawCode);
        if (! $matches) {
            throw ValidationException::withMessages([
                'otp'  => 'Invalid verification code.',
                'code' => 'Invalid verification code.',
            ]);
        }

        $remember = (bool) $request->session()->pull('admin_2fa_remember', false);
        $request->session()->forget('admin_2fa_id');

        $admin->update([
            'otp_code'       => null,
            'otp_expires_at' => null,
        ]);

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function resend2fa(Request $request)
    {
        $adminId = $request->session()->get('admin_2fa_id');
        if (! $adminId) {
            return redirect()->route('admin.login');
        }

        $admin = Admin::find($adminId);
        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $admin->update([
            'otp_code'       => Hash::make($otpCode),
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        try {
            $mailable = class_exists(OtpMail::class)
                ? new OtpMail($otpCode, $admin->name ?? 'Admin')
                : new RegistrationOtpMail($otpCode, 10);

            $this->otpService->deliverMailable($admin->email, $mailable);
        } catch (\Throwable $e) {
            Log::error('Admin Resend 2FA Mail Error: ' . $e->getMessage());
        }

        return back()
            ->with('status', 'A new 6-digit verification code has been sent to your email.')
            ->with('success', 'A new 6-digit verification code has been sent to your email.');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}