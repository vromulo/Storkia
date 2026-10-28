<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationOtpMail;
use App\Models\EmailChangeRequest;
use App\Models\SellerGcashAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoreSettingsController extends Controller
{
    private function optimizeAndStoreImage($file, string $folder): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['svg', 'gif'])) {
            return $file->store($folder, 'public');
        }

        $filename = uniqid('store_') . '_' . time() . '.webp';
        $path = $folder . '/' . $filename;

        $manager = ImageManager::usingDriver(Driver::class);
        $image = $manager->decode($file->getRealPath());
        $image->scaleDown(width: 800, height: 800);
        $encoded = $image->encode(new WebpEncoder(quality: 85));

        Storage::disk('public')->put($path, $encoded->toString());

        return $path;
    }

    public function index()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->sellerProfile;
        $gcashAccount = $profile ? SellerGcashAccount::where('seller_profile_id', $profile->id)->first() : null;

        return view('seller.settings.store-settings', compact('user', 'profile', 'gcashAccount'));
    }

    public function updateGcashAccount(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $rawDigits = preg_replace('/\D/', '', (string) $request->input('gcash_number'));
        if (str_starts_with($rawDigits, '63') && strlen($rawDigits) === 12) {
            $rawDigits = '0' . substr($rawDigits, 2);
        } elseif (strlen($rawDigits) === 10 && str_starts_with($rawDigits, '9')) {
            $rawDigits = '0' . $rawDigits;
        }

        $request->merge([
            'gcash_number' => $rawDigits,
        ]);

        $validated = $request->validate([
            'account_name' => ['required', 'string', 'min:2', 'max:120'],
            'gcash_number' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'qr_image'     => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'is_active'    => ['nullable', 'boolean'],
            'confirmed'    => ['required', 'accepted'],
        ], [
            'account_name.required' => 'Please enter your GCash registered account name.',
            'gcash_number.required' => 'Please enter your 11-digit GCash mobile number.',
            'gcash_number.regex'    => 'GCash number must be a valid 11-digit mobile number starting with 09 (e.g., 09171234567).',
            'qr_image.image'        => 'The GCash QR code must be a valid image file.',
            'qr_image.max'          => 'The GCash QR code image must not exceed 5MB.',
            'confirmed.required'    => 'Please review and confirm your GCash account details before saving.',
            'confirmed.accepted'    => 'Please confirm that your GCash account details are accurate.',
        ]);

        $existing = SellerGcashAccount::where('seller_profile_id', $profile->id)->first();
        $qrPath = $existing?->qr_image_path;

        if ($request->hasFile('qr_image')) {
            if ($qrPath && Storage::disk('public')->exists($qrPath)) {
                Storage::disk('public')->delete($qrPath);
            }
            $qrPath = $this->optimizeAndStoreImage($request->file('qr_image'), 'seller_gcash_qr');
        }

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;

        SellerGcashAccount::updateOrCreate(
            ['seller_profile_id' => $profile->id],
            [
                'user_id'       => $user->id,
                'account_name'  => trim(preg_replace('/\s+/', ' ', $validated['account_name'])),
                'gcash_number'  => $validated['gcash_number'],
                'qr_image_path' => $qrPath,
                'is_active'     => $isActive,
                'confirmed_at'  => now(),
            ]
        );

        return redirect()
            ->route('seller.store-settings')
            ->with('success', 'GCash online payment account details confirmed and saved successfully.');
    }

    public function removeGcashQr()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $existing = SellerGcashAccount::where('seller_profile_id', $profile->id)->first();
        if ($existing && $existing->qr_image_path) {
            if (Storage::disk('public')->exists($existing->qr_image_path)) {
                Storage::disk('public')->delete($existing->qr_image_path);
            }
            $existing->update(['qr_image_path' => null]);
        }

        return redirect()
            ->route('seller.store-settings')
            ->with('success', 'GCash QR Code image removed. Buyers can still pay using your GCash Account Name and Number.');
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'photo.required' => 'Please select an image to upload.',
            'photo.image'    => 'The uploaded file must be a valid image.',
            'photo.mimes'    => 'Supported formats: JPEG, PNG, JPG, WEBP.',
            'photo.max'      => 'Photo size must not exceed 5MB.',
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->sellerProfile;

        if ($profile->logo_path && Storage::disk('public')->exists($profile->logo_path)) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        $path = $this->optimizeAndStoreImage($request->file('photo'), 'seller_photos');
        $profile->update(['logo_path' => $path]);

        return response()->json([
            'success'   => true,
            'message'   => 'Store photo updated successfully.',
            'photo_url' => asset('storage/' . $path),
        ]);
    }

    public function removePhoto()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->sellerProfile;

        if ($profile->logo_path && Storage::disk('public')->exists($profile->logo_path)) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        $profile->update(['logo_path' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Store photo removed.',
        ]);
    }

    public function updateStoreName(Request $request)
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'min:2', 'max:80'],
        ], [
            'business_name.required' => 'Please enter your store name.',
            'business_name.min'      => 'Store name must be at least 2 characters.',
            'business_name.max'      => 'Store name cannot exceed 80 characters.',
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->sellerProfile;

        $businessName = trim(preg_replace('/\s+/', ' ', $validated['business_name']));

        $profile->update([
            'business_name' => $businessName,
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'Store name updated successfully.',
            'business_name' => $businessName,
        ]);
    }

    public function viewDocument(string $type): BinaryFileResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $profile = $user->sellerProfile;

        $path = match ($type) {
            'id'     => $profile?->id_path,
            'permit' => $profile?->permit_path,
            default  => abort(404),
        };

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Document not found.');
        }

        $fullPath = Storage::disk('public')->path($path);
        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
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

        return response()->json([
            'success'    => true,
            'message'    => 'Name updated successfully.',
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'full_name'  => trim("{$firstName} {$lastName}"),
        ]);
    }

    public function updatePhone(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $cleanContact = preg_replace('/\D/', '', (string) $request->input('contact_no'));
        if (str_starts_with($cleanContact, '63')) {
            $cleanContact = substr($cleanContact, 2);
        }
        $cleanContact = ltrim($cleanContact, '0');

        $request->merge(['clean_contact' => $cleanContact]);

        $request->validate([
            'clean_contact' => ['required', 'regex:/^9\d{9}$/'],
        ], [
            'clean_contact.required' => 'Please enter your phone number.',
            'clean_contact.regex'    => 'Please enter a valid 10-digit mobile number starting with 9 (e.g., 912 345 6789).',
        ]);

        $formattedNumber = '+63' . $cleanContact;

        if (User::where('contact_no', $formattedNumber)->where('id', '!=', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This phone number is already registered to another account.',
                'errors'  => [
                    'contact_no' => ['This phone number is already registered to another account.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($user, $formattedNumber) {
            $user->update(['contact_no' => $formattedNumber]);
            if ($user->sellerProfile) {
                $user->sellerProfile->update(['contact_no' => $formattedNumber]);
            }
        });

        $cleanForMask = preg_replace('/\D/', '', $formattedNumber);
        $maskedPhone = substr($cleanForMask, 0, 5) . '****' . substr($cleanForMask, -3);

        return response()->json([
            'success'      => true,
            'message'      => 'Phone number updated across your account and store profile.',
            'phone'        => $formattedNumber,
            'masked_phone' => $maskedPhone,
            'raw_phone'    => $cleanContact,
        ]);
    }

    public function requestEmailOtp(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $cleanEmail = strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $cleanEmail]);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email'    => 'Please enter a valid email address.',
        ]);

        $newEmail = $validated['email'];

        if ($newEmail === strtolower(trim($user->email))) {
            return response()->json([
                'success' => false,
                'message' => 'This is already your current email address.',
                'errors'  => ['email' => ['This is already your current email address.']],
            ], 422);
        }

        if (User::whereRaw('LOWER(TRIM(email)) = ?', [$newEmail])->where('id', '!=', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email address is already in use.',
                'errors'  => ['email' => ['This email address is already in use.']],
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

        $targetEmail = strtolower(trim($changeRequest->new_email));
        if (User::whereRaw('LOWER(TRIM(email)) = ?', [$targetEmail])->where('id', '!=', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email address is already in use.',
            ], 422);
        }

        $user->update(['email' => $targetEmail]);
        $updatedEmail = $user->email;
        $changeRequest->delete();

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

    public function requestPasswordOtp()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $sessionKey = "pwd_otp_{$user->id}";
        $existing = session()->get($sessionKey);

        if ($existing && ! empty($existing['last_sent_at'])) {
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