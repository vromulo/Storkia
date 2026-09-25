<?php

namespace App\Livewire\Seller;

use App\Models\SellerApplication;
use App\Models\SellerProfile;
use App\Services\AddressService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReapplyApplication extends Component
{
    use WithFileUploads;

    public string $contact_no = '';
    public string $province_code = '';
    public string $municipality_code = '';
    public string $barangay_code = '';
    public string $province = '';
    public string $municipality = '';
    public string $barangay = '';
    public string $street = '';
    public string $house_details = '';
    public string $business_name = '';
    public string $line_of_business = '';
    public $valid_id;
    public $business_permit;
    public array $provinces = [];
    public array $municipalities = [];
    public array $barangays = [];
    public ?SellerProfile $profile = null;
    public ?SellerApplication $latestApp = null;
    public bool $isSubmitted = false;

    public function mount(AddressService $addressService)
    {
        $user = Auth::user();
        $this->latestApp = SellerApplication::where('user_id', $user->id)->latest('version')->firstOrFail();
        abort_unless($this->latestApp->status === 'rejected', 403);

        $this->contact_no = ltrim($this->latestApp->contact_no, '+63');
        $this->province = $this->latestApp->province;
        $this->municipality = $this->latestApp->municipality;
        $this->barangay = $this->latestApp->barangay;
        $this->street = $this->latestApp->street ?? '';
        $this->house_details = $this->latestApp->house_details ?? '';
        $this->business_name = $this->latestApp->business_name;
        $this->line_of_business = $this->latestApp->line_of_business;
        $this->provinces = $addressService->getProvinces();
    }

    public function updatedProvinceCode($code, AddressService $addressService)
    {
        $this->municipality_code = '';
        $this->barangay_code = '';
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

    public function reapply()
    {
        $this->validate([
            'contact_no' => ['required', 'regex:/^9\d{2}\s?\d{3}\s?\d{4}$/'],
            'province' => ['required', 'string'],
            'municipality' => ['required', 'string'],
            'barangay' => ['required', 'string'],
            'business_name' => ['required', 'string'],
            'line_of_business' => ['required', 'string'],
            'valid_id' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'business_permit' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $user = Auth::user();
        $formattedContactNo = '+63' . ltrim(preg_replace('/\D/', '', $this->contact_no), '0');
        $latestVersion = SellerApplication::where('user_id', $user->id)->max('version') ?? 1;

        $idPath = $this->valid_id
            ? $this->valid_id->store('seller_documents/ids', 'public')
            : $this->latestApp->id_path;

        $permitPath = $this->business_permit
            ? $this->business_permit->store('seller_documents/permits', 'public')
            : $this->latestApp->permit_path;

        SellerApplication::create([
            'user_id' => $user->id,
            'version' => $latestVersion + 1,
            'contact_no' => $formattedContactNo,
            'province' => $this->province,
            'municipality' => $this->municipality,
            'barangay' => $this->barangay,
            'street' => $this->street,
            'house_details' => $this->house_details,
            'business_name' => $this->business_name,
            'line_of_business' => $this->line_of_business,
            'id_path' => $idPath,
            'permit_path' => $permitPath,
            'status' => 'pending',
        ]);

        $this->isSubmitted = true;
    }

    public function render()
    {
        return view('livewire.seller.reapply-application');
    }
}