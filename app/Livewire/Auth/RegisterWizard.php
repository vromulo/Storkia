<?php

namespace App\Livewire\Auth;

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
            $this->$propertyName = ltrim(preg_replace('/ {2,}/', ' ', $this->$propertyName));
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
        $this->validateOnly('email', ['email' => ['required', 'email:rfc,dns']]);

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

    protected function getStep2Rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'regex:/^[A-Za-z\s]+$/'],
            'last_name' => ['required', 'string', 'regex:/^[A-Za-z\s]+$/'],
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

        if (User::where('email', $this->email)->exists()) {
            $this->addError('password', 'This email was just registered. Please sign in instead.');
            return;
        }

        DB::transaction(function () use ($record) {
            $user = User::create([
                'first_name' => ucwords(strtolower(trim($this->first_name))),
                'last_name' => ucwords(strtolower(trim($this->last_name))),
                'middle_initial' => trim($this->middle_initial) ? strtoupper(trim($this->middle_initial)) : null,
                'sex' => $this->sex,
                'email' => $this->email,
                'contact_no' => null,
                'birthday' => $this->birthday,
                'password' => Hash::make($this->password),
                'role' => 'Buyer',
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