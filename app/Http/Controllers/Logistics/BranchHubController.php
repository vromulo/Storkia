<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\LogisticsEmployeeHubAssignment;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\User;
use App\Services\AddressService;
use App\Services\InternalStorkiaEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchHubController extends Controller
{
    public function __construct(
        protected AddressService $addressService
    ) {}

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || ! $user->isMainLogisticsAccount()) {
            abort(403, 'Only the Main Logistics Account can access Branch Hub Management.');
        }

        $mainHub = $profile->ensureMainHubExists();

        $hubs = LogisticsHub::with([
            'branchUser',
            'activeCourierAssignments.courier',
            'activeRiderAssignments.rider.barangayCoverages',
        ])
            ->where('parent_logistics_profile_id', $profile->id)
            ->orderByDesc('is_main_hub')
            ->orderBy('municipality')
            ->get();

        $provinces = $this->addressService->getProvinces();
        $defaultProvince = $this->addressService->resolveProvinceByName($profile->province ?? 'Cavite');
        $defaultProvinceCode = $defaultProvince['code'] ?? '';
        $defaultProvinceName = $defaultProvince['name'] ?? ($profile->province ?: 'Cavite');
        $initialMunicipalities = $defaultProvinceCode
            ? $this->addressService->getMunicipalities((string) $defaultProvinceCode)
            : [];

        return view('logistics.branches.index', compact(
            'profile',
            'mainHub',
            'hubs',
            'provinces',
            'defaultProvinceCode',
            'defaultProvinceName',
            'initialMunicipalities'
        ));
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || ! $user->isMainLogisticsAccount()) {
            abort(403, 'Only the Main Logistics Account can create Branch Hub Accounts.');
        }

        $normalizedPrefix = InternalStorkiaEmailService::normalizePrefix($request->input('email_prefix'));
        $request->merge([
            'email_prefix' => $normalizedPrefix,
        ]);

        $validated = $request->validate([
            'email_prefix' => InternalStorkiaEmailService::prefixValidationRules(),
            'province_code' => ['nullable', 'string', 'max:30'],
            'province' => ['required', 'string', 'max:100'],
            'municipality_code' => ['nullable', 'string', 'max:30'],
            'municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'contact_no' => ['nullable', 'string', 'max:30'],
            'hub_name' => ['nullable', 'string', 'max:120'],
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
        ], array_merge(
            InternalStorkiaEmailService::prefixValidationMessages('email_prefix'),
            [
                'province.required' => 'Please select a valid province for the Branch Hub.',
                'municipality.required' => 'Please select a valid municipality for the Branch Hub.',
                'password.required' => 'Password is required.',
                'password.min' => 'Password must be at least 8 characters.',
                'password_confirmation.required' => 'Please confirm the password.',
                'password_confirmation.same' => 'Password and Confirm Password do not match.',
            ]
        ));

        $municipalities = ! empty($validated['province_code'])
            ? $this->addressService->getMunicipalities((string) $validated['province_code'])
            : $this->addressService->getMunicipalitiesByProvinceName($validated['province']);

        if (! empty($municipalities)) {
            $normTarget = $this->addressService->normalizeName($validated['municipality']);
            $matchedMun = collect($municipalities)->first(function ($m) use ($normTarget) {
                return $this->addressService->normalizeName($m['name'] ?? '') === $normTarget;
            });

            if (! $matchedMun) {
                throw ValidationException::withMessages([
                    'municipality' => 'The selected municipality does not belong to the chosen province.',
                ]);
            }

            $validated['municipality'] = $matchedMun['name'];
            $validated['municipality_code'] = (string) ($matchedMun['code'] ?? $validated['municipality_code']);
        }

        $fullEmail = InternalStorkiaEmailService::buildEmail($validated['email_prefix']);

        $branchHub = DB::transaction(function () use ($profile, $user, $validated, $fullEmail) {
            if (InternalStorkiaEmailService::emailExists($fullEmail)) {
                throw ValidationException::withMessages([
                    'email_prefix' => "The email {$fullEmail} is already registered.",
                ]);
            }

            $hubDisplayName = ! empty(trim((string) ($validated['hub_name'] ?? '')))
                ? trim((string) $validated['hub_name'])
                : "{$profile->business_name} - {$validated['municipality']} Branch Hub";

            $branchUser = User::create([
                'first_name' => $profile->business_name,
                'last_name' => $validated['municipality'] . ' Branch',
                'middle_initial' => null,
                'sex' => 'other',
                'contact_no' => ! empty($validated['contact_no']) ? trim($validated['contact_no']) : $profile->contact_no,
                'birthday' => '1990-01-01',
                'email' => $fullEmail,
                'password' => Hash::make($validated['password']),
                'password_changed_at' => now(),
                'role' => 'Logistics',
            ]);

            $branchUser->forceFill(['email_verified_at' => now()])->save();

            do {
                $hubCode = 'HUB-BR-' . str_pad((string) $profile->id, 3, '0', STR_PAD_LEFT) . '-' . strtoupper(Str::random(4));
            } while (LogisticsHub::where('hub_code', $hubCode)->exists());

            return LogisticsHub::create([
                'parent_logistics_profile_id' => $profile->id,
                'branch_user_id' => $branchUser->id,
                'hub_code' => $hubCode,
                'hub_name' => $hubDisplayName,
                'is_main_hub' => false,
                'email_prefix' => $validated['email_prefix'],
                'contact_no' => ! empty($validated['contact_no']) ? trim($validated['contact_no']) : null,
                'province_code' => $validated['province_code'] ?? null,
                'province' => $validated['province'],
                'municipality_code' => $validated['municipality_code'] ?? null,
                'municipality' => $validated['municipality'],
                'barangay' => ! empty($validated['barangay']) ? trim($validated['barangay']) : null,
                'street' => ! empty($validated['street']) ? trim($validated['street']) : null,
                'house_details' => null,
                'status' => 'active',
                'created_by' => $user->id,
            ]);
        });

        return redirect()
            ->route('logistics.branches.index')
            ->with('success', "Branch Hub \"{$branchHub->hub_name}\" ({$fullEmail}) created for {$branchHub->municipality}.");
    }

    public function toggleStatus(Request $request, LogisticsHub $hub)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || ! $user->isMainLogisticsAccount() || (int) $hub->parent_logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        if ($hub->is_main_hub) {
            return back()->withErrors(['hub' => 'The Main Hub cannot be deactivated.']);
        }

        $newStatus = $hub->status === 'active' ? 'inactive' : 'active';
        $hub->update(['status' => $newStatus]);

        return back()->with(
            'success',
            "Branch Hub \"{$hub->hub_name}\" has been " . ($newStatus === 'active' ? 'activated' : 'deactivated') . '.'
        );
    }

    public function municipalitiesByProvince(Request $request)
    {
        $provinceCode = (string) $request->query('province_code', '');
        $provinceName = (string) $request->query('province', '');

        $items = $provinceCode !== ''
            ? $this->addressService->getMunicipalities($provinceCode)
            : $this->addressService->getMunicipalitiesByProvinceName($provinceName);

        return response()->json([
            'municipalities' => array_values($items),
        ]);
    }

    public function barangaysByHub(Request $request, LogisticsHub $hub)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $hub->parent_logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        if ($user->isBranchLogisticsAccount() && (int) $user->branchLogisticsHub?->id !== (int) $hub->id) {
            abort(403);
        }

        $barangays = $this->addressService->getBarangaysForLocation(
            provinceName: (string) $hub->province,
            municipalityName: (string) $hub->municipality,
            municipalityCode: $hub->municipality_code
        );

        return response()->json([
            'hub_id' => $hub->id,
            'hub_name' => $hub->hub_name,
            'province' => $hub->province,
            'municipality' => $hub->municipality,
            'barangays' => array_values($barangays),
        ]);
    }
}