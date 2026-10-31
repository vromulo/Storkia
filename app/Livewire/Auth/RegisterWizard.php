<?php

namespace App\Livewire\Auth;

use App\Models\BuyerProfile;
use App\Models\RegistrationOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class RegisterWizard extends Component
{
    public int $currentStep = 1;
    public bool $codeSent = false;

    public string $email = '';
    public string $code = '';
    public ?string $verificationToken = null;
    public int $resendCooldown = 0;
    public int $attemptsRemaining = RegistrationOtp::MAX_ATTEMPTS;

    public string $first_name = '';
    public string $last_name = '';
    public string $middle_initial = '';
    public string $sex = '';
    public string $birthday = '';

    public string $password = '';
    public string $password_confirmation = '';

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['first_name', 'last_name', 'middle_initial'])) {
            $this->$propertyName = ltrim(preg_replace('/ {2,}/', ' ', $this->stripEnye($this->$propertyName)));
        }

        if ($propertyName === 'email') {
            $this->email = $this->stripWhitespace($this->email);
        }

        if ($this->currentStep === 1 && $propertyName === 'email') {
            $this->validateOnly('email', ['email' => ['required', 'email:rfc,dns']]);
        } elseif ($this->currentStep === 2) {
            $this->validateOnly($propertyName, $this->getStep2Rules(), $this->getStep2Messages());
        } elseif ($this->currentStep === 3) {
            if ($propertyName === 'password') {
                $this->validateOnly('password', $this->getStep3Rules(), $this->getStep3Messages());
                if (!empty($this->password_confirmation)) {
                    $this->validateOnly('password_confirmation', ['password_confirmation' => ['same:password']]);
                }
            } elseif ($propertyName === 'password_confirmation') {
                $this->validateOnly('password_confirmation', ['password_confirmation' => ['required', 'same:password']]);
            }
        }
    }

    public function sendCode(OtpService $otpService): void
    {
        $this->email = $this->normalizeEmail($this->email);

        $this->validateOnly('email', ['email' => ['required', 'email:rfc,dns']]);

        if (User::whereRaw('LOWER(TRIM(email)) = ?', [$this->email])->exists()) {
            $this->addError('email', 'This email address is already in use.');
            return;
        }

        [$success, $error, $cooldown] = $otpService->sendOtp($this->email);

        if (! $success) {
            $this->addError($cooldown > 0 ? 'code' : 'email', $error);
            if ($cooldown > 0) {
                $this->resendCooldown = $cooldown;
                $this->codeSent = true;
            }
            return;
        }

        $this->code = '';
        $this->codeSent = true;
        $this->resendCooldown = $cooldown;
        $this->attemptsRemaining = RegistrationOtp::MAX_ATTEMPTS;
        $this->resetErrorBag();
    }

    public function resendCode(OtpService $otpService): void
    {
        $this->sendCode($otpService);
    }

    public function verifyCode(OtpService $otpService): void
    {
        $this->email = $this->normalizeEmail($this->email);
        $this->validateOnly('code', ['code' => ['required', 'digits:6']]);

        [$valid, $error, $token, $remaining] = $otpService->verifyOtp($this->email, $this->code);

        if (! $valid) {
            $this->attemptsRemaining = $remaining;
            $this->addError('code', $error);
            return;
        }

        $this->verificationToken = $token;
        $this->currentStep = 2;
        $this->resetErrorBag();
    }

    /**
     * Name length rule: 1 and 51 characters are rejected, 2 and 50 are accepted.
     * Measured on the trimmed value so stray leading/trailing spaces can't pad a name.
     */
    protected function nameLengthRule(string $label): \Closure
    {
        return function ($attribute, $value, $fail) use ($label) {
            $length = mb_strlen(trim((string) $value));

            if ($length < 2) {
                $fail("{$label} must be at least 2 characters.");
            } elseif ($length > 50) {
                $fail("{$label} must not be more than 50 characters.");
            }
        };
    }

    /** Removes ñ/Ñ, including the decomposed form (n/N + combining tilde U+0303). */
    protected function stripEnye(string $value): string
    {
        return preg_replace('/[\x{00F1}\x{00D1}]|[nN]\x{0303}|\x{0303}/u', '', $value) ?? $value;
    }

    /** Removes every whitespace character (including NBSP / zero-width) from a value. */
    protected function stripWhitespace(string $value): string
    {
        return preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $value) ?? $value;
    }

    protected function normalizeEmail(string $email): string
    {
        return strtolower($this->stripWhitespace($email));
    }

    protected function getStep2Rules(): array
    {
        return [
            'first_name' => ['required', 'string', $this->nameLengthRule('First name'), 'regex:/^[A-Za-z\s]+$/'],
            'last_name' => ['required', 'string', $this->nameLengthRule('Last name'), 'regex:/^[A-Za-z\s]+$/'],
            'middle_initial' => ['nullable', 'string', 'regex:/^[A-Za-z]$/'],
            'sex' => ['required', 'in:male,female'],
            'birthday' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d'), 'after_or_equal:' . now()->subYears(100)->format('Y-m-d')],
        ];
    }

    protected function getStep2Messages(): array
    {
        return [
            'first_name.regex' => 'First name must contain only letters and spaces.',
            'last_name.regex' => 'Last name must contain only letters and spaces.',
            'middle_initial.regex' => 'Middle initial must be a single letter.',
            'sex.required' => 'Please select an option.',
            'birthday.before_or_equal' => 'You must be at least 18 years old to register.',
        ];
    }

    public function goToPasswordStep(): void
    {
        $this->first_name = trim($this->first_name);
        $this->last_name = trim($this->last_name);

        $this->validate($this->getStep2Rules(), $this->getStep2Messages());
        $this->currentStep = 3;
    }

    public function backToStep(int $step): void
    {
        if ($step < $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    protected function getStep3Rules(): array
    {
        return [
            'password' => [
                'required', 'string', 'min:8',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[A-Z]/', $value)) $fail('Password must contain at least 1 uppercase letter.');
                    if (!preg_match('/[0-9]/', $value)) $fail('Password must contain at least 1 number.');
                    if (!preg_match('/[\W_]/', $value)) $fail('Password must contain at least 1 special character.');
                },
            ],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    protected function getStep3Messages(): array
    {
        return [
            'password.required' => 'Please enter a password.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password_confirmation.same' => 'The password confirmation does not match.',
        ];
    }

    public function register(): void
    {
        $this->validate($this->getStep3Rules(), $this->getStep3Messages());

        $this->email = $this->normalizeEmail($this->email);

        $record = RegistrationOtp::where('email', $this->email)
            ->where('verification_token', $this->verificationToken)
            ->first();

        if (! $record || ! $record->isVerified() || ! $record->isReservationActive()) {
            $this->addError('password', 'Your email verification has expired. Please verify your email again.');
            $this->currentStep = 1;
            $this->codeSent = false;
            $this->verificationToken = null;
            return;
        }

        if (User::whereRaw('LOWER(TRIM(email)) = ?', [$this->email])->exists()) {
            $this->addError('password', 'This email address is already in use. Please sign in instead.');
            return;
        }

        DB::transaction(function () use ($record) {
            $user = User::create([
                'first_name'     => ucwords(strtolower(trim($this->first_name))),
                'last_name'      => ucwords(strtolower(trim($this->last_name))),
                'middle_initial' => trim($this->middle_initial) ? strtoupper(trim($this->middle_initial)) : null,
                'sex'            => $this->sex,
                'email'          => $this->email,
                'contact_no'     => null,
                'birthday'       => $this->birthday,
                'password'       => Hash::make($this->password),
                'role'           => 'Buyer',
            ]);

            // Auto-create BuyerProfile with email prefix + random 4 digits
            BuyerProfile::create([
                'user_id'  => $user->id,
                'username' => BuyerProfile::generateUniqueUsername($user->email),
            ]);

            $record->delete();
            Auth::login($user);
        });

        session()->flash('success', 'Your account has been created successfully.');
        $this->redirect(route('home'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register-wizard');
    }
}