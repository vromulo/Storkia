<?php

namespace App\Livewire\Logistics;

use App\Models\LogisticsApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReapplyLogisticsApplication extends Component
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

    public $valid_id;
    public $business_permit;

    public array $provinces = [];
    public array $municipalities = [];
    public array $barangays = [];

    public ?LogisticsApplication $latestApp = null;
    public bool $isSubmitted = false;

    public function mount()
    {
        $user = Auth::user();
        $this->latestApp = LogisticsApplication::where('user_id', $user->id)->latest('version')->firstOrFail();
        abort_unless($this->latestApp->status === 'rejected', 403);

        $this->contact_no = ltrim($this->latestApp->contact_no, '+63');
        $this->province = $this->latestApp->province;
        $this->municipality = $this->latestApp->municipality;
        $this->barangay = $this->latestApp->barangay;
        $this->street = $this->latestApp->street ?? '';
        $this->house_details = $this->latestApp->house_details ?? '';
        $this->business_name = $this->latestApp->business_name;
    }

    public function reapply()
    {
        $this->validate([
            'contact_no' => ['required', 'regex:/^9\d{2}\s?\d{3}\s?\d{4}$/'],
            'business_name' => ['required', 'string'],
        ]);

        $user = Auth::user();
        $formattedContactNo = '+63' . ltrim(preg_replace('/\D/', '', $this->contact_no), '0');
        $latestVersion = LogisticsApplication::where('user_id', $user->id)->max('version') ?? 1;

        $idPath = $this->valid_id 
            ? $this->valid_id->store('logistics_documents/ids', 'public') 
            : $this->latestApp->id_path;

        $permitPath = $this->business_permit 
            ? $this->business_permit->store('logistics_documents/permits', 'public') 
            : $this->latestApp->permit_path;

        LogisticsApplication::create([
            'user_id'       => $user->id,
            'version'       => $latestVersion + 1,
            'contact_no'    => $formattedContactNo,
            'province'      => $this->province,
            'municipality'  => $this->municipality,
            'barangay'      => $this->barangay,
            'street'        => $this->street,
            'house_details' => $this->house_details,
            'business_name' => $this->business_name,
            'id_path'       => $idPath,
            'permit_path'   => $permitPath,
            'status'        => 'pending',
        ]);

        $this->isSubmitted = true;
    }

    public function render()
    {
        return view('livewire.logistics.reapply-logistics-application');
    }
}