<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot password" for buyer, seller and logistics (main / branch hub)
 * accounts, which all live in the users table and use the "users" password
 * broker from config/auth.php.
 *
 * Not covered on purpose:
 *  - Admin: recovery is manual, see the note on the admin login page.
 *  - Riders and couriers: their accounts are created and managed by their
 *    logistics hub, which resets the password for them.
 *
 * Security notes:
 *  - The answer to "send me a link" is always the same, whether the email
 *    exists, is archived (deleted) or was throttled, so the form cannot be
 *    used to find out who has an account. The email itself is sent after the
 *    response, so the response time does not give it away either.
 *  - Resetting the password runs through the normal User update, so
 *    AccountSecurityObserver ends every session, remember-me cookie and API
 *    token of that account.
 */
class PasswordResetController extends Controller
{
    protected const SENT_MESSAGE = 'If an account exists for that email, we have sent a link to reset the password. The link works for 60 minutes.';

    protected const INVALID_LINK_MESSAGE = 'This password reset link is invalid or has expired. Please request a new one.';

    public function showRequestForm(Request $request)
    {
        return view('auth.forgot-password', [
            'loginUrl' => $this->loginUrlForPortal($request->query('portal')),
        ]);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim((string) $request->input('email')));

        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        if ($user && ! $user->isArchived()) {
            // After the response is sent, so a real account and an unknown one
            // answer equally fast.
            defer(function () use ($user) {
                Password::broker()->sendResetLink(['email' => $user->email]);
            });
        }

        return back()->with('status', self::SENT_MESSAGE);
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                function ($attribute, $value, $fail) {
                    if (! preg_match('/[A-Z]/', $value)) {
                        $fail('Password must contain at least 1 uppercase letter.');
                    }
                    if (! preg_match('/[0-9]/', $value)) {
                        $fail('Password must contain at least 1 number.');
                    }
                    if (! preg_match('/[\W_]/', $value)) {
                        $fail('Password must contain at least 1 special character.');
                    }
                },
            ],
        ]);

        $email = strtolower(trim($validated['email']));

        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        // Same message for "no such account", "deleted account" and "bad token".
        if (! $user || $user->isArchived()) {
            throw ValidationException::withMessages(['email' => self::INVALID_LINK_MESSAGE]);
        }

        $status = Password::broker()->reset(
            [
                'email'                 => $user->email,
                'password'              => $validated['password'],
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token'                 => $validated['token'],
            ],
            function (User $resetUser, string $password) {
                $resetUser->forceFill([
                    'password'            => Hash::make($password),
                    'password_changed_at' => now(),
                    'remember_token'      => Str::random(60),
                ])->save();

                event(new PasswordReset($resetUser));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => $status === Password::RESET_THROTTLED
                    ? 'Please wait a moment before trying again.'
                    : self::INVALID_LINK_MESSAGE,
            ]);
        }

        return redirect()
            ->to($this->loginUrlForRole($user->role))
            ->with('status', 'Your password has been reset. Sign in with your new password.');
    }

    protected function loginUrlForPortal(?string $portal): string
    {
        return match (strtolower((string) $portal)) {
            'seller'    => route('seller.login'),
            'logistics' => route('logistics.login'),
            default     => route('login'),
        };
    }

    protected function loginUrlForRole(?string $role): string
    {
        return match ($role) {
            'Seller'    => route('seller.login'),
            'Logistics' => route('logistics.login'),
            default     => route('login'),
        };
    }
}