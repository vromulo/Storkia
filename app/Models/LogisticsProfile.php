<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsProfile extends Model
{
    protected $fillable = [
        'user_id',
        'contact_no',
        'province',
        'municipality',
        'barangay',
        'street',
        'house_details',
        'business_name',
        'logo_path',
        'id_path',
        'permit_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(LogisticsApplication::class, 'user_id', 'user_id')->latest('version');
    }

    public function hubs()
    {
        return $this->hasMany(LogisticsHub::class, 'parent_logistics_profile_id')
            ->orderByDesc('is_main_hub')
            ->orderBy('municipality');
    }

    public function mainHub()
    {
        return $this->hasOne(LogisticsHub::class, 'parent_logistics_profile_id')
            ->where('is_main_hub', true);
    }

    public function branchHubs()
    {
        return $this->hasMany(LogisticsHub::class, 'parent_logistics_profile_id')
            ->where('is_main_hub', false)
            ->latest('id');
    }

    public function employeeHubAssignments()
    {
        return $this->hasMany(LogisticsEmployeeHubAssignment::class, 'logistics_profile_id');
    }

    public function riderBarangayCoverages()
    {
        return $this->hasMany(RiderBarangayCoverage::class, 'logistics_profile_id');
    }

    public function shipmentPackages()
    {
        return $this->hasMany(ShipmentPackage::class, 'logistics_profile_id');
    }

    public function riders()
    {
        return $this->hasMany(Rider::class, 'logistics_profile_id');
    }

    public function couriers()
    {
        return $this->hasMany(Courier::class, 'logistics_profile_id');
    }

    public function sellerOrders()
    {
        return $this->hasMany(SellerOrder::class, 'logistics_profile_id');
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class, 'logistics_profile_id');
    }

    public function pickupAssignments()
    {
        return $this->hasMany(PickupAssignment::class, 'logistics_profile_id');
    }

    public function deliveryAssignments()
    {
        return $this->hasMany(DeliveryAssignment::class, 'logistics_profile_id');
    }

    public function codCollections()
    {
        return $this->hasMany(CodCollection::class, 'logistics_profile_id');
    }

    public function codRemittances()
    {
        return $this->hasMany(CodRemittance::class, 'logistics_profile_id');
    }

    public function commissionRecords()
    {
        return $this->hasMany(CommissionRecord::class, 'logistics_profile_id');
    }

    public function sellerSettlements()
    {
        return $this->hasMany(SellerSettlement::class, 'logistics_profile_id');
    }

    public function ensureMainHubExists(): LogisticsHub
    {
        $mainHub = $this->mainHub()->first();

        if (! $mainHub) {
            $mainHub = LogisticsHub::create([
                'parent_logistics_profile_id' => $this->id,
                'branch_user_id'              => null,
                'hub_code'                    => 'HUB-MAIN-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT),
                'hub_name'                    => $this->business_name . ' (Main Hub - ' . $this->municipality . ')',
                'is_main_hub'                 => true,
                'contact_no'                  => $this->contact_no,
                'province'                    => $this->province,
                'municipality'                => $this->municipality,
                'barangay'                    => $this->barangay,
                'street'                      => $this->street,
                'house_details'               => $this->house_details,
                'status'                      => 'active',
                'created_by'                  => $this->user_id,
            ]);
        }

        return $mainHub;
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->house_details,
            $this->street,
            $this->barangay,
            $this->municipality,
            $this->province,
        ])->filter()->implode(', ');
    }
}