<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\PickupAssignment;
use App\Models\ShipmentPackage;
use App\Models\ShipmentVerification;
use App\Services\CourierMobileSchemaService;
use App\Services\ShipmentVerificationService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourierPackageVerificationController extends Controller
{
    public function __construct(
        protected ShipmentVerificationService $shipmentVerificationService,
        protected CourierPickupController $courierPickupController
    ) {}

    public function verifyPackage(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        /** @var PickupAssignment|null $assignment */
        $assignment = PickupAssignment::with([
            'shipment.packages.logisticsHub',
            'shipment.sellerOrder.items',
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
        ])->find($id);

        if (
            ! $assignment
            || (int) $assignment->courier_id !== (int) $courier->id
            || (int) $assignment->logistics_profile_id !== (int) $courier->logistics_profile_id
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: This pickup assignment does not belong to your Courier account.',
            ], 403);
        }

        if ($assignment->status === 'assigned') {
            return response()->json([
                'status'  => 'error',
                'message' => 'You must first accept the pickup assignment before scanning or verifying packages.',
            ], 422);
        }

        if (! in_array($assignment->status, ['accepted'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This pickup assignment has already been collected or processed.',
            ], 422);
        }

        $validated = $request->validate([
            'lookup_input'        => ['required', 'string', 'max:160'],
            'verification_method' => ['nullable', 'in:qr,manual'],
        ], [
            'lookup_input.required' => 'Please scan a QR Code or enter a Tracking / Package Reference number.',
        ]);

        $rawInput = trim($validated['lookup_input']);
        if (str_contains($rawInput, 'qr_token=')) {
            parse_str((string) parse_url($rawInput, PHP_URL_QUERY), $queryParts);
            if (! empty($queryParts['qr_token'])) {
                $rawInput = trim((string) $queryParts['qr_token']);
            }
        }
        if (str_starts_with($rawInput, 'STORK-QR|')) {
            $parts = explode('|', $rawInput);
            $rawInput = trim((string) (end($parts) ?: $rawInput));
        }

        $shipment = $assignment->shipment;
        if (! $shipment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Shipment record not found.',
            ], 404);
        }

        if ($shipment->packages->isEmpty() && $shipment->sellerOrder) {
            $this->shipmentVerificationService->ensurePackagesForShipment($shipment, $shipment->sellerOrder);
            $shipment->load('packages.logisticsHub');
        }

        /** @var ShipmentPackage|null $matchedPackage */
        $matchedPackage = $shipment->packages->first(function (ShipmentPackage $pkg) use ($rawInput, $shipment) {
            return strcasecmp($pkg->qr_token, $rawInput) === 0
                || strcasecmp($pkg->package_reference, $rawInput) === 0
                || strcasecmp($shipment->tracking_number, $rawInput) === 0;
        });

        if (! $matchedPackage) {
            $otherPkgExists = ShipmentPackage::whereRaw('UPPER(TRIM(qr_token)) = ?', [strtoupper($rawInput)])
                ->orWhereRaw('UPPER(TRIM(package_reference)) = ?', [strtoupper($rawInput)])
                ->orWhereHas('shipment', fn ($q) => $q->whereRaw('UPPER(TRIM(tracking_number)) = ?', [strtoupper($rawInput)]))
                ->exists();

            return response()->json([
                'status'  => 'error',
                'message' => $otherPkgExists
                    ? "Rejected: Package \"{$rawInput}\" is not in this pickup assignment (#{$shipment->tracking_number})."
                    : "Not found: No package matched \"{$rawInput}\". Please check the QR label or tracking number.",
                'errors'  => [
                    'lookup_input' => [
                        $otherPkgExists
                            ? 'This parcel does not belong to the current pickup assignment.'
                            : 'No matching package or tracking number found.',
                    ],
                ],
            ], 422);
        }

        $method = $validated['verification_method']
            ?? (str_starts_with(strtoupper($rawInput), 'STORK-QR') ? 'qr' : 'manual');

        $alreadyVerified = $matchedPackage->courier_verified_at !== null;

        DB::transaction(function () use ($matchedPackage, $shipment, $courier, $method, $rawInput, $alreadyVerified) {
            if (! $alreadyVerified) {
                $matchedPackage->update([
                    'courier_verified_at'         => now(),
                    'courier_verification_method' => $method,
                ]);
            }

            ShipmentVerification::updateOrCreate(
                [
                    'shipment_id'         => $shipment->id,
                    'shipment_package_id' => $matchedPackage->id,
                    'stage'               => 'courier_pickup',
                    'actor_type'          => 'Courier',
                    'actor_id'            => $courier->id,
                ],
                [
                    'seller_order_id'        => $shipment->seller_order_id,
                    'logistics_profile_id'   => $shipment->logistics_profile_id,
                    'logistics_hub_id'       => $matchedPackage->logistics_hub_id,
                    'verification_method'    => $method,
                    'lookup_token_or_number' => substr($rawInput, 0, 100),
                    'is_confirmed'           => true,
                    'confirmed_at'           => now(),
                    'remarks'                => "Package {$matchedPackage->package_reference} verified via " . strtoupper($method) . " by Courier {$courier->full_name}.",
                ]
            );
        });

        $freshAssignment = $assignment->fresh([
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.packages.logisticsHub',
            'shipment.trackingLogs',
            'sellerOrder.items',
        ]);

        $formatted = $this->courierPickupController->formatAssignmentPayload($freshAssignment, true);

        return response()->json([
            'status'           => 'success',
            'already_verified' => $alreadyVerified,
            'message'          => $alreadyVerified
                ? "Already verified: Package {$matchedPackage->package_reference} was already scanned and is counted once ({$formatted['verified_packages_count']}/{$formatted['total_packages_count']})."
                : "Package {$matchedPackage->package_reference} verified via " . strtoupper($method) . " ({$formatted['verified_packages_count']}/{$formatted['total_packages_count']})! Review the manifest and confirm collection.",
            'data'             => [
                'verified_package_reference' => $matchedPackage->package_reference,
                'verification_method'        => $method,
                'already_verified'           => $alreadyVerified,
                'verified_packages_count'    => $formatted['verified_packages_count'],
                'total_packages_count'       => $formatted['total_packages_count'],
                'all_packages_verified'      => $formatted['all_packages_verified'],
                'assignment'                 => $formatted,
            ],
        ]);
    }
}