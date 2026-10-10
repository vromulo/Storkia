<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationOtpMail;
use App\Models\EmailChangeRequest;
use App\Models\RegistrationOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AccountManagementController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return view('buyer.option.account.account-management', compact('user'));
    }

    public function updateName(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'last_name'  => ['required', 'string', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
        ], [
            'first_name.regex' => 'First name must contain only letters and spaces.',
            'last_name.regex'  => 'Last name must contain only letters and spaces.',
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $firstName = ucwords(strtolower(trim($validated['first_name'])));
        $lastName  = ucwords(strtolower(trim($validated['last_name'])));

        $user->update([
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'    => true,
                'message'    => 'Name updated successfully.',
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'full_name'  => trim("{$firstName} {$lastName}"),
            ]);
        }

        return redirect()
            ->route('user.account-management')
            ->with('success', 'Name updated successfully.');
    }

    public function requestEmailOtp(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $validated = $request->validate([
            'email' => [
                'required',
                'email:rfc,dns',
                'max:255',
                'unique:users,email,' . $user->id,
            ],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email'    => 'Please enter a valid email address.',
            'email.unique'   => 'This email address is already registered.',
        ]);

        $newEmail = strtolower(trim($validated['email']));

        if ($newEmail === strtolower($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'This is already your current email address.',
            ], 422);
        }

        $existing = EmailChangeRequest::where('user_id', $user->id)
            ->where('new_email', $newEmail)
            ->first();

        if ($existing && $existing->secondsUntilResend() > 0) {
            return response()->json([
                'success'  => false,
                'message'  => "Please wait {$existing->secondsUntilResend()}s before requesting a new code.",
                'cooldown' => $existing->secondsUntilResend(),
            ], 429);
        }

        $code = EmailChangeRequest::generateCode();

        try {
            Mail::to($newEmail)->send(new RegistrationOtpMail($code, EmailChangeRequest::CODE_TTL_MINUTES));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'We could not send the verification email right now. Please try again shortly.',
            ], 500);
        }

        EmailChangeRequest::updateOrCreate(
            ['user_id' => $user->id],
            [
                'new_email'       => $newEmail,
                'code_hash'       => Hash::make($code),
                'attempts'        => 0,
                'last_sent_at'    => now(),
                'code_expires_at' => now()->addMinutes(EmailChangeRequest::CODE_TTL_MINUTES),
            ]
        );

        // Build masked string for UI preview (e.g. l***3@gmail.com)
        $maskedEmail = $newEmail;
        if (str_contains($newEmail, '@')) {
            [$name, $domain] = explode('@', $newEmail, 2);
            $maskedEmail = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 2, 3)) . substr($name, -1) . '@' . $domain;
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Verification code sent.',
            'masked_email' => $maskedEmail,
            'cooldown'     => EmailChangeRequest::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public function verifyEmailOtp(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $changeRequest = EmailChangeRequest::where('user_id', $user->id)->first();

        if (! $changeRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No active email change request found. Please request a new code.',
            ], 422);
        }

        if ($changeRequest->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'The verification code has expired. Please request a new one.',
            ], 422);
        }

        if ($changeRequest->attempts >= EmailChangeRequest::MAX_ATTEMPTS) {
            return response()->json([
                'success' => false,
                'message' => 'Too many invalid attempts. Please request a new code.',
            ], 422);
        }

        if (! Hash::check($request->code, $changeRequest->code_hash)) {
            $changeRequest->increment('attempts');
            $remaining = max(0, EmailChangeRequest::MAX_ATTEMPTS - $changeRequest->attempts);
            return response()->json([
                'success' => false,
                'message' => "Invalid code. {$remaining} attempt(s) remaining.",
            ], 422);
        }

        // Final uniqueness check before applying
        if (User::where('email', $changeRequest->new_email)->where('id', '!=', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email address is already in use by another account.',
            ], 422);
        }

        // Apply new email
        $user->update(['email' => $changeRequest->new_email]);
        $updatedEmail = $user->email;
        $changeRequest->delete();

        // Format masked email for UI
        $maskedEmail = $updatedEmail;
        if (str_contains($updatedEmail, '@')) {
            [$name, $domain] = explode('@', $updatedEmail, 2);
            $maskedEmail = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 2, 4)) . substr($name, -1) . '@' . $domain;
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Email updated successfully.',
            'email'        => $updatedEmail,
            'masked_email' => $maskedEmail,
        ]);
    }

    /**
     * Send OTP to the user's current email before allowing password change
     */
    public function requestPasswordOtp(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $sessionKey = "pwd_otp_{$user->id}";
        $existing = session()->get($sessionKey);

        if ($existing && !empty($existing['last_sent_at'])) {
            $lastSentAt = \Carbon\Carbon::parse($existing['last_sent_at']);
            $unlockAt = $lastSentAt->copy()->addSeconds(60);

            if (now()->lt($unlockAt)) {
                $diff = (int) now()->diffInSeconds($unlockAt);
                return response()->json([
                    'success'  => false,
                    'message'  => "Please wait {$diff}s before requesting a new code.",
                    'cooldown' => $diff,
                ], 429);
            }
        }

        $code = (string) random_int(100000, 999999);

        try {
            Mail::to($user->email)->send(new RegistrationOtpMail($code, 10));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'We could not send the verification email right now. Please try again.',
            ], 500);
        }

        session()->put($sessionKey, [
            'code_hash'    => Hash::make($code),
            'attempts'     => 0,
            'last_sent_at' => now()->toDateTimeString(),
            'expires_at'   => now()->addMinutes(10)->toDateTimeString(),
        ]);

        $maskedEmail = $user->email;
        if (str_contains($user->email, '@')) {
            [$name, $domain] = explode('@', $user->email, 2);
            $maskedEmail = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 2, 3)) . substr($name, -1) . '@' . $domain;
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Verification code sent.',
            'masked_email' => $maskedEmail,
            'cooldown'     => 60,
        ]);
    }

    /**
     * Verify the 6-digit OTP code to unlock password form
     */
    public function verifyPasswordOtp(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $sessionKey = "pwd_otp_{$user->id}";
        $data = session()->get($sessionKey);

        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => 'No active verification code found. Please request a new code.',
            ], 422);
        }

        if (now()->isAfter($data['expires_at'])) {
            session()->forget($sessionKey);
            return response()->json([
                'success' => false,
                'message' => 'The verification code has expired. Please request a new one.',
            ], 422);
        }

        if ($data['attempts'] >= 5) {
            session()->forget($sessionKey);
            return response()->json([
                'success' => false,
                'message' => 'Too many invalid attempts. Please request a new code.',
            ], 422);
        }

        if (! Hash::check($request->code, $data['code_hash'])) {
            $data['attempts']++;
            session()->put($sessionKey, $data);
            $remaining = max(0, 5 - $data['attempts']);
            return response()->json([
                'success' => false,
                'message' => "Invalid code. {$remaining} attempt(s) remaining.",
            ], 422);
        }

        // Clean up OTP and issue temporary unlock token
        session()->forget($sessionKey);
        $token = Str::random(40);
        session()->put("pwd_unlock_token_{$user->id}", [
            'token'      => $token,
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'success'      => true,
            'unlock_token' => $token,
            'message'      => 'Identity verified successfully.',
        ]);
    }

    /**
     * Update Password via AJAX with full validation
     */
    public function updatePassword(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $tokenData = session()->get("pwd_unlock_token_{$user->id}");
        if (! $tokenData || $tokenData['token'] !== $request->input('unlock_token') || now()->isAfter($tokenData['expires_at'])) {
            return response()->json([
                'success' => false,
                'message' => 'Verification expired. Please verify your identity again.',
            ], 403);
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[A-Z]/', $value)) {
                        $fail('Password must contain at least 1 uppercase letter.');
                    }
                    if (!preg_match('/[0-9]/', $value)) {
                        $fail('Password must contain at least 1 number.');
                    }
                    if (!preg_match('/[\W_]/', $value)) {
                        $fail('Password must contain at least 1 special character.');
                    }
                },
            ],
            'password_confirmation' => ['required', 'same:password'],
        ], [
            'password.required'              => 'Please enter a new password.',
            'password.min'                   => 'Password must be at least 8 characters long.',
            'password_confirmation.required' => 'Please confirm your password.',
            'password_confirmation.same'     => 'The password confirmation does not match.',
        ]);

        $user->update([
            'password'            => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        session()->forget("pwd_unlock_token_{$user->id}");

        return response()->json([
            'success'             => true,
            'message'             => 'Password updated successfully.',
            'password_changed_at' => $user->password_changed_at->toISOString(),
            'formatted_time'      => $user->formattedPasswordLastUpdated(),
        ]);
    }
}