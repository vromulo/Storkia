<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationOtpMail;
use App\Models\EmailChangeRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function updateName(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'last_name'  => ['required', 'string', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
        ]);

        $user = $request->user();
        $user->update([
            'first_name' => ucwords(strtolower(trim($validated['first_name']))),
            'last_name'  => ucwords(strtolower(trim($validated['last_name']))),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Name updated successfully.',
            'data'    => $user->fresh()
        ]);
    }

    public function updatePhone(Request $request)
    {
        $cleanContact = preg_replace('/\D/', '', (string) $request->input('contact_no'));
        if (str_starts_with($cleanContact, '63')) $cleanContact = substr($cleanContact, 2);
        $cleanContact = ltrim($cleanContact, '0');
        
        $request->merge(['clean_contact' => $cleanContact]);
        $request->validate(['clean_contact' => ['required', 'regex:/^9\d{9}$/']]);

        $formattedNumber = '+63' . $cleanContact;
        $user = $request->user();

        if (User::where('contact_no', $formattedNumber)->where('id', '!=', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Phone number is already registered.'], 422);
        }

        $user->update(['contact_no' => $formattedNumber]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number updated successfully.',
            'data'    => $user->fresh()
        ]);
    }

    public function requestEmailOtp(Request $request)
    {
        $user = $request->user();
        $newEmail = strtolower(trim((string) $request->input('email')));
        
        if ($newEmail === strtolower(trim($user->email))) {
            return response()->json(['success' => false, 'message' => 'This is already your current email.'], 422);
        }

        if (User::whereRaw('LOWER(TRIM(email)) = ?', [$newEmail])->where('id', '!=', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Email address is already in use.'], 422);
        }

        $existing = EmailChangeRequest::where('user_id', $user->id)->where('new_email', $newEmail)->first();
        if ($existing && $existing->secondsUntilResend() > 0) {
            return response()->json(['success' => false, 'message' => "Please wait {$existing->secondsUntilResend()}s."], 429);
        }

        $code = EmailChangeRequest::generateCode();
        try {
            Mail::to($newEmail)->send(new RegistrationOtpMail($code, EmailChangeRequest::CODE_TTL_MINUTES));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send email.'], 500);
        }

        EmailChangeRequest::updateOrCreate(
            ['user_id' => $user->id],
            [
                'new_email' => $newEmail, 'code_hash' => Hash::make($code),
                'attempts' => 0, 'last_sent_at' => now(),
                'code_expires_at' => now()->addMinutes(EmailChangeRequest::CODE_TTL_MINUTES),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Verification code sent.']);
    }

    public function verifyEmailOtp(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        $changeRequest = EmailChangeRequest::where('user_id', $user->id)->first();

        if (!$changeRequest || $changeRequest->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Code expired or invalid.'], 422);
        }

        if (!Hash::check($request->code, $changeRequest->code_hash)) {
            $changeRequest->increment('attempts');
            return response()->json(['success' => false, 'message' => 'Invalid code.'], 422);
        }

        $user->update(['email' => strtolower(trim($changeRequest->new_email))]);
        $changeRequest->delete();

        return response()->json(['success' => true, 'message' => 'Email updated successfully.', 'data' => $user->fresh()]);
    }

    public function requestPasswordOtp(Request $request)
    {
        $user = $request->user();
        $cacheKey = "api_pwd_otp_{$user->id}";

        if (Cache::has($cacheKey)) {
            return response()->json(['success' => false, 'message' => 'Please wait before requesting a new code.'], 429);
        }

        $code = (string) random_int(100000, 999999);
        try {
            Mail::to($user->email)->send(new RegistrationOtpMail($code, 10));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send email.'], 500);
        }

        Cache::put($cacheKey, ['code_hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(10));

        return response()->json(['success' => true, 'message' => 'Verification code sent.']);
    }

    public function verifyPasswordOtp(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        $cacheKey = "api_pwd_otp_{$user->id}";
        $data = Cache::get($cacheKey);

        if (!$data) return response()->json(['success' => false, 'message' => 'Code expired or missing.'], 422);

        if (!Hash::check($request->code, $data['code_hash'])) {
            $data['attempts']++;
            Cache::put($cacheKey, $data, now()->addMinutes(10));
            return response()->json(['success' => false, 'message' => 'Invalid code.'], 422);
        }

        Cache::forget($cacheKey);
        $unlockToken = Str::random(40);
        Cache::put("api_pwd_unlock_{$user->id}", $unlockToken, now()->addMinutes(15));

        return response()->json(['success' => true, 'unlock_token' => $unlockToken]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $validToken = Cache::get("api_pwd_unlock_{$user->id}");

        if (!$validToken || $validToken !== $request->input('unlock_token')) {
            return response()->json(['success' => false, 'message' => 'Verification expired.'], 403);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[\W_]/'],
            'password_confirmation' => ['required', 'same:password'],
        ]);

        $user->update(['password' => Hash::make($validated['password']), 'password_changed_at' => now()]);
        Cache::forget("api_pwd_unlock_{$user->id}");

        return response()->json(['success' => true, 'message' => 'Password updated successfully.']);
    }
}