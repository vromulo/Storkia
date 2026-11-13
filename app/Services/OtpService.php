<?php

namespace App\Services;

use App\Mail\RegistrationOtpMail;
use App\Models\RegistrationOtp;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OtpService
{
    public const SESSION_EMAIL_KEY = 'storkia_registration_otp_email';
    public const SESSION_TOKEN_KEY = 'storkia_registration_otp_token';

    /**
     * Issue and email an OTP code.
     *
     * @return array{0: bool, 1: ?string, 2: int}
     */
    public function sendOtp(string $email, bool $forceResend = false): array
    {
        $email = strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Please enter a valid email address.', 0];
        }

        if (User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
            return [false, 'This email address is already in use.', 0];
        }

        $existing = RegistrationOtp::where('email', $email)->first();

        if ($existing) {
            $sessionEmail = $this->getSessionValue(self::SESSION_EMAIL_KEY);
            $sessionToken = $this->getSessionValue(self::SESSION_TOKEN_KEY);
            $isSameSession = ($sessionEmail === $email)
                || ($sessionToken && $existing->verification_token && hash_equals($existing->verification_token, $sessionToken));

            if ($existing->isReservationActive() && ! $isSameSession && ! $forceResend) {
                $recentlyVerified = $existing->verified_at && $existing->verified_at->gt(now()->subMinutes(5));
                if ($recentlyVerified) {
                    return [false, 'This email is currently completing registration in another session.', 0];
                }
            }

            $cooldown = $existing->secondsUntilResendAllowed();
            if ($cooldown > 0) {
                return [false, "Please wait {$cooldown}s before requesting a new code.", $cooldown];
            }
        }

        $code = RegistrationOtp::generateCode();

        try {
            $this->deliverMailable($email, new RegistrationOtpMail($code, RegistrationOtp::CODE_TTL_MINUTES));
        } catch (\Throwable $e) {
            Log::error('OTP Mail Error', [
                'email' => $email,
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'error' => $e->getMessage(),
            ]);

            $friendlyError = config('app.debug')
                ? 'Could not send verification email (' . Str::limit($e->getMessage(), 140) . ').'
                : 'We could not send the verification email right now. Please check your email address and try again.';

            return [false, $friendlyError, 0];
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

        $this->putSessionValue(self::SESSION_EMAIL_KEY, $email);
        $this->putSessionValue(self::SESSION_TOKEN_KEY, null);

        return [true, null, RegistrationOtp::RESEND_COOLDOWN_SECONDS];
    }

    /**
     * Verify the supplied OTP code.
     *
     * @return array{0: bool, 1: ?string, 2: ?string, 3: int}
     */
    public function verifyOtp(string $email, string $code): array
    {
        $email = strtolower(trim($email));
        $cleanCode = preg_replace('/\D/', '', trim($code));

        if (User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
            return [false, 'This email address is already in use.', null, 0];
        }

        $record = RegistrationOtp::where('email', $email)->first();

        if (! $record) {
            return [false, 'Please request a verification code first.', null, RegistrationOtp::MAX_ATTEMPTS];
        }

        if ($record->isCodeExpired()) {
            return [false, 'This code has expired. Please click Resend Code to get a new one.', null, 0];
        }

        if ($record->attempts >= RegistrationOtp::MAX_ATTEMPTS) {
            return [false, 'Too many invalid attempts. Please click Resend Code to request a new one.', null, 0];
        }

        if ($cleanCode === '' || ! Hash::check($cleanCode, $record->code_hash)) {
            $record->increment('attempts');
            $remaining = $record->attemptsRemaining();

            return [false, "Invalid verification code. {$remaining} attempt(s) remaining.", null, $remaining];
        }

        $token = Str::random(64);

        $record->update([
            'verified_at' => now(),
            'verification_token' => $token,
            'reservation_expires_at' => now()->addMinutes(RegistrationOtp::RESERVATION_TTL_MINUTES),
        ]);

        $this->putSessionValue(self::SESSION_EMAIL_KEY, $email);
        $this->putSessionValue(self::SESSION_TOKEN_KEY, $token);

        return [true, null, $token, RegistrationOtp::MAX_ATTEMPTS];
    }

    /**
     * Release any uncompleted OTP reservation for the given email when the user changes email.
     */
    public function releaseEmail(string $email): void
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return;
        }

        $sessionEmail = $this->getSessionValue(self::SESSION_EMAIL_KEY);
        if ($sessionEmail === $normalized) {
            RegistrationOtp::where('email', $normalized)->update([
                'verified_at' => null,
                'verification_token' => null,
                'reservation_expires_at' => null,
            ]);
            $this->putSessionValue(self::SESSION_EMAIL_KEY, null);
            $this->putSessionValue(self::SESSION_TOKEN_KEY, null);
        }
    }

    /**
     * Deliver a Mailable using the configured SMTP transport when a real SMTP host is present.
     */
    public function deliverMailable(string $recipientEmail, Mailable $mailable): void
    {
        $smtpHost = trim((string) config('mail.mailers.smtp.host', ''));
        $defaultMailer = (string) config('mail.default', 'smtp');

        config([
            'mail.mailers.smtp.verify_peer' => false,
            'mail.mailers.smtp.timeout' => 15,
            'mail.mailers.smtp.stream' => [
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ],
        ]);

        $shouldForceSmtp = $smtpHost !== ''
            && ! in_array(strtolower($smtpHost), ['127.0.0.1', 'localhost', 'mailpit'], true)
            && in_array($defaultMailer, ['log', 'array', ''], true);

        $mailerName = $shouldForceSmtp ? 'smtp' : ($defaultMailer ?: 'smtp');

        Mail::mailer($mailerName)->to($recipientEmail)->send($mailable);
    }

    protected function getSessionValue(string $key): ?string
    {
        try {
            if (request()->hasSession()) {
                $val = request()->session()->get($key);
                return is_string($val) && $val !== '' ? $val : null;
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    protected function putSessionValue(string $key, ?string $value): void
    {
        try {
            if (request()->hasSession()) {
                if ($value === null) {
                    request()->session()->forget($key);
                } else {
                    request()->session()->put($key, $value);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}