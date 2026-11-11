<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    protected const SENT_MESSAGE = 'If an account exists for that email, we have sent a link to reset the password. The link works for 60 minutes.';

    protected const INVALID_LINK_MESSAGE = 'This password reset link is invalid or has expired. Please request a new one.';

    public function forgot(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim((string) $request->input('email')));

        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        if ($user && ! $user->isArchived()) {
            defer(function () use ($user) {
                Password::broker()->sendResetLink(['email' => $user->email]);
            });
        }

        return response()->json([
            'success' => true,
            'message' => self::SENT_MESSAGE,
        ]);
    }

    public function reset(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset. Sign in with your new password.',
        ]);
    }
}