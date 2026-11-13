<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationOtpMail;
use App\Models\EmailChangeRequest;
use App\Models\SellerGcashAccount;
use App\Models\User;
use App\Services\OtpService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class StoreSettingsController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403, 'Approved seller profile required.');
        }

        $gcashAccount = SellerGcashAccount::where('seller_profile_id', $profile->id)->first();

        return view('seller.settings.store-settings', compact('user', 'profile', 'gcashAccount'));
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $request->validate([
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.mimetypes' => 'Store photo must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'photo.max'       => 'Store photo must not exceed 5 MB.',
        ]);

        $file = $request->file('photo');
        $filename = 'seller_logos/store_' . $profile->id . '_' . uniqid('', true) . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->cover(500, 500)->toWebp(84);
            Storage::disk('public')->put($filename, (string) $encoded);
        } catch (\Throwable $e) {
            $filename = $file->store('seller_logos', 'public');
        }

        if ($profile->logo_path && Storage::disk('public')->exists($profile->logo_path)) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        $profile->update(['logo_path' => $filename]);

        return response()->json([
            'success'   => true,
            'status'    => 'success',
            'message'   => 'Store photo updated and optimized to WebP.',
            'photo_url' => asset('storage/' . $filename),
        ]);
    }

    public function removePhoto(Request $request): JsonResponse
    {
        $profile = $request->user()?->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        if ($profile->logo_path && Storage::disk('public')->exists($profile->logo_path)) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        $profile->update(['logo_path' => null]);

        return response()->json([
            'success'   => true,
            'status'    => 'success',
            'message'   => 'Store photo removed.',
            'photo_url' => '',
        ]);
    }

    public function updateStoreName(Request $request): JsonResponse
    {
        $profile = $request->user()?->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $request->merge([
            'business_name' => trim((string) $request->input('business_name', '')),
        ]);

        $validated = $request->validate([
            'business_name' => [
                'required',
                'string',
                'min:3',
                'max:80',
                Rule::unique('seller_profiles', 'business_name')->ignore($profile->id),
            ],
        ], [
            'business_name.min'    => 'Store name must be at least 3 characters.',
            'business_name.unique' => 'This store name is already taken by another merchant.',
        ]);

        $profile->update([
            'business_name' => $validated['business_name'],
        ]);

        return response()->json([
            'success'       => true,
            'status'        => 'success',
            'message'       => 'Store name updated successfully.',
            'business_name' => $profile->business_name,
        ]);
    }

    public function updateGcashAccount(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $digits = preg_replace('/\D/', '', (string) $request->input('gcash_number', ''));
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        $request->merge([
            'account_name' => trim((string) $request->input('account_name', '')),
            'gcash_number' => $digits,
        ]);

        $validated = $request->validate([
            'account_name' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'gcash_number' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'qr_image'     => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active'    => ['nullable', 'boolean'],
        ], [
            'account_name.regex' => 'GCash Account Name may only contain letters, spaces, hyphens, and periods.',
            'gcash_number.regex' => 'Please enter a valid 11-digit GCash mobile number starting with 09.',
        ]);

        $existing = SellerGcashAccount::where('seller_profile_id', $profile->id)->first();
        $qrPath = $existing?->qr_image_path;

        if ($request->hasFile('qr_image')) {
            $file = $request->file('qr_image');
            $filename = 'seller_gcash_qr/gcash_qr_' . $profile->id . '_' . uniqid('', true) . '.webp';

            try {
                $manager = new ImageManager(new Driver());
                $encoded = $manager->read($file->getRealPath())->scaleDown(1000, 1000)->toWebp(85);
                Storage::disk('public')->put($filename, (string) $encoded);
                $qrPath = $filename;
            } catch (\Throwable $e) {
                $qrPath = $file->store('seller_gcash_qr', 'public');
            }
        }

        SellerGcashAccount::updateOrCreate(
            ['seller_profile_id' => $profile->id],
            [
                'user_id'       => $user->id,
                'account_name'  => $validated['account_name'],
                'gcash_number'  => $validated['gcash_number'],
                'qr_image_path' => $qrPath,
                'is_active'     => (bool) ($validated['is_active'] ?? true),
                'confirmed_at'  => now(),
            ]
        );

        return back()->with('success', 'Payout GCash account details saved.');
    }

    public function removeGcashQr(Request $request)
    {
        $profile = $request->user()?->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $gcash = SellerGcashAccount::where('seller_profile_id', $profile->id)->first();
        if ($gcash && $gcash->qr_image_path) {
            if (Storage::disk('public')->exists($gcash->qr_image_path)) {
                Storage::disk('public')->delete($gcash->qr_image_path);
            }
            $gcash->update(['qr_image_path' => null]);
        }

        return back()->with('success', 'Uploaded GCash QR image removed.');
    }

    public function viewDocument(Request $request, string $type)
    {
        $profile = $request->user()?->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $path = match ($type) {
            'id'     => $profile->id_path,
            'permit' => $profile->permit_path,
            default  => null,
        };

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Document file not found.');
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    public function updateName(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->merge([
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name'  => trim((string) $request->input('last_name', '')),
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'last_name'  => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
        ], [
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            'last_name.regex'  => 'Last name may only contain letters, spaces, hyphens, apostrophes, and periods.',
        ]);

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
        ]);

        return response()->json([
            'success'    => true,
            'status'     => 'success',
            'message'    => 'Owner name updated.',
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'full_name'  => $user->full_name,
        ]);
    }

    public function updatePhone(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $digits = preg_replace('/\D/', '', (string) $request->input('contact_no', ''));
        if (str_starts_with($digits, '63') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }
        $cleanDigits = ltrim($digits, '0');
        $normalizedPhone = '0' . $cleanDigits;

        $request->merge([
            'contact_no_clean' => $cleanDigits,
            'contact_no_norm'  => $normalizedPhone,
        ]);

        $request->validate([
            'contact_no_clean' => ['required', 'string', 'regex:/^9\d{9}$/'],
            'contact_no_norm'  => [
                'required',
                Rule::unique('users', 'contact_no')->ignore($user->id),
            ],
        ], [
            'contact_no_clean.regex' => 'Please enter a valid 10-digit Philippine mobile number starting with 9.',
            'contact_no_norm.unique' => 'This mobile number is already registered to another account.',
        ]);

        $user->update(['contact_no' => $normalizedPhone]);
        $profile?->update(['contact_no' => $normalizedPhone]);

        $fullDigits = '63' . $cleanDigits;
        $maskedPhone = substr($fullDigits, 0, 5) . '****' . substr($fullDigits, -3);
        $formattedPhone = substr($cleanDigits, 0, 3) . ' ' . substr($cleanDigits, 3, 3) . ' ' . substr($cleanDigits, 6, 4);

        return response()->json([
            'success'         => true,
            'status'          => 'success',
            'message'         => 'Store contact number updated.',
            'phone_raw'       => $cleanDigits,
            'formatted_phone' => $formattedPhone,
            'masked_phone'    => $maskedPhone,
            'has_phone'       => true,
        ]);
    }

    public function requestEmailOtp(Request $request): JsonResponse
    {
        $user = $request->user();
        $newEmail = strtolower(trim((string) $request->input('email', '')));
        $request->merge(['email' => $newEmail]);

        $request->validate([
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:120',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], [
            'email.unique' => 'This email address is already registered to another account.',
        ]);

        if (strtolower((string) $user->email) === $newEmail) {
            throw ValidationException::withMessages([
                'email' => 'Please enter a different email address from your current one.',
            ]);
        }

        $existing = EmailChangeRequest::where('user_id', $user->id)->first();
        if ($existing && $existing->secondsUntilResend() > 0) {
            $remaining = $existing->secondsUntilResend();
            return response()->json([
                'success'  => false,
                'message'  => "Please wait {$remaining}s before requesting another code.",
                'cooldown' => $remaining,
            ], 429);
        }

        $code = EmailChangeRequest::generateCode();

        try {
            $this->otpService->deliverMailable($newEmail, new RegistrationOtpMail($code, EmailChangeRequest::CODE_TTL_MINUTES));
        } catch (\Throwable $e) {
            Log::error('Seller Email Change OTP Mail Error: ' . $e->getMessage());
            throw ValidationException::withMessages([
                'email' => 'Unable to send verification email right now. Please try again shortly.',
            ]);
        }

        EmailChangeRequest::updateOrCreate(
            ['user_id' => $user->id],
            [
                'new_email'       => $newEmail,
                'code_hash'       => Hash::make($code),
                'last_sent_at'    => now(),
                'code_expires_at' => now()->addMinutes(EmailChangeRequest::CODE_TTL_MINUTES),
                'attempts'        => 0,
            ]
        );

        [$name, $domain] = explode('@', $newEmail, 2);
        $masked = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 2, 4)) . substr($name, -1) . '@' . $domain;

        return response()->json([
            'success'      => true,
            'status'       => 'otp_sent',
            'masked_email' => $masked,
            'cooldown'     => EmailChangeRequest::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $user = $request->user();
        $otpInput = preg_replace('/\D/', '', (string) ($request->input('otp') ?? $request->input('code') ?? ''));

        if (strlen($otpInput) !== 6) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid 6-digit verification code.'], 422);
        }

        $changeReq = EmailChangeRequest::where('user_id', $user->id)->first();
        if (! $changeReq || $changeReq->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Verification code has expired.'], 422);
        }

        if ($changeReq->attempts >= EmailChangeRequest::MAX_ATTEMPTS) {
            return response()->json(['success' => false, 'message' => 'Maximum verification attempts exceeded.'], 422);
        }

        if (! Hash::check($otpInput, $changeReq->code_hash)) {
            $changeReq->increment('attempts');
            return response()->json(['success' => false, 'message' => 'Invalid verification code.'], 422);
        }

        $newEmail = strtolower(trim($changeReq->new_email));
        if (User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Email already in use.'], 422);
        }

        $user->update([
            'email'             => $newEmail,
            'email_verified_at' => now(),
        ]);
        $changeReq->delete();

        [$name, $domain] = explode('@', $newEmail, 2);
        $masked = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 2, 4)) . substr($name, -1) . '@' . $domain;

        return response()->json([
            'success'      => true,
            'status'       => 'success',
            'message'      => 'Email address updated successfully.',
            'email'        => $newEmail,
            'masked_email' => $masked,
        ]);
    }

    public function requestPasswordOtp(Request $request): JsonResponse
    {
        $user = $request->user();
        $code = EmailChangeRequest::generateCode();

        try {
            $this->otpService->deliverMailable((string) $user->email, new RegistrationOtpMail($code, EmailChangeRequest::CODE_TTL_MINUTES));
        } catch (\Throwable $e) {
            Log::error('Seller Password OTP Mail Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to send verification code right now. Please try again shortly.',
            ], 500);
        }

        $request->session()->put('seller_password_otp', [
            'hash'       => Hash::make($code),
            'expires_at' => now()->addMinutes(EmailChangeRequest::CODE_TTL_MINUTES)->timestamp,
            'attempts'   => 0,
            'verified'   => false,
        ]);

        [$name, $domain] = explode('@', $user->email, 2);
        $masked = substr($name, 0, 1) . str_repeat('*', max(strlen($name) - 2, 4)) . substr($name, -1) . '@' . $domain;

        return response()->json([
            'success'      => true,
            'status'       => 'otp_sent',
            'masked_email' => $masked,
            'cooldown'     => EmailChangeRequest::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public function verifyPasswordOtp(Request $request): JsonResponse
    {
        $otpInput = preg_replace('/\D/', '', (string) ($request->input('otp') ?? $request->input('code') ?? ''));

        if (strlen($otpInput) !== 6) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid 6-digit verification code.'], 422);
        }

        $otpData = $request->session()->get('seller_password_otp');

        if (! $otpData || now()->timestamp > ($otpData['expires_at'] ?? 0)) {
            return response()->json(['success' => false, 'message' => 'Verification code has expired.'], 422);
        }

        if (($otpData['attempts'] ?? 0) >= EmailChangeRequest::MAX_ATTEMPTS) {
            return response()->json(['success' => false, 'message' => 'Too many incorrect attempts.'], 422);
        }

        if (! Hash::check($otpInput, $otpData['hash'])) {
            $otpData['attempts'] = ($otpData['attempts'] ?? 0) + 1;
            $request->session()->put('seller_password_otp', $otpData);
            return response()->json(['success' => false, 'message' => 'Invalid verification code.'], 422);
        }

        $otpData['verified'] = true;
        $request->session()->put('seller_password_otp', $otpData);

        return response()->json([
            'success'  => true,
            'verified' => true,
            'status'   => 'verified',
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $otpData = $request->session()->get('seller_password_otp');

        if (! $otpData || empty($otpData['verified'])) {
            return response()->json(['success' => false, 'message' => 'Please verify OTP first.'], 403);
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $user->update([
            'password'            => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        $request->session()->forget('seller_password_otp');

        return response()->json([
            'success'               => true,
            'status'                => 'success',
            'message'               => 'Password updated successfully.',
            'password_last_updated' => $user->fresh()->formattedPasswordLastUpdated(),
        ]);
    }
}