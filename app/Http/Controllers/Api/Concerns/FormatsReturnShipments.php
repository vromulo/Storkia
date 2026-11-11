<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ReturnShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

trait FormatsReturnShipments
{
    protected function returnShipmentRelations(): array
    {
        return [
            'returnRequest',
            'sellerOrder.order',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.sellerProfile',
        ];
    }

    protected function formatReturnShipment(ReturnShipment $returnShipment, string $leg): array
    {
        $isRider = $leg === 'rider';
        $order = $returnShipment->sellerOrder;

        $paidAt = $isRider ? $returnShipment->rider_paid_at : $returnShipment->courier_paid_at;
        $pay = $isRider ? $returnShipment->rider_pay : $returnShipment->courier_pay;
        $assignedAt = $isRider ? $returnShipment->rider_assigned_at : $returnShipment->courier_assigned_at;
        $attempts = (int) $returnShipment->pickup_attempts_count;

        $hubSnapshot = (array) ($returnShipment->hub_address_snapshot ?? []);

        $payload = [
            'id'                    => (int) $returnShipment->id,
            'tracking_number'       => (string) $returnShipment->tracking_number,
            'status'                => (string) $returnShipment->status,
            'status_label'          => (string) $returnShipment->status_label,
            'return_request_id'     => (int) $returnShipment->return_request_id,
            'return_request_number' => $returnShipment->returnRequest?->request_number,
            'reason_category'       => $returnShipment->returnRequest?->reason_category,
            'seller_order_id'       => (int) $returnShipment->seller_order_id,
            'seller_order_number'   => $order?->seller_order_number,
            'total_weight'          => round((float) $returnShipment->total_weight, 3),
            'assigned_at'           => $assignedAt?->toIso8601String(),
            'pay'                   => [
                'amount'  => $paidAt ? round((float) $pay, 2) : null,
                'is_paid' => $paidAt !== null,
                'paid_at' => $paidAt?->toIso8601String(),
            ],
            'hub'                   => $this->returnParty(
                $hubSnapshot,
                (string) ($hubSnapshot['hub_name'] ?? ($hubSnapshot['name'] ?? 'Sorting hub')),
                (string) ($hubSnapshot['contact_no'] ?? '')
            ),
            'items'                 => $this->returnItems($returnShipment),
        ];

        if ($isRider) {
            $pickupSnapshot = (array) ($returnShipment->pickup_address_snapshot ?? []);

            $payload['attempts_made'] = $attempts;
            $payload['attempt_number'] = $attempts + 1;
            $payload['max_attempts'] = ReturnShipment::MAX_PICKUP_ATTEMPTS;
            $payload['pickup_failure_reason'] = $returnShipment->pickup_failure_reason;
            $payload['picked_up_from_buyer_at'] = $returnShipment->picked_up_from_buyer_at?->toIso8601String();
            $payload['arrived_at_hub_at'] = $returnShipment->arrived_at_hub_at?->toIso8601String();
            $payload['buyer'] = $this->returnParty(
                $pickupSnapshot,
                (string) ($pickupSnapshot['recipient_name'] ?? ($order?->buyer?->full_name ?? 'Buyer')),
                (string) ($pickupSnapshot['phone_number'] ?? '')
            );

            return $payload;
        }

        $returnSnapshot = (array) ($returnShipment->return_address_snapshot ?? []);

        $payload['picked_up_from_hub_at'] = $returnShipment->picked_up_from_hub_at?->toIso8601String();
        $payload['delivered_to_seller_at'] = $returnShipment->delivered_to_seller_at?->toIso8601String();
        $payload['seller'] = $this->returnParty(
            $returnSnapshot,
            (string) ($order?->sellerProfile?->business_name ?? ($returnSnapshot['business_name'] ?? 'Seller')),
            (string) ($returnSnapshot['phone_number'] ?? '')
        );

        return $payload;
    }

    protected function returnParty(array $snapshot, string $name, string $phone): array
    {
        $barangay = (string) ($snapshot['barangay'] ?? '');
        $municipality = (string) ($snapshot['municipality'] ?? '');
        $province = (string) ($snapshot['province'] ?? '');

        $fullAddress = trim((string) ($snapshot['full_address'] ?? ''));

        if ($fullAddress === '') {
            $fullAddress = collect([$barangay, $municipality, $province])->filter()->implode(', ');
        }

        return [
            'name'         => $name,
            'phone_number' => $phone,
            'barangay'     => $barangay,
            'municipality' => $municipality,
            'province'     => $province,
            'full_address' => $fullAddress,
        ];
    }

    protected function returnItems(ReturnShipment $returnShipment): array
    {
        $items = $returnShipment->sellerOrder?->items ?? collect();

        return collect($items)->map(function ($item) {
            $variant = implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name]));

            return [
                'product_name'  => (string) $item->product_name,
                'variant_label' => $variant !== '' ? $variant : null,
                'quantity'      => (int) $item->quantity,
            ];
        })->values()->all();
    }

    protected function returnFailure(string $message, int $status = 422, array $errors = []): JsonResponse
    {
        $body = ['status' => 'error', 'message' => $message];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    protected function returnNotAssigned(): JsonResponse
    {
        return $this->returnFailure('This return is not assigned to you.', 403);
    }

    protected function returnFailureFrom(ValidationException $exception): JsonResponse
    {
        $errors = $exception->errors();
        $message = (string) (collect($errors)->flatten()->first() ?? 'This return cannot be updated right now.');

        return $this->returnFailure($message, 422, $errors);
    }
}