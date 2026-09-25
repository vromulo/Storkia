<?php

namespace App\Livewire\Auth;

use App\Models\LogisticsApplication;
use App\Models\RegistrationOtp;
use App\Models\User;
use App\Services\AddressService;
use App\Services\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithFileUploads;

class LogisticsRegisterWizard extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;
    public bool $codeSent = false;
    public bool $registrationSuccessful = false;

    // Step 1: Email
    public string $email = '';
    public string $code = '';
    public ?string $verificationToken = null;
    public int $resendCooldown = 0;
    public int $attemptsRemaining = RegistrationOtp::MAX_ATTEMPTS;

    // Step 2: Personal Info
    public string $first_name = '';
    public string $last_name = '';
    public string $middle_initial = '';
    public string $sex = '';
    public string $birthday = '';

    // Step 3: Contact & Address
    public string $contact_no = '';
    public string $province_code = '';
    public string $municipality_code = '';
    public string $barangay_code = '';
    public string $province = '';
    public string $municipality = '';
    public string $barangay = '';
    public string $street = '';
    public string $house_details = '';

    public array $provinces = [];
    public array $municipalities = [];
    public array $barangays = [];

    // Step 4: Hub Info
    public string $business_name = '';

    // Step 5: Documents
    public $valid_id;
    public $business_permit;

    // Step 6: Password
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(AddressService $addressService)
    {
        $this->provinces = $addressService->getProvinces();
    }

    public function updated($propertyName)
    {
        if ($this->currentStep === 1 && $propertyName === 'email') {
            $this->validateOnly('email', ['email' => ['required', 'email:rfc,dns']]);
        } elseif ($this->currentStep === 2) {
            $this->validateOnly($propertyName, $this->getStep2Rules(), $this->getStep2Messages());
        } elseif ($this->currentStep === 3) {
            if ($propertyName === 'contact_no') {
                $this->validateOnly('contact_no', ['contact_no' => ['required', 'regex:/^9\d{2}\s?\d{3}\s?\d{4}$/']]);
            } else {
                $this->validateOnly($propertyName, $this->getStep3Rules());
            }
        } elseif ($this->currentStep === 4) {
            $this->validateOnly($propertyName, $this->getStep4Rules());
        } elseif ($this->currentStep === 5) {
            $this->validateOnly($propertyName, $this->getStep5Rules());
        } elseif ($this->currentStep === 6) {
            if ($propertyName === 'password') {
                $this->validateOnly('password', $this->getStep6Rules(), $this->getStep6Messages());
                if (!empty($this->password_confirmation)) {
                    $this->validateOnly('password_confirmation', ['password_confirmation' => ['same:password']]);
                }
            } elseif ($propertyName === 'password_confirmation') {
                $this->validateOnly('password_confirmation', ['required', 'same:password']);
            }
        }
    }

    public function updatedProvinceCode($code, AddressService $addressService)
    {
        $this->municipality_code = '';
        $this->barangay_code = '';
        $this->municipality = '';
        $this->barangay = '';
        $this->municipalities = [];
        $this->barangays = [];

        $prov = collect($this->provinces)->firstWhere('code', $code);
        $this->province = $prov ? $prov['name'] : '';

        if ($code) {
            $this->municipalities = $addressService->getMunicipalities($code);
        }
    }

    public function updatedMunicipalityCode($code, AddressService $addressService)
    {
        $this->barangay_code = '';
        $this->barangay = '';
        $this->barangays = [];

        $mun = collect($this->municipalities)->firstWhere('code', $code);
        $this->municipality = $mun ? $mun['name'] : '';

        if ($code) {
            $this->barangays = $addressService->getBarangays($code);
        }
    }

    public function updatedBarangayCode($code)
    {
        $brgy = collect($this->barangays)->firstWhere('code', $code);
        $this->barangay = $brgy ? $brgy['name'] : '';
    }

    public function sendCode(OtpService $otpService): void
    {
        $this->validateOnly('email', ['email' => ['required', 'email:rfc,dns']]);

        [$success, $error, $cooldown] = $otpService->sendOtp($this->email);

        if (! $success) {
            $this->addError('email', $error);
            return;
        }

        $this->code = '';
        $this->codeSent = true;
        $this->attemptsRemaining = RegistrationOtp::MAX_ATTEMPTS;
        $this->resendCooldown = $cooldown;
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
    }

    protected function getStep2Rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'regex:/^[A-Za-z\s]+$/'],
            'last_name'  => ['required', 'string', 'regex:/^[A-Za-z\s]+$/'],
            'middle_initial' => ['nullable', 'string', 'regex:/^[A-Za-z]$/'],
            'sex' => ['required', 'in:male,female'],
            'birthday' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d'), 'after_or_equal:' . now()->subYears(100)->format('Y-m-d')],
        ];
    }

    protected function getStep2Messages(): array
    {
        return ['birthday.before_or_equal' => 'You must be at least 18 years old.'];
    }

    protected function getStep3Rules(): array
    {
        return [
            'contact_no' => ['required', 'regex:/^9\d{2}\s?\d{3}\s?\d{4}$/'],
            'province_code' => ['required'],
            'municipality_code' => ['required'],
            'barangay_code' => ['required'],
        ];
    }

    protected function getStep4Rules(): array
    {
        return ['business_name' => ['required', 'string']];
    }

    protected function getStep5Rules(): array
    {
        return [
            'valid_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'business_permit' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    protected function getStep6Rules(): array
    {
        return [
            'password' => [
                'required', 'string', 'min:8',
                function ($attr, $value, $fail) {
                    if (!preg_match('/[A-Z]/', $value)) $fail('Password must contain at least 1 uppercase letter.');
                    if (!preg_match('/[0-9]/', $value)) $fail('Password must contain at least 1 number.');
                    if (!preg_match('/[\W_]/', $value)) $fail('Password must contain at least 1 special character.');
                },
            ],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    protected function getStep6Messages(): array
    {
        return [
            'password.required' => 'Please enter a password.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password_confirmation.same' => 'The password confirmation does not match.',
        ];
    }

    public function nextStep(int $step)
    {
        if ($step === 2) $this->validate($this->getStep2Rules(), $this->getStep2Messages());
        if ($step === 3) $this->validate($this->getStep3Rules());
        if ($step === 4) $this->validate($this->getStep4Rules());
        if ($step === 5) $this->validate($this->getStep5Rules());
        if ($step === 6) $this->validate($this->getStep6Rules(), $this->getStep6Messages());
        $this->currentStep = $step + 1;
    }

    public function backToStep(int $step)
    {
        if ($step < $this->currentStep) $this->currentStep = $step;
    }

    public function register()
    {
        $this->validate($this->getStep6Rules(), $this->getStep6Messages());
        $formattedContactNo = '+63' . ltrim(preg_replace('/\D/', '', $this->contact_no), '0');

        DB::transaction(function () use ($formattedContactNo) {
            $user = User::create([
                'first_name' => ucwords(strtolower($this->first_name)),
                'last_name' => ucwords(strtolower($this->last_name)),
                'middle_initial' => $this->middle_initial ? strtoupper($this->middle_initial) : null,
                'sex' => $this->sex,
                'email' => $this->email,
                'contact_no' => $formattedContactNo,
                'birthday' => $this->birthday,
                'password' => Hash::make($this->password),
                'role' => 'Logistics',
            ]);

            $idPath = $this->valid_id->store('logistics_documents/ids', 'public');
            $permitPath = $this->business_permit->store('logistics_documents/permits', 'public');

            LogisticsApplication::create([
                'user_id' => $user->id,
                'version' => 1,
                'contact_no' => $formattedContactNo,
                'province' => $this->province,
                'municipality' => $this->municipality,
                'barangay' => $this->barangay,
                'street' => $this->street,
                'house_details' => $this->house_details,
                'business_name' => $this->business_name,
                'id_path' => $idPath,
                'permit_path' => $permitPath,
                'status' => 'pending',
            ]);

            RegistrationOtp::where('email', $this->email)->delete();
        });

        $this->registrationSuccessful = true;
    }

    public function render()
    {
        return view('livewire.auth.logistics-register-wizard');
    }
}