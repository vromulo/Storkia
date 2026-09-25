<?php

namespace App\Services;

use App\Mail\RegistrationOtpMail;
use App\Models\RegistrationOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OtpService
{
    /**
     * Issue and email an OTP code.
     *
     * @return array [bool $success, ?string $error, int $cooldown]
     */
    public function sendOtp(string $email): array
    {
        if (User::where('email', $email)->exists()) {
            return [false, 'This email is already registered.', 0];
        }

        $existing = RegistrationOtp::where('email', $email)->first();

        if ($existing && $existing->isReservationActive()) {
            return [false, 'This email is currently completing registration in another session.', 0];
        }

        if ($existing && ! $existing->isVerified()) {
            $cooldown = $existing->secondsUntilResendAllowed();
            if ($cooldown > 0) {
                return [false, "Please wait {$cooldown}s before requesting a new code.", $cooldown];
            }
        }

        $code = RegistrationOtp::generateCode();

        try {
            Mail::to($email)->send(new RegistrationOtpMail($code, RegistrationOtp::CODE_TTL_MINUTES));
        } catch (\Throwable $e) {
            return [false, 'We could not send the verification email right now. Please try again shortly.', 0];
        }

        RegistrationOtp::updateOrCreate(
            ['email' => $email],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'last_sent_at' => now(),
                'code_expires_at' => now()->addMinutes(RegistrationOtp::CODE_TTL_MINUTES),
                'verified_at' => null,
                'verification_token' => null,
                'reservation_expires_at' => null,
            ]
        );

        return [true, null, RegistrationOtp::RESEND_COOLDOWN_SECONDS];
    }

    /**
     * Verify the supplied OTP code.
     *
     * @return array [bool $valid, ?string $error, ?string $verificationToken, int $attemptsRemaining]
     */
    public function verifyOtp(string $email, string $code): array
    {
        $record = RegistrationOtp::where('email', $email)->first();

        if (! $record) {
            return [false, 'Please request a verification code first.', null, RegistrationOtp::MAX_ATTEMPTS];
        }

        if ($record->isCodeExpired()) {
            return [false, 'This code has expired. Please request a new one.', null, 0];
        }

        if ($record->attempts >= RegistrationOtp::MAX_ATTEMPTS) {
            return [false, 'Too many invalid attempts. Please request a new code.', null, 0];
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            $remaining = $record->attemptsRemaining();
            return [false, "Invalid code. {$remaining} attempt(s) remaining.", null, $remaining];
        }

        $token = Str::random(64);
        $record->update([
            'verified_at' => now(),
            'verification_token' => $token,
            'reservation_expires_at' => now()->addMinutes(RegistrationOtp::RESERVATION_TTL_MINUTES),
        ]);

        return [true, null, $token, RegistrationOtp::MAX_ATTEMPTS];
    }
}