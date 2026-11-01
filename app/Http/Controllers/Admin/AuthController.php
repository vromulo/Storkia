<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminOtpMail;
use App\Models\Admin;
use App\Services\SessionRevocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected SessionRevocationService $revocation)
    {
    }

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

        $normalizedEmail = strtolower(trim((string) $request->input('email')));

        $admin = Admin::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our administrative records.',
            ]);
        }

        // A code was emailed moments ago and is still valid: do not mail
        // another one, just take the admin back to the verification form.
        if ($this->hasPendingOtp($admin) && RateLimiter::tooManyAttempts($this->sendKey($admin), 1)) {
            $this->startPreAuth($request, $admin);

            return redirect()->route('admin.otp.form')
                ->with('status', 'A verification code was sent a moment ago. Please check your email.');
        }

        $this->issueOtp($admin);
        $this->startPreAuth($request, $admin);

        return redirect()->route('admin.otp.form');
    }

    public function showOtpForm(Request $request)
    {
        if (! $request->session()->has('admin_auth_id')) {
            return redirect()->route('admin.login');
        }

        if (! $this->pendingAdmin($request)) {
            return $this->restartLogin($request, 'Your verification session has expired. Please sign in again.');
        }

        return view('admin.auth.otp');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'code' => ['required', 'numeric', 'digits:6'],
        ]);

        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return $this->restartLogin($request, 'Your verification session has expired. Please sign in again.');
        }

        $verifyKey = $this->verifyKey($admin);
        $maxAttempts = (int) config('security.admin_otp.max_attempts', 5);

        if (RateLimiter::tooManyAttempts($verifyKey, $maxAttempts)) {
            return $this->abandonOtp($request, $admin);
        }

        if (! $this->hasPendingOtp($admin) || ! Hash::check((string) $request->input('code'), $admin->otp_code)) {
            RateLimiter::hit($verifyKey, $this->windowSeconds());

            // Too many wrong guesses: this code is burned, start over.
            if (RateLimiter::tooManyAttempts($verifyKey, $maxAttempts)) {
                return $this->abandonOtp($request, $admin);
            }

            throw ValidationException::withMessages([
                'code' => 'The provided verification code is invalid or has expired.',
            ]);
        }

        $admin->update([
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        RateLimiter::clear($verifyKey);
        RateLimiter::clear($this->sendKey($admin));

        // "Remember me" is no longer offered on the admin login. Rotating the
        // remember token here also ends any older remember-me cookie, which
        // would otherwise skip this OTP step.
        $this->revocation->revokeActor($admin);

        Auth::guard('admin')->login($admin);
        $this->forgetPreAuth($request);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function resendOtp(Request $request)
    {
        if (! $request->session()->has('admin_auth_id')) {
            return redirect()->route('admin.login');
        }

        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return $this->restartLogin($request, 'Your verification session has expired. Please sign in again.');
        }

        $sendKey = $this->sendKey($admin);

        if (RateLimiter::tooManyAttempts($sendKey, 1)) {
            $seconds = RateLimiter::availableIn($sendKey);

            return back()->withErrors([
                'code' => "Please wait {$seconds} seconds before requesting another code.",
            ]);
        }

        $this->issueOtp($admin);
        $this->startPreAuth($request, $admin);

        return back()->with('status', 'A new verification code has been sent.');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Generates a fresh code, stores only its hash, starts the resend
     * cooldown, resets the wrong-guess counter and emails the code.
     */
    protected function issueOtp(Admin $admin): void
    {
        $otp = (string) random_int(100000, 999999);

        $admin->update([
            'otp_code' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes($this->windowMinutes()),
        ]);

        RateLimiter::clear($this->verifyKey($admin));
        RateLimiter::hit($this->sendKey($admin), (int) config('security.admin_otp.resend_cooldown_seconds', 60));

        Mail::to($admin->email)->send(new AdminOtpMail($otp));
    }

    protected function hasPendingOtp(Admin $admin): bool
    {
        return ! empty($admin->otp_code)
            && $admin->otp_expires_at !== null
            && $admin->otp_expires_at->isFuture();
    }

    /**
     * Burns the current code and sends the admin back to the password step.
     */
    protected function abandonOtp(Request $request, Admin $admin): RedirectResponse
    {
        $admin->update([
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        RateLimiter::clear($this->verifyKey($admin));
        RateLimiter::clear($this->sendKey($admin));

        return $this->restartLogin($request, 'Too many incorrect verification codes. Please sign in again.');
    }

    protected function restartLogin(Request $request, string $message): RedirectResponse
    {
        $this->forgetPreAuth($request);

        return redirect()->route('admin.login')->withErrors(['email' => $message]);
    }

    /**
     * The admin who passed the password step in this session, or null when
     * there is none, it has expired, or the admin no longer exists.
     */
    protected function pendingAdmin(Request $request): ?Admin
    {
        $adminId = $request->session()->get('admin_auth_id');
        $startedAt = (int) $request->session()->get('admin_auth_at', 0);

        if (! $adminId || $startedAt <= 0 || (time() - $startedAt) > $this->windowSeconds()) {
            return null;
        }

        return Admin::find($adminId);
    }

    protected function startPreAuth(Request $request, Admin $admin): void
    {
        $request->session()->put('admin_auth_id', $admin->id);
        $request->session()->put('admin_auth_at', time());
    }

    protected function forgetPreAuth(Request $request): void
    {
        $request->session()->forget(['admin_auth_id', 'admin_auth_at', 'admin_auth_remember']);
    }

    protected function windowMinutes(): int
    {
        return max(1, (int) config('security.admin_otp.pre_auth_minutes', 10));
    }

    protected function windowSeconds(): int
    {
        return $this->windowMinutes() * 60;
    }

    protected function sendKey(Admin $admin): string
    {
        return 'admin-otp-send:'.$admin->id;
    }

    protected function verifyKey(Admin $admin): string
    {
        return 'admin-otp-verify:'.$admin->id;
    }
}