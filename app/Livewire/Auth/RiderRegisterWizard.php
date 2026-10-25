<?php

namespace App\Livewire\Auth;

use App\Models\LogisticsProfile;
use App\Models\RegistrationOtp;
use App\Models\Rider;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithFileUploads;

class RiderRegisterWizard extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;
    public bool $codeSent = false;
    public bool $registrationSuccessful = false;

    public string $email = '';
    public string $code = '';
    public ?string $verificationToken = null;
    public int $resendCooldown = 0;
    public int $attemptsRemaining = RegistrationOtp::MAX_ATTEMPTS;

    public string $logistics_profile_id = '';
    public string $selectedLogisticsName = '';
    public array $logisticsCompanies = [];

    public string $first_name = '';
    public string $last_name = '';
    public string $birthday = '';
    public string $contact_no = '';

    public string $license_no = '';
    public string $license_expiry_date = '';
    public string $vehicle_type = '';
    public string $plate_number = '';

    public array $vehicleTypes = [
        'Motorcycle',
        'Bicycle / E-Bike',
        'Tricycle / Three-Wheeler',
        'Sedan / Hatchback',
        'MPV / SUV',
        'L300 / Delivery Van',
        'Pickup Truck',
        'Light Cargo Truck (4-Wheeler)',
        'Closed Van Truck (6-Wheeler)',
    ];

    public $drivers_license;
    public $vehicle_or_cr;

    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->logisticsCompanies = LogisticsProfile::query()
            ->whereNotNull('business_name')
            ->orderBy('business_name')
            ->get(['id', 'business_name'])
            ->map(fn ($hub) => [
                'id' => (string) $hub->id,
                'business_name' => $hub->business_name,
            ])
            ->toArray();
    }

    public function updatedLogisticsProfileId($value): void
    {
        $found = collect($this->logisticsCompanies)->firstWhere('id', (string) $value);
        $this->selectedLogisticsName = $found ? $found['business_name'] : '';
    }

    public function updatedContactNo($value): void
    {
        $this->contact_no = preg_replace('/\D/', '', $value);
    }

    public function updatedLicenseNo($value): void
    {
        $this->license_no = strtoupper(trim($value));
    }

    public function updatedPlateNumber($value): void
    {
        $this->plate_number = strtoupper(trim($value));
    }

    public function updated($propertyName): void
    {
        if ($this->currentStep === 1 && $propertyName === 'email') {
            $this->validateOnly('email', $this->getStep1EmailRules(), $this->getStep1EmailMessages());
        } elseif ($this->currentStep === 2) {
            $this->validateOnly($propertyName, $this->getStep2Rules(), $this->getStep2Messages());
        } elseif ($this->currentStep === 3) {
            $this->validateOnly($propertyName, $this->getStep3Rules(), $this->getStep3Messages());
        } elseif ($this->currentStep === 4) {
            $this->validateOnly($propertyName, $this->getStep4Rules(), $this->getStep4Messages());
        } elseif ($this->currentStep === 5) {
            if ($propertyName === 'password') {
                $this->validateOnly('password', $this->getStep5Rules(), $this->getStep5Messages());
                if (! empty($this->password_confirmation)) {
                    $this->validateOnly('password_confirmation', ['password_confirmation' => ['same:password']], $this->getStep5Messages());
                }
            } elseif ($propertyName === 'password_confirmation') {
                $this->validateOnly('password_confirmation', ['password_confirmation' => ['required', 'same:password']], $this->getStep5Messages());
            }
        }
    }

    protected function getStep1EmailRules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                function ($attr, $value, $fail) {
                    $normalized = strtolower(trim($value));
                    if (
                        User::whereRaw('LOWER(TRIM(email)) = ?', [$normalized])->exists() ||
                        Rider::whereRaw('LOWER(TRIM(email)) = ?', [$normalized])->exists()
                    ) {
                        $fail('This email address is already in use.');
                    }
                },
            ],
        ];
    }

    protected function getStep1EmailMessages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }

    public function sendCode(OtpService $otpService): void
    {
        $this->email = strtolower(trim($this->email));

        $this->validateOnly('email', $this->getStep1EmailRules(), $this->getStep1EmailMessages());

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
        $this->attemptsRemaining = RegistrationOtp::MAX_ATTEMPTS;
        $this->resendCooldown = $cooldown;
        $this->resetErrorBag();
    }

    public function resendCode(OtpService $otpService): void
    {
        $this->sendCode($otpService);
    }

    public function verifyCode(OtpService $otpService): void
    {
        $this->email = strtolower(trim($this->email));
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
            'logistics_profile_id' => ['required', 'exists:logistics_profiles,id'],
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'birthday' => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->format('Y-m-d'),
                'after_or_equal:' . now()->subYears(100)->format('Y-m-d'),
            ],
            'contact_no' => [
                'required',
                'regex:/^9\d{9}$/',
                function ($attr, $value, $fail) {
                    $digits = preg_replace('/\D/', '', $value);
                    $clean = ltrim($digits, '0');
                    $plusFormat = '+63' . $clean;
                    $zeroFormat = '0' . $clean;

                    $existsInUsers = User::whereIn('contact_no', [$plusFormat, $zeroFormat, $clean])->exists();
                    $existsInRiders = Rider::whereIn('contact_no', [$plusFormat, $zeroFormat, $clean])->exists();

                    if ($existsInUsers || $existsInRiders) {
                        $fail('This mobile number is already in use.');
                    }
                },
            ],
        ];
    }

    protected function getStep2Messages(): array
    {
        return [
            'logistics_profile_id.required' => 'Please select a logistics company to apply to.',
            'logistics_profile_id.exists' => 'The selected logistics company is invalid.',
            'first_name.required' => 'First name is required.',
            'first_name.min' => 'First name must be between 2 and 50 characters.',
            'first_name.max' => 'First name must be between 2 and 50 characters.',
            'first_name.regex' => 'Only letters are allowed in the first name.',
            'last_name.required' => 'Last name is required.',
            'last_name.min' => 'Last name must be between 2 and 50 characters.',
            'last_name.max' => 'Last name must be between 2 and 50 characters.',
            'last_name.regex' => 'Only letters are allowed in the last name.',
            'birthday.required' => 'Date of birth is required.',
            'birthday.before_or_equal' => 'You must be at least 18 years old.',
            'contact_no.required' => 'Mobile number is required.',
            'contact_no.regex' => 'Please enter a valid 10-digit mobile number starting with 9 (e.g. 9451239876).',
        ];
    }

    protected function getStep3Rules(): array
    {
        return [
            'license_no' => [
                'required',
                'string',
                'regex:/^[A-Za-z]\d{2}-\d{2}-\d{6}$/',
                function ($attr, $value, $fail) {
                    $normalized = strtoupper(trim($value));
                    if (Rider::whereRaw('UPPER(TRIM(license_no)) = ?', [$normalized])->exists()) {
                        $fail('This driver\'s license number is already registered.');
                    }
                },
            ],
            'license_expiry_date' => [
                'required',
                'date',
                'after_or_equal:' . now()->format('Y-m-d'),
            ],
            'vehicle_type' => [
                'required',
                'string',
                'in:' . implode(',', $this->vehicleTypes),
            ],
            'plate_number' => [
                'required',
                'string',
                'max:15',
                'regex:/^[A-Za-z0-9\s\-]+$/',
                function ($attr, $value, $fail) {
                    $normalized = strtoupper(trim($value));
                    if (Rider::whereRaw('UPPER(TRIM(plate_number)) = ?', [$normalized])->exists()) {
                        $fail('This plate number is already registered.');
                    }
                },
            ],
        ];
    }

    protected function getStep3Messages(): array
    {
        return [
            'license_no.required' => 'Driver\'s license number is required.',
            'license_no.regex' => 'Driver\'s license number must follow the format A00-00-000000.',
            'license_expiry_date.required' => 'License expiry date is required.',
            'license_expiry_date.after_or_equal' => 'Your driver\'s license is expired. Please provide a valid license.',
            'vehicle_type.required' => 'Please select a vehicle type.',
            'plate_number.required' => 'Plate number is required.',
            'plate_number.regex' => 'Please enter a valid plate number.',
        ];
    }

    protected function getStep4Rules(): array
    {
        return [
            'drivers_license' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'vehicle_or_cr' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    protected function getStep4Messages(): array
    {
        return [
            'drivers_license.required' => 'Driver\'s license document is required.',
            'drivers_license.mimes' => 'Accepted file types are JPG, PNG, and PDF only.',
            'drivers_license.max' => 'Driver\'s license file must not exceed 5 MB (5,120 KB).',
            'vehicle_or_cr.required' => 'Vehicle OR/CR document is required.',
            'vehicle_or_cr.mimes' => 'Accepted file types are JPG, PNG, and PDF only.',
            'vehicle_or_cr.max' => 'Vehicle OR/CR file must not exceed 5 MB (5,120 KB).',
        ];
    }

    protected function getStep5Rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'min:8',
                function ($attr, $value, $fail) {
                    if (! preg_match('/[A-Z]/', $value)) {
                        $fail('Password must contain at least 1 uppercase letter.');
                    }
                    if (! preg_match('/[a-z]/', $value)) {
                        $fail('Password must contain at least 1 lowercase letter.');
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
        ];
    }

    protected function getStep5Messages(): array
    {
        return [
            'password.required' => 'Please enter a password.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password_confirmation.required' => 'Please confirm your password.',
            'password_confirmation.same' => 'The passwords do not match.',
        ];
    }

    public function nextStep(int $step): void
    {
        if ($step === 2) {
            $this->validate($this->getStep2Rules(), $this->getStep2Messages());
        } elseif ($step === 3) {
            $this->validate($this->getStep3Rules(), $this->getStep3Messages());
        } elseif ($step === 4) {
            $this->validate($this->getStep4Rules(), $this->getStep4Messages());
        } elseif ($step === 5) {
            $this->validate($this->getStep5Rules(), $this->getStep5Messages());
        }

        $this->currentStep = $step + 1;
    }

    public function backToStep(int $step): void
    {
        if ($step < $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    public function register(): void
    {
        if ($this->registrationSuccessful) {
            return;
        }

        $this->validate(
            array_merge(
                $this->getStep2Rules(),
                $this->getStep3Rules(),
                $this->getStep4Rules(),
                $this->getStep5Rules()
            ),
            array_merge(
                $this->getStep2Messages(),
                $this->getStep3Messages(),
                $this->getStep4Messages(),
                $this->getStep5Messages()
            )
        );

        $this->email = strtolower(trim($this->email));

        $record = RegistrationOtp::where('email', $this->email)
            ->where('verification_token', $this->verificationToken)
            ->first();

        if (! $record || ! $record->isVerified() || ! $record->isReservationActive()) {
            $this->addError('email', 'Your email verification has expired. Please verify your email again.');
            $this->currentStep = 1;
            $this->codeSent = false;
            $this->verificationToken = null;
            return;
        }

        if (
            User::whereRaw('LOWER(TRIM(email)) = ?', [$this->email])->exists() ||
            Rider::whereRaw('LOWER(TRIM(email)) = ?', [$this->email])->exists()
        ) {
            $this->addError('email', 'This email address is already in use.');
            $this->currentStep = 1;
            $this->codeSent = false;
            $this->verificationToken = null;
            return;
        }

        $formattedContactNo = '+63' . ltrim($this->contact_no, '0');

        DB::transaction(function () use ($formattedContactNo) {
            $licensePath = $this->drivers_license->store('rider_documents/licenses', 'public');
            $orCrPath = $this->vehicle_or_cr->store('rider_documents/or_cr', 'public');

            Rider::create([
                'logistics_profile_id' => (int) $this->logistics_profile_id,
                'first_name' => ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $this->first_name)))),
                'last_name' => ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $this->last_name)))),
                'birthday' => $this->birthday,
                'email' => $this->email,
                'email_verified_at' => now(),
                'contact_no' => $formattedContactNo,
                'license_no' => strtoupper(trim($this->license_no)),
                'license_expiry_date' => $this->license_expiry_date,
                'vehicle_type' => $this->vehicle_type,
                'plate_number' => strtoupper(trim($this->plate_number)),
                'license_path' => $licensePath,
                'or_cr_path' => $orCrPath,
                'password' => Hash::make($this->password),
                'status' => 'pending',
            ]);

            RegistrationOtp::where('email', $this->email)->delete();
        });

        $this->registrationSuccessful = true;
    }

    public function render()
    {
        return view('livewire.auth.rider-register-wizard');
    }
}