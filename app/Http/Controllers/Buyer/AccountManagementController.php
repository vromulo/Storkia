<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationOtpMail;
use App\Models\BuyerProfile;
use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Services\AccountArchiveService;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountManagementController extends Controller
{
    public const OTP_TTL_MINUTES = 10;
    public const OTP_COOLDOWN_SECONDS = 60;
    public const OTP_MAX_ATTEMPTS = 5;

    public function __construct(
        protected AccountArchiveService $accountArchiveService
    ) {}

    /**
     * Display the Buyer Account Management & Security page (GET /user/account-management).
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (Schema::hasTable('buyer_profiles')) {
            BuyerProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'username'            => BuyerProfile::generateUniqueUsername($user->email),
                    'avatar_path'         => null,
                    'username_changed_at' => null,
                ]
            );
        }

        $initialFullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: 'Buyer-' . str_pad((string) $user->id, 6, '0', STR_PAD_LEFT);

        $rawEmail = (string) ($user->email ?? '');
        $maskedEmail = $this->maskEmail($rawEmail);

        $passwordLastUpdated = method_exists($user, 'formattedPasswordLastUpdated')
            ? $user->formattedPasswordLastUpdated()
            : ($user->password_changed_at ? 'Updated ' . $user->password_changed_at->diffForHumans() : 'Never updated');

        $phoneData = $this->formatPhoneData((string) ($user->contact_no ?? ''));

        $deleteBlockers = $this->accountArchiveService->blockers($user);
        $archiveReasons = defined(User::class . '::ARCHIVE_REASONS')
            ? User::ARCHIVE_REASONS
            : [
                'privacy'        => 'Privacy or security concerns',
                'not_using'      => 'I no longer use Storkia',
                'second_account' => 'I have another Storkia account',
                'other'          => 'Other reason',
            ];

        return view()->first(
            [
                'buyer.option.account.account-management',
                'buyer.account.account-management',
                'buyer.account-management',
            ],
            [
                'user'                => $user,
                'initialFullName'     => $initialFullName,
                'rawEmail'            => $rawEmail,
                'maskedEmail'         => $maskedEmail,
                'passwordLastUpdated' => $passwordLastUpdated,
                'cleanContact'        => $phoneData['phone_raw'],
                'formattedPhone'      => $phoneData['formatted_phone'],
                'maskedContact'       => $phoneData['masked_phone'],
                'hasPhone'            => $phoneData['has_phone'],
                'deleteBlockers'      => $deleteBlockers,
                'archiveReasons'      => $archiveReasons,
            ]
        );
    }

    /**
     * Alias for index() in case any route or caller invokes show().
     */
    public function show(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Update Buyer's First Name and Last Name (PATCH /user/account-management/name).
     */
    public function updateName(Request $request)
    {
        /** @var User $user */
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

        $oldName = $user->full_name;

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
        ]);

        AuditLoggerService::log(
            action: 'buyer_name_updated',
            targetType: 'User',
            targetId: (int) $user->id,
            targetReference: $user->email,
            oldValues: ['name' => $oldName],
            newValues: ['name' => $user->full_name],
            reason: 'Buyer updated full name in Account Management'
        );

        $fullName = trim($user->first_name . ' ' . $user->last_name);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'    => true,
                'message'    => 'Your name has been updated.',
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'full_name'  => $fullName,
            ]);
        }

        return back()->with('success', 'Your name has been updated.');
    }

    /**
     * Update Buyer's Phone Number (PATCH /user/account-management/phone).
     */
    public function updatePhone(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $rawInput = (string) ($request->input('contact_no') ?? $request->input('phone') ?? '');
        $digits = preg_replace('/\D/', '', $rawInput);

        if (str_starts_with($digits, '63') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }
        $clean10 = ltrim($digits, '0');

        if (! preg_match('/^9\d{9}$/', $clean10)) {
            throw ValidationException::withMessages([
                'phone'      => 'Please enter a valid 10-digit Philippine mobile number starting with 9 (e.g., 917 123 4567).',
                'contact_no' => 'Please enter a valid 10-digit Philippine mobile number starting with 9 (e.g., 917 123 4567).',
            ]);
        }

        $normalized09 = '0' . $clean10;
        $normalized63 = '+63' . $clean10;

        $exists = User::where('id', '!=', $user->id)
            ->whereIn('contact_no', [$normalized09, $clean10, $normalized63, '63' . $clean10])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'phone'      => 'This mobile number is already registered to another account.',
                'contact_no' => 'This mobile number is already registered to another account.',
            ]);
        }

        $user->update([
            'contact_no' => $normalized09,
        ]);

        $phoneData = $this->formatPhoneData($normalized09);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'         => true,
                'message'         => 'Phone number updated successfully.',
                'phone_raw'       => $phoneData['phone_raw'],
                'formatted_phone' => $phoneData['formatted_phone'],
                'masked_phone'    => $phoneData['masked_phone'],
                'has_phone'       => true,
            ]);
        }

        return back()->with('success', 'Phone number updated successfully.');
    }

    /**
     * Request a 6-digit OTP to change the Buyer's Email Address (POST /user/account-management/email/request-otp).
     */
    public function requestEmailOtp(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $normalizedEmail = strtolower(trim((string) $request->input('email', '')));
        $request->merge(['email' => $normalizedEmail]);

        $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], [
            'email.unique' => 'This email address is already registered to another account.',
        ]);

        if (strtolower((string) $user->email) === $normalizedEmail) {
            throw ValidationException::withMessages([
                'email' => 'Please enter a different email address from your current one.',
            ]);
        }

        $existing = EmailChangeRequest::where('user_id', $user->id)->first();
        if ($existing && $existing->last_sent_at) {
            $elapsed = now()->diffInSeconds($existing->last_sent_at, true);
            if ($elapsed < self::OTP_COOLDOWN_SECONDS) {
                $remaining = max(1, (int) ceil(self::OTP_COOLDOWN_SECONDS - $elapsed));
                return response()->json([
                    'success'      => false,
                    'message'      => "Please wait {$remaining}s before requesting another code.",
                    'cooldown'     => $remaining,
                    'masked_email' => $this->maskEmail($normalizedEmail),
                ], 429);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            Mail::to($normalizedEmail)->send(new RegistrationOtpMail($code, self::OTP_TTL_MINUTES));
        } catch (\Throwable $e) {
            Log::error('Buyer Email Change OTP Mail Error: ' . $e->getMessage());
            throw ValidationException::withMessages([
                'email' => 'Unable to send verification email right now. Please try again shortly.',
            ]);
        }

        EmailChangeRequest::updateOrCreate(
            ['user_id' => $user->id],
            [
                'new_email'       => $normalizedEmail,
                'code_hash'       => Hash::make($code),
                'attempts'        => 0,
                'last_sent_at'    => now(),
                'code_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            ]
        );

        return response()->json([
            'success'      => true,
            'message'      => 'A 6-digit verification code has been sent to your new email.',
            'masked_email' => $this->maskEmail($normalizedEmail),
            'cooldown'     => self::OTP_COOLDOWN_SECONDS,
        ]);
    }

    /**
     * Verify the 6-digit Email OTP and update the Buyer's Email (POST /user/account-management/email/verify-otp).
     */
    public function verifyEmailOtp(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $code = preg_replace('/\D/', '', (string) ($request->input('code') ?? $request->input('otp') ?? ''));

        if (strlen($code) !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 6-digit verification code.',
            ], 422);
        }

        $record = EmailChangeRequest::where('user_id', $user->id)->first();
        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'No active email change request found. Please request a new code.',
            ], 422);
        }

        if ($record->code_expires_at && now()->gt($record->code_expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'This verification code has expired. Please request a new one.',
            ], 422);
        }

        if ((int) $record->attempts >= self::OTP_MAX_ATTEMPTS) {
            return response()->json([
                'success' => false,
                'message' => 'Too many invalid attempts. Please request a new verification code.',
            ], 422);
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            $remaining = max(0, self::OTP_MAX_ATTEMPTS - (int) $record->attempts);
            return response()->json([
                'success' => false,
                'message' => "Incorrect verification code. {$remaining} attempt(s) remaining.",
            ], 422);
        }

        $newEmail = strtolower(trim($record->new_email));
        if (User::where('id', '!=', $user->id)->whereRaw('LOWER(TRIM(email)) = ?', [$newEmail])->exists()) {
            $record->delete();
            return response()->json([
                'success' => false,
                'message' => 'This email address was recently claimed by another account.',
            ], 422);
        }

        $oldEmail = $user->email;

        $user->update([
            'email'             => $newEmail,
            'email_verified_at' => now(),
        ]);

        $record->delete();

        AuditLoggerService::log(
            action: 'buyer_email_updated',
            targetType: 'User',
            targetId: (int) $user->id,
            targetReference: $newEmail,
            oldValues: ['email' => $oldEmail],
            newValues: ['email' => $newEmail],
            reason: 'Buyer verified OTP and updated email address'
        );

        return response()->json([
            'success'      => true,
            'message'      => 'Your email address has been updated.',
            'email'        => $newEmail,
            'masked_email' => $this->maskEmail($newEmail),
        ]);
    }

    /**
     * Request a 6-digit OTP to authorize a Password Change (POST /user/account-management/password/request-otp).
     */
    public function requestPasswordOtp(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $lastSent = (int) $request->session()->get('buyer_pwd_otp_last_sent_at', 0);
        $elapsed = time() - $lastSent;

        if ($lastSent > 0 && $elapsed < self::OTP_COOLDOWN_SECONDS) {
            $remaining = max(1, self::OTP_COOLDOWN_SECONDS - $elapsed);
            return response()->json([
                'success'      => false,
                'message'      => "Please wait {$remaining}s before requesting another code.",
                'cooldown'     => $remaining,
                'masked_email' => $this->maskEmail((string) $user->email),
            ], 429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            Mail::to($user->email)->send(new RegistrationOtpMail($code, self::OTP_TTL_MINUTES));
        } catch (\Throwable $e) {
            Log::error('Buyer Password OTP Mail Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to send verification code right now. Please try again shortly.',
            ], 500);
        }

        $request->session()->put([
            'buyer_pwd_otp_hash'        => Hash::make($code),
            'buyer_pwd_otp_expires_at'  => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
            'buyer_pwd_otp_attempts'    => 0,
            'buyer_pwd_otp_last_sent_at'=> time(),
            'buyer_pwd_otp_verified'    => false,
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Verification code sent to your email address.',
            'masked_email' => $this->maskEmail((string) $user->email),
            'cooldown'     => self::OTP_COOLDOWN_SECONDS,
        ]);
    }

    /**
     * Verify the 6-digit Password OTP (POST /user/account-management/password/verify-otp).
     */
    public function verifyPasswordOtp(Request $request)
    {
        $code = preg_replace('/\D/', '', (string) ($request->input('code') ?? $request->input('otp') ?? ''));

        if (strlen($code) !== 6) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 6-digit verification code.',
            ], 422);
        }

        $hash = $request->session()->get('buyer_pwd_otp_hash');
        $expiresAt = (int) $request->session()->get('buyer_pwd_otp_expires_at', 0);
        $attempts = (int) $request->session()->get('buyer_pwd_otp_attempts', 0);

        if (! $hash || time() > $expiresAt) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code has expired. Please request a new one.',
            ], 422);
        }

        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            return response()->json([
                'success' => false,
                'message' => 'Too many invalid attempts. Please request a new code.',
            ], 422);
        }

        if (! Hash::check($code, $hash)) {
            $attempts++;
            $request->session()->put('buyer_pwd_otp_attempts', $attempts);
            $remaining = max(0, self::OTP_MAX_ATTEMPTS - $attempts);

            return response()->json([
                'success' => false,
                'message' => "Incorrect verification code. {$remaining} attempt(s) remaining.",
            ], 422);
        }

        $request->session()->put('buyer_pwd_otp_verified', true);

        return response()->json([
            'success'  => true,
            'verified' => true,
            'message'  => 'Identity verified. You can now update your password.',
        ]);
    }

    /**
     * Update Buyer's Password (PATCH /user/account-management/password).
     */
    public function updatePassword(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $updates = [
            'password' => Hash::make($validated['password']),
        ];

        if (Schema::hasColumn('users', 'password_changed_at')) {
            $updates['password_changed_at'] = now();
        }

        $user->forceFill($updates)->save();

        $request->session()->forget([
            'buyer_pwd_otp_hash',
            'buyer_pwd_otp_expires_at',
            'buyer_pwd_otp_attempts',
            'buyer_pwd_otp_last_sent_at',
            'buyer_pwd_otp_verified',
        ]);

        AuditLoggerService::log(
            action: 'buyer_password_updated',
            targetType: 'User',
            targetId: (int) $user->id,
            targetReference: $user->email,
            reason: 'Buyer updated password in Account Management'
        );

        $passwordLastUpdated = method_exists($user, 'formattedPasswordLastUpdated')
            ? $user->formattedPasswordLastUpdated()
            : 'Updated just now';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'               => true,
                'message'               => 'Your password has been updated.',
                'password_last_updated' => $passwordLastUpdated,
            ]);
        }

        return back()->with('success', 'Your password has been updated.');
    }

    protected function maskEmail(string $rawEmail): string
    {
        if (! str_contains($rawEmail, '@')) {
            return $rawEmail;
        }

        [$name, $domain] = explode('@', $rawEmail, 2);
        if (strlen($name) <= 2) {
            return substr($name, 0, 1) . '****@' . $domain;
        }

        return substr($name, 0, 1)
            . str_repeat('*', max(strlen($name) - 2, 4))
            . substr($name, -1)
            . '@' . $domain;
    }

    protected function formatPhoneData(string $contact): array
    {
        $cleanContact = preg_replace('/\D/', '', $contact);
        if (str_starts_with($cleanContact, '63')) {
            $cleanContact = substr($cleanContact, 2);
        }
        $cleanContact = ltrim($cleanContact, '0');
        $hasPhone = ! empty($cleanContact);

        $maskedContact = 'Not provided';
        $formattedPhone = '';

        if ($hasPhone) {
            $fullDigits = '63' . $cleanContact;
            $maskedContact = strlen($fullDigits) >= 8
                ? substr($fullDigits, 0, 5) . '****' . substr($fullDigits, -3)
                : $fullDigits;

            $formattedPhone = substr($cleanContact, 0, 3);
            if (strlen($cleanContact) > 3) {
                $formattedPhone .= ' ' . substr($cleanContact, 3, 3);
            }
            if (strlen($cleanContact) > 6) {
                $formattedPhone .= ' ' . substr($cleanContact, 6, 4);
            }
        }

        return [
            'phone_raw'       => $cleanContact,
            'formatted_phone' => $formattedPhone,
            'masked_phone'    => $maskedContact,
            'has_phone'       => $hasPhone,
        ];
    }
}