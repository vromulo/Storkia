<?php

namespace App\Livewire\Logistics;

use App\Models\Courier;
use App\Models\LogisticsEmployeeHubAssignment;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\User;
use App\Services\InternalStorkiaEmailService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateCourierWizard extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;
    public bool $creationSuccessful = false;

    public string $first_name = '';
    public string $last_name = '';
    public string $birthday = '';
    public string $email_prefix = '';
    public string $email = '';
    public string $contact_no = '';

    public string $logistics_hub_id = '';
    public string $home_hub = '';
    public array $eligibleHubs = [];

    public string $license_no = '';
    public string $license_type = 'Professional';
    public array $dl_codes = [];
    public string $license_expiry_date = '';
    public string $vehicle_type = '';
    public string $plate_number = '';
    public string $load_capacity = '';

    public array $licenseTypes = [
        'Professional',
        'Non-professional',
        'Student permit',
    ];

    public array $availableDlCodes = [
        'A',
        'A1',
        'B',
        'B1',
        'B2',
        'C',
        'D',
        'BE',
        'CE',
    ];

    public array $vehicleTypes = [
        'Van',
        '4-Wheeler Closed Van Truck',
        '6-Wheeler Truck',
    ];

    public $drivers_license;
    public $vehicle_or_cr;
    public $nbi_clearance;

    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $profile = $user?->logisticsProfile;

        if (! $user || ! $profile) {
            abort(403, 'Your logistics hub must be approved to add couriers.');
        }

        $profile->ensureMainHubExists();

        $hubsQuery = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->orderByDesc('is_main_hub')
            ->orderBy('municipality');

        if ($user->isBranchLogisticsAccount() && $user->branchLogisticsHub) {
            $hubsQuery->where('id', $user->branchLogisticsHub->id);
        }

        $hubs = $hubsQuery->get();

        $this->eligibleHubs = $hubs->map(fn (LogisticsHub $h) => [
            'id'           => (string) $h->id,
            'hub_name'     => $h->hub_name,
            'municipality' => $h->municipality,
            'province'     => $h->province,
            'display_type' => $h->display_type,
            'label'        => "{$h->display_type} — {$h->municipality} ({$h->hub_name})",
        ])->values()->all();

        if (! empty($this->eligibleHubs)) {
            $this->logistics_hub_id = (string) $this->eligibleHubs[0]['id'];
            $this->home_hub = $this->eligibleHubs[0]['hub_name'];
        }
    }

    public function updatedFirstName($value): void
    {
        $this->first_name = substr(preg_replace('/\s{2,}/', ' ', ltrim((string) $value)), 0, 50);
    }

    public function updatedLastName($value): void
    {
        $this->last_name = substr(preg_replace('/\s{2,}/', ' ', ltrim((string) $value)), 0, 50);
    }

    public function updatedEmailPrefix($value): void
    {
        $this->email_prefix = InternalStorkiaEmailService::sanitizeLivePrefix((string) $value);
        $this->email = InternalStorkiaEmailService::buildEmail($this->email_prefix);
    }

    public function updatedLogisticsHubId($value): void
    {
        $found = collect($this->eligibleHubs)->firstWhere('id', (string) $value);
        $this->home_hub = $found ? $found['hub_name'] : '';
    }

    public function updatedContactNo($value): void
    {
        $this->contact_no = substr(preg_replace('/\D/', '', (string) $value), 0, 11);
    }

    public function updatedLicenseNo($value): void
    {
        $this->license_no = substr(preg_replace('/[^A-Z0-9\-]/', '', strtoupper(trim((string) $value))), 0, 13);
    }

    public function updatedPlateNumber($value): void
    {
        $this->plate_number = substr(preg_replace('/[^A-Z0-9\s\-]/', '', strtoupper(trim((string) $value))), 0, 8);
    }

    public function updatedLoadCapacity($value): void
    {
        $this->load_capacity = substr(preg_replace('/\D/', '', (string) $value), 0, 5);
    }

    public function updatedVehicleType(): void
    {
        $this->validateOnly('vehicle_type', $this->getStep2Rules(), $this->getStep2Messages());

        if (! empty($this->dl_codes)) {
            $this->validateOnly('dl_codes', $this->getStep2Rules(), $this->getStep2Messages());
        }

        if ($this->load_capacity !== '') {
            $this->validateOnly('load_capacity', $this->getStep2Rules(), $this->getStep2Messages());
        }
    }

    public function updatedDlCodes(): void
    {
        $this->validateOnly('dl_codes', $this->getStep2Rules(), $this->getStep2Messages());
    }

    public function updated($propertyName): void
    {
        if ($this->currentStep === 1) {
            $this->validateOnly($propertyName, $this->getStep1Rules(), $this->getStep1Messages());
        } elseif ($this->currentStep === 2) {
            if (str_starts_with($propertyName, 'dl_codes')) {
                $this->validateOnly('dl_codes', $this->getStep2Rules(), $this->getStep2Messages());
            } else {
                $this->validateOnly($propertyName, $this->getStep2Rules(), $this->getStep2Messages());
            }
        } elseif ($this->currentStep === 3) {
            $this->validateOnly($propertyName, $this->getStep3Rules(), $this->getStep3Messages());
        } elseif ($this->currentStep === 4) {
            if ($propertyName === 'password') {
                $this->validateOnly('password', $this->getStep4Rules(), $this->getStep4Messages());
                if (! empty($this->password_confirmation)) {
                    $this->validateOnly('password_confirmation', ['password_confirmation' => ['same:password']], $this->getStep4Messages());
                }
            } elseif ($propertyName === 'password_confirmation') {
                $this->validateOnly('password_confirmation', ['password_confirmation' => ['required', 'same:password']], $this->getStep4Messages());
            }
        }
    }

    protected function getStep1Rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'last_name'  => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'birthday'   => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->format('Y-m-d'),
                'after_or_equal:' . now()->subYears(100)->format('Y-m-d'),
            ],
            'email_prefix' => InternalStorkiaEmailService::prefixValidationRules(),
            'contact_no' => [
                'required',
                'regex:/^09\d{9}$/',
                function ($attr, $value, $fail) {
                    $digits = preg_replace('/\D/', '', (string) $value);
                    $clean = ltrim($digits, '0');
                    $plusFormat = '+63' . $clean;
                    $zeroFormat = '0' . $clean;

                    $existsInUsers = User::whereIn('contact_no', [$plusFormat, $zeroFormat, $clean])->exists();
                    $existsInRiders = Rider::whereIn('contact_no', [$plusFormat, $zeroFormat, $clean])->exists();
                    $existsInCouriers = Courier::whereIn('contact_no', [$plusFormat, $zeroFormat, $clean])->exists();

                    if ($existsInUsers || $existsInRiders || $existsInCouriers) {
                        $fail('This mobile number is already in use.');
                    }
                },
            ],
        ];
    }

    protected function getStep1Messages(): array
    {
        return array_merge(
            InternalStorkiaEmailService::prefixValidationMessages('email_prefix'),
            [
                'first_name.required'      => 'First name is required.',
                'first_name.min'           => 'First name must be between 2 and 50 characters.',
                'first_name.max'           => 'First name must be between 2 and 50 characters.',
                'first_name.regex'         => 'Only letters are allowed in the first name.',
                'last_name.required'       => 'Last name is required.',
                'last_name.min'            => 'Last name must be between 2 and 50 characters.',
                'last_name.max'            => 'Last name must be between 2 and 50 characters.',
                'last_name.regex'          => 'Only letters are allowed in the last name.',
                'birthday.required'        => 'Date of birth is required.',
                'birthday.before_or_equal' => 'The courier must be at least 18 years old.',
                'birthday.after_or_equal'  => 'Please enter a valid date of birth.',
                'contact_no.required'      => 'Mobile number is required.',
                'contact_no.regex'         => 'Please enter a valid 11-digit mobile number starting with 09 (e.g. 09276543210).',
            ]
        );
    }

    protected function getStep2Rules(): array
    {
        $eligibleIds = array_column($this->eligibleHubs, 'id');

        return [
            'logistics_hub_id' => [
                'required',
                function ($attr, $value, $fail) use ($eligibleIds) {
                    if (! in_array((string) $value, $eligibleIds, true)) {
                        $fail('The selected Municipality Hub does not belong to your logistics company.');
                    }
                },
            ],
            'license_no' => [
                'required',
                'string',
                'size:13',
                'regex:/^[A-Za-z]\d{2}-\d{2}-\d{6}$/',
                function ($attr, $value, $fail) {
                    $normalized = strtoupper(trim((string) $value));
                    if (
                        Rider::whereRaw('UPPER(TRIM(license_no)) = ?', [$normalized])->exists() ||
                        Courier::whereRaw('UPPER(TRIM(license_no)) = ?', [$normalized])->exists()
                    ) {
                        $fail('This driver\'s license number is already registered.');
                    }
                },
            ],
            'license_type' => [
                'required',
                'string',
                function ($attr, $value, $fail) {
                    if ($value !== 'Professional') {
                        $fail('Couriers need a Professional driver\'s license.');
                    }
                },
            ],
            'dl_codes' => [
                'required',
                'array',
                'min:1',
                function ($attr, $value, $fail) {
                    $codes = array_map('strtoupper', (array) $value);
                    if ($this->vehicle_type === 'Van') {
                        if (! in_array('B', $codes, true) && ! in_array('B2', $codes, true)) {
                            $fail('Van requires DL code B or B2.');
                        }
                    } elseif (in_array($this->vehicle_type, ['4-Wheeler Closed Van Truck', '6-Wheeler Truck'], true)) {
                        if (! in_array('C', $codes, true)) {
                            $fail("{$this->vehicle_type} requires DL code C.");
                        }
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
                'min:6',
                'max:8',
                'regex:/^[A-Za-z0-9\s\-]+$/',
                function ($attr, $value, $fail) {
                    $normalized = strtoupper(trim((string) $value));
                    if (
                        Rider::whereRaw('UPPER(TRIM(plate_number)) = ?', [$normalized])->exists() ||
                        Courier::whereRaw('UPPER(TRIM(plate_number)) = ?', [$normalized])->exists()
                    ) {
                        $fail('This plate number is already registered.');
                    }
                },
            ],
            'load_capacity' => [
                'required',
                function ($attr, $value, $fail) {
                    $raw = trim((string) $value);
                    if (! preg_match('/^\d+$/', $raw)) {
                        $fail('Load capacity must be a positive whole number in kg.');
                        return;
                    }

                    $kg = (int) $raw;
                    if ($this->vehicle_type === 'Van') {
                        if ($kg < 100 || $kg > 1000) {
                            $fail('Load capacity for Van must be between 100 kg and 1,000 kg.');
                        }
                    } elseif ($this->vehicle_type === '4-Wheeler Closed Van Truck') {
                        if ($kg < 100 || $kg > 4000) {
                            $fail('Load capacity for 4-Wheeler Closed Van Truck must be between 100 kg and 4,000 kg.');
                        }
                    } else {
                        if ($kg < 100 || $kg > 10000) {
                            $fail('Load capacity must be between 100 kg and 10,000 kg.');
                        }
                    }
                },
            ],
        ];
    }

    protected function getStep2Messages(): array
    {
        return [
            'logistics_hub_id.required'         => 'Please select an eligible Municipality Hub.',
            'license_no.required'               => 'Driver\'s license number is required.',
            'license_no.size'                   => 'Driver\'s license number must follow the format A00-00-000000.',
            'license_no.regex'                  => 'Driver\'s license number must follow the format A00-00-000000.',
            'license_type.required'             => 'License type is required.',
            'dl_codes.required'                 => 'Please select at least one DL code.',
            'dl_codes.min'                      => 'Please select at least one DL code.',
            'license_expiry_date.required'      => 'License expiry date is required.',
            'license_expiry_date.after_or_equal'=> 'The driver\'s license is expired. Please provide a valid license.',
            'vehicle_type.required'             => 'Please select a vehicle type.',
            'vehicle_type.in'                   => 'Please select a valid vehicle type.',
            'plate_number.required'             => 'Plate number is required.',
            'plate_number.min'                  => 'Plate number must be between 6 and 8 characters.',
            'plate_number.max'                  => 'Plate number must not exceed 8 characters.',
            'plate_number.regex'                => 'Please enter a valid plate number.',
            'load_capacity.required'            => 'Load capacity is required.',
        ];
    }

    protected function getStep3Rules(): array
    {
        return [
            'drivers_license' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'vehicle_or_cr'   => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'nbi_clearance'   => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    protected function getStep3Messages(): array
    {
        return [
            'drivers_license.required' => 'Professional driver\'s license document is required.',
            'drivers_license.mimes'    => 'Accepted file types are JPG, PNG, and PDF only.',
            'drivers_license.max'      => 'Professional driver\'s license file must not exceed 5 MB.',
            'vehicle_or_cr.required'   => 'Vehicle OR/CR document is required.',
            'vehicle_or_cr.mimes'      => 'Accepted file types are JPG, PNG, and PDF only.',
            'vehicle_or_cr.max'        => 'Vehicle OR/CR file must not exceed 5 MB.',
            'nbi_clearance.required'   => 'NBI Clearance document is required.',
            'nbi_clearance.mimes'      => 'Accepted file types are JPG, PNG, and PDF only.',
            'nbi_clearance.max'        => 'NBI Clearance file must not exceed 5 MB.',
        ];
    }

    protected function getStep4Rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'min:8',
                'max:64',
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

    protected function getStep4Messages(): array
    {
        return [
            'password.required'              => 'Please enter a password.',
            'password.min'                   => 'Password must be at least 8 characters long.',
            'password.max'                   => 'Password must not exceed 64 characters.',
            'password_confirmation.required' => 'Please confirm the password.',
            'password_confirmation.same'     => 'The passwords do not match.',
        ];
    }

    public function nextStep(int $step): void
    {
        if ($step === 1) {
            $this->email_prefix = InternalStorkiaEmailService::normalizePrefix($this->email_prefix);
            $this->email = InternalStorkiaEmailService::buildEmail($this->email_prefix);
            $this->validate($this->getStep1Rules(), $this->getStep1Messages());
        } elseif ($step === 2) {
            $this->validate($this->getStep2Rules(), $this->getStep2Messages());
        } elseif ($step === 3) {
            $this->validate($this->getStep3Rules(), $this->getStep3Messages());
        } elseif ($step === 4) {
            $this->validate($this->getStep4Rules(), $this->getStep4Messages());
        }

        $this->currentStep = $step + 1;
    }

    public function backToStep(int $step): void
    {
        if ($step >= 1 && $step < $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    public function saveCourier(): void
    {
        if ($this->creationSuccessful) {
            return;
        }

        /** @var User|null $user */
        $user = Auth::user();
        $profile = $user?->logisticsProfile;
        if (! $user || ! $profile) {
            abort(403);
        }

        $this->email_prefix = InternalStorkiaEmailService::normalizePrefix($this->email_prefix);
        $this->email = InternalStorkiaEmailService::buildEmail($this->email_prefix);

        $this->validate(
            array_merge(
                $this->getStep1Rules(),
                $this->getStep2Rules(),
                $this->getStep3Rules(),
                $this->getStep4Rules()
            ),
            array_merge(
                $this->getStep1Messages(),
                $this->getStep2Messages(),
                $this->getStep3Messages(),
                $this->getStep4Messages()
            )
        );

        /** @var LogisticsHub $targetHub */
        $targetHub = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->findOrFail((int) $this->logistics_hub_id);

        if ($user->isBranchLogisticsAccount() && (int) $user->branchLogisticsHub?->id !== (int) $targetHub->id) {
            abort(403, 'Branch accounts can only assign couriers to their own hub.');
        }

        $digits = preg_replace('/\D/', '', $this->contact_no);
        $cleanMobile = ltrim($digits, '0');
        $formattedContactNo = '+63' . $cleanMobile;

        DB::transaction(function () use ($profile, $targetHub, $user, $formattedContactNo) {
            $licensePath = $this->drivers_license->store('courier_documents/licenses', 'public');
            $orCrPath = $this->vehicle_or_cr->store('courier_documents/or_cr', 'public');
            $nbiPath = $this->nbi_clearance->store('courier_documents/nbi_clearances', 'public');

            $courier = Courier::create([
                'logistics_profile_id' => $profile->id,
                'first_name'           => ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $this->first_name)))),
                'last_name'            => ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $this->last_name)))),
                'birthday'             => $this->birthday,
                'email'                => $this->email,
                'email_verified_at'    => now(),
                'contact_no'           => $formattedContactNo,
                'license_no'           => strtoupper(trim($this->license_no)),
                'license_type'         => $this->license_type,
                'dl_codes'             => array_values($this->dl_codes),
                'license_expiry_date'  => $this->license_expiry_date,
                'vehicle_type'         => $this->vehicle_type,
                'plate_number'         => strtoupper(trim($this->plate_number)),
                'load_capacity'        => (int) $this->load_capacity,
                'home_hub'             => $targetHub->hub_name,
                'license_path'         => $licensePath,
                'or_cr_path'           => $orCrPath,
                'nbi_clearance_path'   => $nbiPath,
                'password'             => Hash::make($this->password),
                'status'               => 'active',
            ]);

            LogisticsEmployeeHubAssignment::create([
                'logistics_profile_id' => $profile->id,
                'logistics_hub_id'     => $targetHub->id,
                'employee_type'        => 'courier',
                'courier_id'           => $courier->id,
                'municipality'         => $targetHub->municipality,
                'is_current'           => true,
                'assigned_by'          => $user->id,
                'assigned_at'          => now(),
                'remarks'              => "Assigned to {$targetHub->hub_name} ({$targetHub->municipality})",
            ]);
        });

        $this->creationSuccessful = true;
    }

    public function render()
    {
        return view('livewire.logistics.create-courier-wizard');
    }
}