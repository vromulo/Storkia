<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyerBanAppeal;
use App\Models\RegistrationOtp;
use App\Models\User;
use App\Services\BuyerAbandonmentService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected BuyerAbandonmentService $abandonmentService
    ) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $cleanEmail = strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $cleanEmail]);

        $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ]);

        [$success, $error, $cooldown] = $this->otpService->sendOtp($cleanEmail);

        if (! $success) {
            return response()->json([
                'status'   => 'error',
                'success'  => false,
                'message'  => $error,
                'cooldown' => $cooldown,
            ], $cooldown > 0 ? 429 : 422);
        }

        return response()->json([
            'status'   => 'success',
            'success'  => true,
            'message'  => 'Verification code sent.',
            'cooldown' => $cooldown,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $cleanEmail = strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $cleanEmail]);

        $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'digits:6'],
        ]);

        [$valid, $error, $verificationToken, $attemptsRemaining] = $this->otpService->verifyOtp(
            $cleanEmail,
            (string) $request->input('code')
        );

        if (! $valid) {
            return response()->json([
                'status'             => 'error',
                'success'            => false,
                'message'            => $error,
                'attempts_remaining' => $attemptsRemaining,
            ], 400);
        }

        return response()->json([
            'status'             => 'success',
            'success'            => true,
            'message'            => 'Email verified successfully.',
            'verification_token' => $verificationToken,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $cleanEmail = strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $cleanEmail]);

        $validated = $request->validate([
            'first_name'         => ['required', 'string', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'last_name'          => ['required', 'string', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'middle_initial'     => ['nullable', 'string', 'max:1', 'regex:/^[A-Za-z]$/'],
            'sex'                => ['required', 'in:male,female,other'],
            'birthday'           => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->format('Y-m-d'),
                'after_or_equal:' . now()->subYears(100)->format('Y-m-d'),
            ],
            'email'              => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'verification_token' => ['nullable', 'string'],
            'password'           => [
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
        ], [
            'first_name.regex'         => 'First name must contain only letters and spaces.',
            'last_name.regex'          => 'Last name must contain only letters and spaces.',
            'middle_initial.regex'     => 'Middle initial must be a single letter.',
            'birthday.before_or_equal' => 'You must be at least 18 years old to register.',
        ]);

        $otpQuery = RegistrationOtp::where('email', $validated['email']);
        if (! empty($validated['verification_token'])) {
            $otpQuery->where('verification_token', $validated['verification_token']);
        }
        $otpRecord = $otpQuery->first();

        if (! $otpRecord || ! $otpRecord->isVerified() || ! $otpRecord->isReservationActive()) {
            return response()->json([
                'status'  => 'error',
                'success' => false,
                'message' => 'Your email verification has expired. Please verify your email again.',
            ], 422);
        }

        $user = DB::transaction(function () use ($validated, $otpRecord) {
            $created = User::create([
                'first_name'     => ucwords(strtolower(trim($validated['first_name']))),
                'last_name'      => ucwords(strtolower(trim($validated['last_name']))),
                'middle_initial' => ! empty($validated['middle_initial'])
                    ? strtoupper(trim($validated['middle_initial']))
                    : null,
                'sex'            => $validated['sex'],
                'birthday'       => $validated['birthday'],
                'email'          => $validated['email'],
                'password'       => Hash::make($validated['password']),
                'contact_no'     => null,
                'role'           => 'Buyer',
            ]);

            $otpRecord->delete();

            return $created;
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'       => 'success',
            'success'      => true,
            'message'      => 'User registered successfully',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $this->formatUserPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $normalizedEmail = strtolower(trim((string) $request->input('email')));
        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            return response()->json([
                'status'  => 'error',
                'success' => false,
                'message' => 'The provided credentials do not match our records.',
            ], 401);
        }

        if ($user->role !== 'Buyer') {
            return response()->json([
                'status'  => 'error',
                'success' => false,
                'message' => "You have a {$user->role} account. Please sign in through the web {$user->role} Portal.",
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'       => 'success',
            'success'      => true,
            'message'      => 'Login successful',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $this->formatUserPayload($user),
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'status'  => 'success',
            'success' => true,
            'data'    => $this->formatUserPayload($user),
        ]);
    }

    public function sanctionStatus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => $this->buildSanctionPayload($user),
        ]);
    }

    public function submitSanctionAppeal(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'appeal_explanation' => ['required', 'string', 'min:20', 'max:1500'],
            'supporting_photo'   => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'appeal_explanation.required' => 'Please explain why your account should be reactivated.',
            'appeal_explanation.min'      => 'Please provide at least 20 characters explaining your appeal.',
        ]);

        $photoPath = null;
        if ($request->hasFile('supporting_photo')) {
            $photoPath = $request->file('supporting_photo')->store('buyer_ban_appeals', 'public');
        }

        $appeal = $this->abandonmentService->submitBuyerAppeal(
            buyer: $user,
            explanation: $validated['appeal_explanation'],
            photoPath: $photoPath
        );

        return response()->json([
            'success' => true,
            'message' => 'Your reactivation appeal has been submitted for Admin review.',
            'appeal'  => $appeal,
            'data'    => $this->formatUserPayload($user->fresh()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'status'  => 'success',
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function formatUserPayload(User $user): array
    {
        $user->loadMissing('identityVerification');
        $latestVerif = $user->identityVerification;

        return [
            'id'                              => $user->id,
            'first_name'                      => $user->first_name,
            'last_name'                       => $user->last_name,
            'full_name'                       => $user->full_name,
            'middle_initial'                  => $user->middle_initial,
            'sex'                             => $user->sex,
            'birthday'                        => $user->birthday ? $user->birthday->format('Y-m-d') : null,
            'email'                           => $user->email,
            'contact_no'                      => $user->contact_no,
            'role'                            => $user->role ?? 'Buyer',
            'password_changed_at'             => $user->password_changed_at?->toISOString(),
            'formatted_password_last_updated' => $user->formattedPasswordLastUpdated(),
            'identity_verification_status'    => $latestVerif ? $latestVerif->status : 'not_started',
            'identity_verification'           => $latestVerif ? [
                'id'               => $latestVerif->id,
                'id_type'          => $latestVerif->id_type,
                'method'           => $latestVerif->method,
                'status'           => $latestVerif->status,
                'rejection_reason' => $latestVerif->rejection_reason,
                'verified_at'      => $latestVerif->verified_at?->toISOString(),
            ] : null,
            'sanction'                        => $this->buildSanctionPayload($user),
        ];
    }

    protected function buildSanctionPayload(User $user): array
    {
        $sanction = $this->abandonmentService->getOrCreateSanction((int) $user->id);
        $latestAppeal = BuyerBanAppeal::where('buyer_id', $user->id)->latest('id')->first();

        return [
            'id'                                    => $sanction->id,
            'cod_abandonment_strikes'               => (int) $sanction->cod_abandonment_strikes,
            'ban_level'                             => (int) $sanction->ban_level,
            'account_status'                        => $sanction->account_status,
            'temp_ban_started_at'                   => $sanction->temp_ban_started_at?->toISOString(),
            'temp_ban_ends_at'                      => $sanction->temp_ban_ends_at?->toISOString(),
            'appeal_required'                       => (bool) $sanction->appeal_required,
            'permanently_banned_at'                 => $sanction->permanently_banned_at?->toISOString(),
            'last_abandonment_reason'               => $sanction->last_abandonment_reason,
            'strike_history'                        => $sanction->strike_history ?? [],
            'is_permanently_banned'                 => $sanction->isPermanentlyBanned(),
            'is_temporarily_banned_countdown_active'=> $sanction->isTemporarilyBannedCountdownActive(),
            'can_submit_appeal_now'                 => $sanction->canSubmitAppealNow(),
            'is_blocked_from_marketplace'           => $sanction->isBlockedFromMarketplace(),
            'latest_appeal'                         => $latestAppeal ? [
                'id'                 => $latestAppeal->id,
                'status'             => $latestAppeal->status,
                'appeal_explanation' => $latestAppeal->appeal_explanation,
                'admin_remarks'      => $latestAppeal->admin_remarks,
                'created_at'         => $latestAppeal->created_at?->toISOString(),
                'reviewed_at'        => $latestAppeal->reviewed_at?->toISOString(),
            ] : null,
        ];
    }
}