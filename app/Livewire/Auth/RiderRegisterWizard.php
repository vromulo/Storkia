<?php

namespace App\Livewire\Auth;

use App\Models\Courier;
use App\Models\LogisticsEmployeeHubAssignment;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\RiderBarangayCoverage;
use App\Models\User;
use App\Services\AddressService;
use App\Services\InternalStorkiaEmailService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithFileUploads;

class RiderRegisterWizard extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;
    public bool $registrationSuccessful = false;

    public string $email_prefix = '';
    public string $email = '';

    public string $logistics_profile_id = '';
    public string $logistics_hub_id = '';
    public string $selectedLogisticsName = '';
    public string $selectedHubMunicipality = '';
    public array $eligibleHubs = [];
    public array $availableBarangays = [];
    public array $selectedBarangays = [];

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

    public function mount(AddressService $addressService): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $profile = $user?->logisticsProfile;

        if (! $user || $user->role !== 'Logistics' || ! $profile) {
            abort(403, 'Unauthorized access. Rider accounts can only be created by an approved Logistics account.');
        }

        $this->logistics_profile_id = (string) $profile->id;
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
            'id'                => (string) $h->id,
            'hub_name'          => $h->hub_name,
            'province'          => $h->province,
            'municipality'      => $h->municipality,
            'municipality_code' => $h->municipality_code,
            'display_type'      => $h->display_type,
            'label'             => "{$h->display_type} — {$h->municipality} ({$h->hub_name})",
        ])->values()->all();

        if (! empty($this->eligibleHubs)) {
            $firstHub = $this->eligibleHubs[0];
            $this->logistics_hub_id = (string) $firstHub['id'];
            $this->selectedLogisticsName = $firstHub['hub_name'];
            $this->selectedHubMunicipality = $firstHub['municipality'];
            $this->loadBarangaysForHub($firstHub, $addressService);
        }
    }

    protected function loadBarangaysForHub(array $hubData, AddressService $addressService): void
    {
        $this->availableBarangays = $addressService->getBarangaysForLocation(
            provinceName: (string) ($hubData['province'] ?? ''),
            municipalityName: (string) ($hubData['municipality'] ?? ''),
            municipalityCode: $hubData['municipality_code'] ?? null
        );
    }

    public function updatedEmailPrefix($value): void
    {
        $this->email_prefix = InternalStorkiaEmailService::sanitizeLivePrefix((string) $value);
        $this->email = InternalStorkiaEmailService::buildEmail($this->email_prefix);
    }

    public function updatedLogisticsHubId($value, AddressService $addressService): void
    {
        $found = collect($this->eligibleHubs)->firstWhere('id', (string) $value);
        $this->selectedBarangays = [];

        if ($found) {
            $this->selectedLogisticsName = $found['hub_name'];
            $this->selectedHubMunicipality = $found['municipality'];
            $this->loadBarangaysForHub($found, $addressService);
        } else {
            $this->selectedLogisticsName = '';
            $this->selectedHubMunicipality = '';
            $this->availableBarangays = [];
        }
    }

    public function updatedContactNo($value): void
    {
        $this->contact_no = substr(preg_replace('/\D/', '', (string) $value), 0, 10);
    }

    public function updatedLicenseNo($value): void
    {
        $this->license_no = strtoupper(trim((string) $value));
    }

    public function updatedPlateNumber($value): void
    {
        $this->plate_number = strtoupper(trim((string) $value));
    }

    protected function getStep1Rules(): array
    {
        $eligibleHubIds = array_column($this->eligibleHubs, 'id');
        $allowedBarangayNames = array_map(
            fn ($b) => strtolower(trim((string) ($b['name'] ?? ''))),
            $this->availableBarangays
        );

        return [
            'email_prefix' => InternalStorkiaEmailService::prefixValidationRules(),
            'logistics_hub_id' => [
                'required',
                function ($attr, $value, $fail) use ($eligibleHubIds) {
                    if (! in_array((string) $value, $eligibleHubIds, true)) {
                        $fail('Selected hub does not belong to your logistics company.');
                    }
                },
            ],
            'selectedBarangays' => [
                'required',
                'array',
                'min:1',
                function ($attr, $value, $fail) use ($allowedBarangayNames) {
                    foreach ((array) $value as $bName) {
                        if (! in_array(strtolower(trim((string) $bName)), $allowedBarangayNames, true)) {
                            $fail("Barangay \"{$bName}\" does not belong to {$this->selectedHubMunicipality}.");
                        }
                    }
                },
            ],
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'last_name'  => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'birthday'   => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->format('Y-m-d'),
                'after_or_equal:' . now()->subYears(100)->format('Y-m-d'),
            ],
            'contact_no' => [
                'required',
                'regex:/^9\d{9}$/',
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
                'logistics_hub_id.required'   => 'Please select a company hub.',
                'selectedBarangays.required'  => 'Please select at least one Barangay within the hub municipality.',
                'selectedBarangays.min'       => 'Please select at least one Barangay within the hub municipality.',
                'first_name.required'         => 'First name is required.',
                'last_name.required'          => 'Last name is required.',
                'birthday.required'           => 'Date of birth is required.',
                'birthday.before_or_equal'    => 'The rider must be at least 18 years old.',
                'contact_no.required'         => 'Mobile number is required.',
                'contact_no.regex'            => 'Enter a valid 10-digit mobile number starting with 9 (e.g. 9451239876).',
            ]
        );
    }

    protected function getStep2Rules(): array
    {
        return [
            'license_no' => [
                'required',
                'string',
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
                    $normalized = strtoupper(trim((string) $value));
                    if (
                        Rider::whereRaw('UPPER(TRIM(plate_number)) = ?', [$normalized])->exists() ||
                        Courier::whereRaw('UPPER(TRIM(plate_number)) = ?', [$normalized])->exists()
                    ) {
                        $fail('This plate number is already registered.');
                    }
                },
            ],
        ];
    }

    protected function getStep2Messages(): array
    {
        return [
            'license_no.required'               => 'Driver\'s license number is required.',
            'license_no.regex'                  => 'License number must follow format A00-00-000000.',
            'license_expiry_date.required'      => 'License expiry date is required.',
            'license_expiry_date.after_or_equal'=> 'License is expired.',
            'vehicle_type.required'             => 'Please select a vehicle type.',
            'plate_number.required'             => 'Plate number is required.',
        ];
    }

    protected function getStep3Rules(): array
    {
        return [
            'drivers_license' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'vehicle_or_cr'   => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    protected function getStep3Messages(): array
    {
        return [
            'drivers_license.required' => 'Driver\'s license document is required.',
            'vehicle_or_cr.required'   => 'Vehicle OR/CR document is required.',
        ];
    }

    protected function getStep4Rules(): array
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

    protected function getStep4Messages(): array
    {
        return [
            'password.required'              => 'Please enter a password.',
            'password.min'                   => 'Password must be at least 8 characters long.',
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

    public function register(): void
    {
        if ($this->registrationSuccessful) {
            return;
        }

        /** @var User|null $user */
        $user = Auth::user();
        $profile = $user?->logisticsProfile;

        if (! $user || $user->role !== 'Logistics' || ! $profile) {
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
            abort(403, 'Branch accounts can only assign riders to their own hub.');
        }

        $formattedContactNo = '+63' . ltrim($this->contact_no, '0');

        $barangayLookup = [];
        foreach ($this->availableBarangays as $b) {
            $bName = trim((string) ($b['name'] ?? ''));
            if ($bName !== '') {
                $barangayLookup[strtolower($bName)] = [
                    'name' => $bName,
                    'code' => (string) ($b['code'] ?? ''),
                ];
            }
        }

        DB::transaction(function () use ($profile, $targetHub, $user, $formattedContactNo, $barangayLookup) {
            $licensePath = $this->drivers_license->store('rider_documents/licenses', 'public');
            $orCrPath = $this->vehicle_or_cr->store('rider_documents/or_cr', 'public');

            $rider = Rider::create([
                'logistics_profile_id' => $profile->id,
                'first_name'           => ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $this->first_name)))),
                'last_name'            => ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $this->last_name)))),
                'birthday'             => $this->birthday,
                'email'                => $this->email,
                'email_verified_at'    => now(),
                'contact_no'           => $formattedContactNo,
                'license_no'           => strtoupper(trim($this->license_no)),
                'license_expiry_date'  => $this->license_expiry_date,
                'vehicle_type'         => $this->vehicle_type,
                'plate_number'         => strtoupper(trim($this->plate_number)),
                'license_path'         => $licensePath,
                'or_cr_path'           => $orCrPath,
                'password'             => Hash::make($this->password),
                'status'               => 'approved',
                'approved_at'          => now(),
            ]);

            LogisticsEmployeeHubAssignment::create([
                'logistics_profile_id' => $profile->id,
                'logistics_hub_id'     => $targetHub->id,
                'employee_type'        => 'rider',
                'rider_id'             => $rider->id,
                'municipality'         => $targetHub->municipality,
                'is_current'           => true,
                'assigned_by'          => $user->id,
                'assigned_at'          => now(),
                'remarks'              => "Assigned to {$targetHub->hub_name} ({$targetHub->municipality})",
            ]);

            foreach ($this->selectedBarangays as $bName) {
                $key = strtolower(trim((string) $bName));
                if (! isset($barangayLookup[$key])) {
                    continue;
                }
                RiderBarangayCoverage::create([
                    'logistics_profile_id' => $profile->id,
                    'logistics_hub_id'     => $targetHub->id,
                    'rider_id'             => $rider->id,
                    'municipality_code'    => $targetHub->municipality_code,
                    'municipality'         => $targetHub->municipality,
                    'barangay_code'        => $barangayLookup[$key]['code'] ?: null,
                    'barangay_name'        => $barangayLookup[$key]['name'],
                    'is_active'            => true,
                    'assigned_by'          => $user->id,
                ]);
            }
        });

        $this->registrationSuccessful = true;
    }

    public function render()
    {
        return view('livewire.auth.rider-register-wizard');
    }
}