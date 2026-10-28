<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\ShipmentPackage;
use App\Models\ShipmentVerification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShipmentVerificationService
{
    public function ensurePackagesForShipment(
        Shipment $shipment,
        SellerOrder $sellerOrder,
        string $handoverMethod = 'courier_pickup',
        ?int $logisticsHubId = null,
        int $packageCount = 1
    ): Collection {
        $existing = ShipmentPackage::where('shipment_id', $shipment->id)
            ->orderBy('package_number')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $sellerOrder->loadMissing('items');
        $items = $sellerOrder->items;

        if (! $logisticsHubId) {
            $mainHub = LogisticsHub::where('parent_logistics_profile_id', $shipment->logistics_profile_id)
                ->where('is_main_hub', true)
                ->first();
            $logisticsHubId = $mainHub?->id;
        }

        $manifestItems = $items->map(function ($item) {
            $variantLabel = implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name]));
            $skuSuffix = $item->variant_id ? '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $item->variant_id), -4)) : '';

            return [
                'order_item_id' => (int) $item->id,
                'product_id'    => (int) $item->product_id,
                'product_name'  => (string) $item->product_name,
                'variant'       => $variantLabel !== '' ? $variantLabel : null,
                'sku'           => 'SKU-' . str_pad((string) $item->product_id, 5, '0', STR_PAD_LEFT) . $skuSuffix,
                'quantity'      => (int) $item->quantity,
                'unit_weight'   => (float) $item->unit_weight,
                'line_weight'   => (float) $item->line_weight,
                'unit_price'    => (float) $item->discounted_unit_price,
                'line_total'    => (float) $item->line_total,
            ];
        })->values()->all();

        $totalUnits = max(1, (int) $items->sum('quantity'));
        $packageCount = max(1, min(10, $packageCount));
        $created = collect();

        $baseTrack = str_replace('TRK-', '', $shipment->tracking_number);

        for ($num = 1; $num <= $packageCount; $num++) {
            do {
                $pkgRef = 'PKG-' . $baseTrack . '-' . str_pad((string) $num, 2, '0', STR_PAD_LEFT);
                if (ShipmentPackage::where('package_reference', $pkgRef)->exists()) {
                    $pkgRef .= '-' . strtoupper(Str::random(3));
                }
            } while (ShipmentPackage::where('package_reference', $pkgRef)->exists());

            do {
                $qrToken = 'STORK-QR-' . Str::upper(Str::random(24)) . '-' . bin2hex(random_bytes(12));
            } while (ShipmentPackage::where('qr_token', $qrToken)->exists());

            $pkgWeight = $packageCount === 1
                ? (float) $shipment->total_weight
                : round(((float) $shipment->total_weight) / $packageCount, 3);

            $pkg = ShipmentPackage::create([
                'shipment_id'          => $shipment->id,
                'seller_order_id'      => $sellerOrder->id,
                'order_id'             => $shipment->order_id,
                'seller_profile_id'    => $shipment->seller_profile_id,
                'logistics_profile_id' => $shipment->logistics_profile_id,
                'logistics_hub_id'     => $logisticsHubId,
                'package_reference'    => $pkgRef,
                'qr_token'             => $qrToken,
                'package_number'       => $num,
                'total_packages'       => $packageCount,
                'handover_method'      => $handoverMethod,
                'package_weight'       => $pkgWeight,
                'cod_amount'           => (float) $shipment->cod_amount,
                'total_items_count'    => $totalUnits,
                'manifest_snapshot'    => $manifestItems,
                'seller_prepared_at'   => now(),
            ]);

            $created->push($pkg);
        }

        return $created;
    }

    public function lookupPackageForActor(
        string $rawInput,
        string $verificationMethod,
        string $stage,
        string $actorType,
        object $actor
    ): array {
        $cleaned = trim($rawInput);
        if ($cleaned === '') {
            throw ValidationException::withMessages([
                'lookup' => 'Please scan a QR Code or enter a Tracking / Package Reference number.',
            ]);
        }

        if (str_contains($cleaned, 'qr_token=')) {
            parse_str((string) parse_url($cleaned, PHP_URL_QUERY), $queryParts);
            if (! empty($queryParts['qr_token'])) {
                $cleaned = trim((string) $queryParts['qr_token']);
            }
        }

        $package = ShipmentPackage::with([
            'shipment.sellerOrder.order',
            'shipment.sellerOrder.items',
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.currentCourier',
            'shipment.currentRider',
            'shipment.latestPickupAssignment',
            'shipment.latestDeliveryAssignment',
            'logisticsHub',
        ])
            ->where('qr_token', $cleaned)
            ->orWhereRaw('UPPER(TRIM(package_reference)) = ?', [strtoupper($cleaned)])
            ->orWhereHas('shipment', function ($q) use ($cleaned) {
                $q->whereRaw('UPPER(TRIM(tracking_number)) = ?', [strtoupper($cleaned)]);
            })
            ->first();

        if (! $package || ! $package->shipment) {
            throw ValidationException::withMessages([
                'lookup' => 'No matching shipment or package was found for "' . e($cleaned) . '".',
            ]);
        }

        $shipment = $package->shipment;

        if ($package->manifest_snapshot === null || empty($package->manifest_snapshot)) {
            $this->ensurePackagesForShipment(
                shipment: $shipment,
                sellerOrder: $shipment->sellerOrder,
                handoverMethod: $package->handover_method ?: 'courier_pickup',
                logisticsHubId: $package->logistics_hub_id
            );
            $package->refresh();
        }

        $this->authorizeStageAndOwnership($shipment, $package, $stage, $actorType, $actor);

        $normalizedMethod = in_array(strtolower($verificationMethod), ['qr', 'manual'], true)
            ? strtolower($verificationMethod)
            : (str_starts_with(strtoupper($cleaned), 'STORK-QR-') ? 'qr' : 'manual');

        $verification = ShipmentVerification::create([
            'shipment_id'            => $shipment->id,
            'shipment_package_id'    => $package->id,
            'seller_order_id'        => $shipment->seller_order_id,
            'logistics_profile_id'   => $shipment->logistics_profile_id,
            'logistics_hub_id'       => $package->logistics_hub_id,
            'stage'                  => $stage,
            'verification_method'    => $normalizedMethod,
            'lookup_token_or_number' => substr($cleaned, 0, 100),
            'actor_type'             => $actorType,
            'actor_id'               => (int) $actor->id,
            'is_confirmed'           => false,
            'confirmed_at'           => null,
            'remarks'                => "Package inspected via " . strtoupper($normalizedMethod) . " for stage: {$stage}.",
        ]);

        return [
            'verification' => $verification,
            'package'      => $package,
            'shipment'     => $shipment,
            'payload'      => $this->formatShipmentPayload($shipment, $package, $verification),
        ];
    }

    public function requireConfirmedVerification(
        Shipment $shipment,
        string $stage,
        string $actorType,
        int $actorId,
        ?int $verificationId = null,
        ?string $fallbackMethod = null
    ): ShipmentVerification {
        $package = $shipment->primaryPackage
            ?? $this->ensurePackagesForShipment($shipment, $shipment->sellerOrder)->first();

        $query = ShipmentVerification::where('shipment_id', $shipment->id)
            ->where('stage', $stage)
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId);

        if ($verificationId) {
            $query->where('id', $verificationId);
        }

        /** @var ShipmentVerification|null $verification */
        $verification = $query->latest('id')->first();

        if (! $verification) {
            throw ValidationException::withMessages([
                'verification' => 'You must first scan the package QR Code or verify the tracking number and review the shipment manifest before confirming this handover step.',
            ]);
        }

        if (! $verification->is_confirmed) {
            $verification->update([
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ]);
        }

        $method = $fallbackMethod ?: $verification->verification_method;

        if ($package) {
            $updates = match ($stage) {
                'courier_pickup' => [
                    'courier_verified_at'         => now(),
                    'courier_verification_method' => $method,
                ],
                'seller_drop_off', 'logistics_receiving' => [
                    'hub_verified_at'         => now(),
                    'hub_verified_by'         => $actorType === 'Logistics' ? $actorId : $package->hub_verified_by,
                    'hub_verification_method' => $method,
                    'seller_dropped_off_at'   => $stage === 'seller_drop_off' ? now() : $package->seller_dropped_off_at,
                ],
                'rider_collection' => [
                    'rider_verified_at'         => now(),
                    'rider_verification_method' => $method,
                    'rider_collected_at'        => now(),
                ],
                'delivery_confirmation' => [
                    'delivery_verified_at'         => now(),
                    'delivery_verification_method' => $method,
                ],
                default => [],
            };

            if (! empty($updates)) {
                $package->update($updates);
            }
        }

        return $verification;
    }

    public function authorizeStageAndOwnership(
        Shipment $shipment,
        ShipmentPackage $package,
        string $stage,
        string $actorType,
        object $actor
    ): void {
        if ($actorType === 'Courier') {
            /** @var Courier $actor */
            if ((int) $shipment->logistics_profile_id !== (int) $actor->logistics_profile_id) {
                throw ValidationException::withMessages([
                    'lookup' => 'Unauthorized: This shipment belongs to another Logistics company.',
                ]);
            }

            if ((int) $shipment->current_courier_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'lookup' => 'Unauthorized: You are not the Courier assigned to this package.',
                ]);
            }

            $assignment = $shipment->latestPickupAssignment;
            if (! $assignment || (int) $assignment->courier_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'lookup' => 'No active pickup assignment found for your Courier account.',
                ]);
            }

            if ($stage === 'courier_pickup') {
                if ($assignment->status !== 'accepted') {
                    throw ValidationException::withMessages([
                        'lookup' => 'You must explicitly accept the pickup assignment before verifying and collecting the package from the Seller.',
                    ]);
                }
                if ($shipment->status !== 'courier_assigned') {
                    throw ValidationException::withMessages([
                        'lookup' => 'This shipment is not in Courier Assigned status.',
                    ]);
                }
            }

            return;
        }

        if ($actorType === 'Logistics') {
            /** @var User $actor */
            $profile = $actor->logisticsProfile;
            if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
                throw ValidationException::withMessages([
                    'lookup' => 'Unauthorized: This shipment belongs to another Logistics company.',
                ]);
            }

            if ($actor->isBranchLogisticsAccount()) {
                $branchHubId = (int) $actor->branchLogisticsHub?->id;
                if ($package->logistics_hub_id && (int) $package->logistics_hub_id !== $branchHubId) {
                    throw ValidationException::withMessages([
                        'lookup' => 'Unauthorized: This shipment is assigned to another hub within your company.',
                    ]);
                }
            }

            if (in_array($stage, ['logistics_receiving', 'seller_drop_off'], true)) {
                $allowedStatuses = ['awaiting_seller_drop_off', 'picked_up_from_seller', 'in_transit_to_hub'];
                if (! in_array($shipment->status, $allowedStatuses, true)) {
                    throw ValidationException::withMessages([
                        'lookup' => "Shipment cannot be received at the hub because its current status is \"{$shipment->status_label}\".",
                    ]);
                }
            }

            return;
        }

        if ($actorType === 'Rider') {
            /** @var Rider $actor */
            if ((int) $shipment->logistics_profile_id !== (int) $actor->logistics_profile_id) {
                throw ValidationException::withMessages([
                    'lookup' => 'Unauthorized: This shipment belongs to another Logistics company.',
                ]);
            }

            if ((int) $shipment->current_rider_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'lookup' => 'Unauthorized: You are not the Rider assigned to this delivery.',
                ]);
            }

            $deliveryAssignment = $shipment->latestDeliveryAssignment;
            if (! $deliveryAssignment || (int) $deliveryAssignment->rider_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'lookup' => 'No active delivery assignment found for your Rider account.',
                ]);
            }

            if ($stage === 'rider_collection') {
                if ($package->rider_accepted_at === null && $deliveryAssignment->status === 'assigned') {
                    throw ValidationException::withMessages([
                        'lookup' => 'You must first explicitly accept the delivery assignment before scanning/verifying the package for collection.',
                    ]);
                }

                if (! in_array($shipment->status, ['rider_assigned'], true)) {
                    throw ValidationException::withMessages([
                        'lookup' => 'Package must be in Rider Assigned status at the hub before collection.',
                    ]);
                }
            } elseif ($stage === 'delivery_confirmation') {
                if ($shipment->status !== 'out_for_delivery' || $deliveryAssignment->status !== 'out_for_delivery') {
                    throw ValidationException::withMessages([
                        'lookup' => 'Package must be Out for Delivery before verifying buyer handover.',
                    ]);
                }
            }

            return;
        }

        if ($actorType === 'Seller') {
            /** @var User $actor */
            if ((int) $shipment->sellerOrder?->seller_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'lookup' => 'Unauthorized: This shipment does not belong to your store.',
                ]);
            }
        }
    }

    public function formatShipmentPayload(
        Shipment $shipment,
        ShipmentPackage $package,
        ?ShipmentVerification $verification = null
    ): array {
        $pickup = $shipment->pickup_address_snapshot ?? [];
        $deliv = $shipment->delivery_address_snapshot ?? [];
        $hubSnap = $shipment->hub_address_snapshot ?? [];

        return [
            'verification_id'     => $verification?->id,
            'verification_method' => $verification?->verification_method,
            'stage'               => $verification?->stage,
            'shipment_id'         => $shipment->id,
            'tracking_number'     => $shipment->tracking_number,
            'package_id'          => $package->id,
            'package_reference'   => $package->package_reference,
            'package_number'      => $package->package_number,
            'total_packages'      => $package->total_packages,
            'handover_method'     => $package->handover_method,
            'handover_label'      => $package->handover_method_label,
            'parent_order_number' => $shipment->sellerOrder?->order?->order_number ?? 'N/A',
            'seller_order_number' => $shipment->sellerOrder?->seller_order_number ?? 'N/A',
            'current_status'      => $shipment->status,
            'status_label'        => $shipment->status_label,
            'cod_amount'          => number_format((float) $shipment->cod_amount, 2),
            'cod_amount_raw'      => (float) $shipment->cod_amount,
            'shipping_fee'        => number_format((float) $shipment->shipping_fee, 2),
            'total_weight'        => number_format((float) $package->package_weight, 2),
            'total_items_count'   => (int) $package->total_items_count,
            'seller' => [
                'business_name' => $shipment->sellerProfile?->business_name ?? ($pickup['business_name'] ?? 'Seller'),
                'contact_no'    => $pickup['contact_no'] ?? '',
                'municipality'  => $pickup['municipality'] ?? '',
                'province'      => $pickup['province'] ?? '',
                'full_address'  => $pickup['full_address'] ?? '',
            ],
            'logistics' => [
                'company_name' => $shipment->logisticsProfile?->business_name ?? ($hubSnap['business_name'] ?? 'Logistics Hub'),
                'hub_name'     => $package->logisticsHub?->hub_name ?? ($hubSnap['hub_name'] ?? ($hubSnap['business_name'] ?? 'Main Hub')),
                'municipality' => $package->logisticsHub?->municipality ?? ($hubSnap['municipality'] ?? ''),
                'province'     => $package->logisticsHub?->province ?? ($hubSnap['province'] ?? ''),
            ],
            'recipient' => [
                'name'         => $deliv['recipient_name'] ?? 'Buyer',
                'phone_number' => $deliv['phone_number'] ?? '',
                'barangay'     => $deliv['barangay'] ?? '',
                'municipality' => $deliv['municipality'] ?? '',
                'province'     => $deliv['province'] ?? '',
                'full_address' => $deliv['full_address'] ?? '',
            ],
            'manifest' => $package->manifest_snapshot ?? [],
        ];
    }
}