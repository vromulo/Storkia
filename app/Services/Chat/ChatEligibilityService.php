<?php

namespace App\Services\Chat;

use App\Models\LogisticsHub;
use App\Models\LogisticsProfile;
use App\Models\Message;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\ShipmentPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Decides who may chat with whom in the user-to-user chat (the `messages`
 * table). The Help Center (Admin <-> user) has its own rules and does not
 * go through this class.
 *
 *   Buyer     -> Seller      always (from a product page, store page or inbox)
 *   Seller    -> Buyer       only a buyer who already wrote to them, or who
 *                            has an order with them
 *   Seller    <-> Logistics  only when linked through a shipment. "Logistics"
 *                            means the main logistics account AND the branch
 *                            hub account that handled the shipment
 *   anything else            not allowed (e.g. Buyer <-> Logistics)
 */
class ChatEligibilityService
{
    /** @var array<string, Collection<int, int>> */
    private array $memo = [];

    public function canMessage(User $from, User $to): bool
    {
        if ($from->id === $to->id || $to->archived_at !== null) {
            return false;
        }

        return match ([$from->role, $to->role]) {
            ['Buyer', 'Seller']     => true,
            ['Seller', 'Buyer']     => $this->sellerMayReplyToBuyer($from, $to),
            ['Seller', 'Logistics'] => $this->linkedLogisticsUserIds($from)->contains($to->id),
            ['Logistics', 'Seller'] => $this->linkedSellerUserIds($from)->contains($to->id),
            default                 => false,
        };
    }

    /**
     * Accounts the viewer can open a brand-new chat with even though no
     * message has been exchanged yet (shown in their contact list).
     * Buyers start from product / store pages, so they have none here.
     *
     * @return Collection<int, int>
     */
    public function startableContactIds(User $viewer): Collection
    {
        return match ($viewer->role) {
            'Seller'    => $this->linkedLogisticsUserIds($viewer),
            'Logistics' => $this->linkedSellerUserIds($viewer),
            default     => collect(),
        };
    }

    /**
     * Seller -> user ids of every logistics account that handled one of the
     * seller's shipments: the main account plus each branch hub account.
     *
     * @return Collection<int, int>
     */
    public function linkedLogisticsUserIds(User $seller): Collection
    {
        return $this->memo['logistics:' . $seller->id] ??= $this->resolveLinkedLogisticsUserIds($seller);
    }

    /**
     * Logistics account -> user ids of the sellers it ships for. A branch hub
     * account only sees sellers whose parcels passed through its own hub.
     *
     * @return Collection<int, int>
     */
    public function linkedSellerUserIds(User $logistics): Collection
    {
        return $this->memo['sellers:' . $logistics->id] ??= $this->resolveLinkedSellerUserIds($logistics);
    }

    /**
     * Shipments the two accounts have in common, newest first. Used by the
     * "attach a shipment" picker in the Seller <-> Logistics chat.
     *
     * @return Collection<int, Shipment>
     */
    public function sharedShipments(User $a, User $b, int $limit = 20): Collection
    {
        $query = $this->sharedShipmentsQuery($a, $b);

        return $query ? $query->latest('id')->limit($limit)->get() : collect();
    }

    /**
     * A single shipment, only if it really belongs to this seller/logistics pair.
     */
    public function findSharedShipment(User $a, User $b, int $shipmentId): ?Shipment
    {
        $query = $this->sharedShipmentsQuery($a, $b);

        return $query ? $query->whereKey($shipmentId)->first() : null;
    }

    private function sellerMayReplyToBuyer(User $seller, User $buyer): bool
    {
        if (Message::where('user_id', $buyer->id)->where('receiver_id', $seller->id)->exists()) {
            return true;
        }

        return Schema::hasTable('seller_orders')
            && SellerOrder::where('seller_id', $seller->id)->where('buyer_id', $buyer->id)->exists();
    }

    /**
     * @return Collection<int, int>
     */
    private function resolveLinkedLogisticsUserIds(User $seller): Collection
    {
        if (! Schema::hasTable('shipments')) {
            return collect();
        }

        $sellerProfileId = SellerProfile::where('user_id', $seller->id)->value('id');
        if (! $sellerProfileId) {
            return collect();
        }

        $profileIds = Shipment::where('seller_profile_id', $sellerProfileId)
            ->distinct()
            ->pluck('logistics_profile_id');

        $mainUserIds = LogisticsProfile::whereIn('id', $profileIds)->pluck('user_id');

        $hubIds = collect();
        if (Schema::hasTable('shipment_packages')) {
            $hubIds = ShipmentPackage::where('seller_profile_id', $sellerProfileId)
                ->whereNotNull('logistics_hub_id')
                ->distinct()
                ->pluck('logistics_hub_id');
        }

        // Shipments created before their packages were generated only carry
        // the hub inside the address snapshot. Bounded to the latest 300.
        $snapshotHubIds = Shipment::where('seller_profile_id', $sellerProfileId)
            ->latest('id')
            ->limit(300)
            ->pluck('hub_address_snapshot')
            ->map(function ($snapshot) {
                $snapshot = is_array($snapshot) ? $snapshot : (json_decode((string) $snapshot, true) ?: []);

                return $snapshot['logistics_hub_id'] ?? null;
            });

        $hubIds = $hubIds->merge($snapshotHubIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $branchUserIds = $hubIds->isEmpty()
            ? collect()
            : LogisticsHub::whereIn('id', $hubIds)->whereNotNull('branch_user_id')->pluck('branch_user_id');

        return $mainUserIds->merge($branchUserIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function resolveLinkedSellerUserIds(User $logistics): Collection
    {
        if (! Schema::hasTable('shipments')) {
            return collect();
        }

        $profile = $logistics->logisticsProfile;
        if (! $profile) {
            return collect();
        }

        $query = $this->restrictToBranchHub(
            Shipment::where('logistics_profile_id', $profile->id),
            $logistics
        );

        $sellerProfileIds = $query->distinct()->pluck('seller_profile_id');

        return SellerProfile::whereIn('id', $sellerProfileIds)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function sharedShipmentsQuery(User $a, User $b): ?Builder
    {
        if (! Schema::hasTable('shipments')) {
            return null;
        }

        $seller = $a->role === 'Seller' ? $a : ($b->role === 'Seller' ? $b : null);
        $logistics = $a->role === 'Logistics' ? $a : ($b->role === 'Logistics' ? $b : null);

        if (! $seller || ! $logistics) {
            return null;
        }

        $sellerProfileId = SellerProfile::where('user_id', $seller->id)->value('id');
        $profile = $logistics->logisticsProfile;

        if (! $sellerProfileId || ! $profile) {
            return null;
        }

        return $this->restrictToBranchHub(
            Shipment::where('seller_profile_id', $sellerProfileId)
                ->where('logistics_profile_id', $profile->id),
            $logistics
        );
    }

    /**
     * Branch hub accounts only work with parcels routed through their own hub
     * (same rule the logistics Shipments page already applies).
     */
    private function restrictToBranchHub(Builder $query, User $logistics): Builder
    {
        if ($logistics->isBranchLogisticsAccount() && $logistics->branchLogisticsHub) {
            $hubId = (int) $logistics->branchLogisticsHub->id;

            $query->where(function (Builder $w) use ($hubId) {
                $w->whereHas('packages', fn ($p) => $p->where('logistics_hub_id', $hubId))
                    ->orWhere('hub_address_snapshot->logistics_hub_id', $hubId);
            });
        }

        return $query;
    }
}