<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\CommissionRecord;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\SellerSettlement;
use App\Models\SellerSettlementItem;
use App\Notifications\OrderLifecycleNotification;
use App\Services\CommissionCalculatorService;
use App\Services\OrderCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceController extends Controller
{
    public function __construct(
        protected CommissionCalculatorService $commissionCalculatorService,
        protected OrderCompletionService $orderCompletionService
    ) {}

    public function index(Request $request)
    {
        $profile = $request->user()->logisticsProfile;
        if (! $profile) {
            abort(403);
        }

        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(logisticsProfileId: (int) $profile->id);

        $tab = $request->query('tab', 'remittances');

        $remittances = CodRemittance::with(['rider', 'items.sellerOrder', 'verifier'])
            ->where('logistics_profile_id', $profile->id)
            ->orderBy('submitted_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10, ['*'], 'remittances_page');

        $codCollections = CodCollection::with(['rider', 'sellerOrder.sellerProfile', 'shipment'])
            ->where('logistics_profile_id', $profile->id)
            ->orderBy('collected_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10, ['*'], 'collections_page');

        $unsettledCommissions = CommissionRecord::with(['sellerOrder', 'sellerProfile.user'])
            ->where('logistics_profile_id', $profile->id)
            ->where('status', 'recorded')
            ->orderBy('calculated_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $pendingSellerPayouts = $unsettledCommissions->groupBy('seller_profile_id')->map(function ($records) {
            /** @var SellerProfile $sellerProfile */
            $sellerProfile = $records->first()->sellerProfile;

            return [
                'seller_profile'         => $sellerProfile,
                'orders_count'           => $records->count(),
                'gross_product_subtotal' => round((float) $records->sum('product_subtotal'), 2),
                'admin_commission_total' => round((float) $records->sum('admin_commission_amount'), 2),
                'net_settlement_amount'  => round((float) $records->sum('seller_net_earning'), 2),
            ];
        })->values();

        $settlements = SellerSettlement::with(['sellerProfile', 'items.sellerOrder', 'processor'])
            ->where('logistics_profile_id', $profile->id)
            ->orderBy('settled_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10, ['*'], 'settlements_page');

        $metrics = [
            'total_cod_collected'         => (float) CodCollection::where('logistics_profile_id', $profile->id)->sum('collected_amount'),
            'pending_remittance_verify'   => (float) CodRemittance::where('logistics_profile_id', $profile->id)->where('status', 'submitted')->sum('total_collected_amount'),
            'logistics_10_commission'     => (float) CommissionRecord::where('logistics_profile_id', $profile->id)->sum('logistics_commission_amount'),
            'logistics_80_operational'    => (float) CommissionRecord::where('logistics_profile_id', $profile->id)->sum('logistics_operational_revenue'),
            'logistics_total_90_shipping' => (float) CommissionRecord::where('logistics_profile_id', $profile->id)->sum('logistics_total_earning'),
            'rider_10_commission'         => (float) CommissionRecord::where('logistics_profile_id', $profile->id)->sum('rider_commission_amount'),
            'pending_seller_settlement'   => (float) CommissionRecord::where('logistics_profile_id', $profile->id)->where('status', 'recorded')->sum('seller_net_earning'),
            'released_seller_settlement'  => (float) SellerSettlement::where('logistics_profile_id', $profile->id)->sum('net_settlement_amount'),
        ];

        return view('logistics.finance.index', compact(
            'profile',
            'tab',
            'remittances',
            'codCollections',
            'pendingSellerPayouts',
            'settlements',
            'metrics'
        ));
    }

    public function verifyRemittance(Request $request, CodRemittance $remittance)
    {
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $remittance->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        DB::transaction(function () use ($remittance, $profile, $user) {
            $lockedRemittance = CodRemittance::with(['items.collection.sellerOrder.payment', 'items.collection.sellerOrder.order'])
                ->where('id', $remittance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRemittance->status === 'verified') {
                throw ValidationException::withMessages([
                    'remittance' => 'This COD remittance has already been verified.',
                ]);
            }

            $lockedRemittance->update([
                'status'      => 'verified',
                'verified_at' => now(),
                'verified_by' => $user->id,
            ]);

            foreach ($lockedRemittance->items as $item) {
                $collection = $item->collection;
                if (! $collection) {
                    continue;
                }

                $collection->update([
                    'status'      => 'verified',
                    'verified_at' => now(),
                    'verified_by' => $user->id,
                ]);

                $sellerOrder = $collection->sellerOrder;
                if (! $sellerOrder) {
                    continue;
                }

                $sellerOrder->update([
                    'payment_status' => 'remitted',
                ]);

                $sellerOrder->payment?->update([
                    'status'      => 'remitted',
                    'remitted_at' => now(),
                ]);

                if (! CommissionRecord::where('seller_order_id', $sellerOrder->id)->exists()) {
                    $split = $this->commissionCalculatorService->calculateSplitBase(
                        productSubtotal: (float) $sellerOrder->subtotal_amount,
                        shippingFee: (float) $sellerOrder->shipping_fee
                    );

                    CommissionRecord::create([
                        'seller_order_id'               => $sellerOrder->id,
                        'shipment_id'                   => $collection->shipment_id,
                        'cod_collection_id'             => $collection->id,
                        'seller_profile_id'             => $sellerOrder->seller_profile_id,
                        'logistics_profile_id'          => $profile->id,
                        'rider_id'                      => $collection->rider_id,
                        'product_subtotal'              => $split['product_subtotal'],
                        'shipping_fee'                  => $split['shipping_fee'],
                        'total_cod_amount'              => $split['total_cod_amount'],
                        'admin_commission_rate'         => $split['admin_commission_rate'],
                        'admin_commission_amount'       => $split['admin_commission_amount'],
                        'seller_net_earning'            => $split['seller_net_earning'],
                        'logistics_commission_rate'     => $split['logistics_commission_rate'],
                        'logistics_commission_amount'   => $split['logistics_commission_amount'],
                        'rider_commission_rate'         => $split['rider_commission_rate'],
                        'rider_commission_amount'       => $split['rider_commission_amount'],
                        'logistics_operational_revenue' => $split['logistics_operational_revenue'],
                        'logistics_total_earning'       => $split['logistics_total_earning'],
                        'status'                        => 'recorded',
                        'calculated_at'                 => now(),
                    ]);
                }

                if ($sellerOrder->order) {
                    $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);
                }
            }
        });

        return back()->with('success', "Remittance #{$remittance->remittance_reference} verified and Split-Base commissions recorded.");
    }

    public function settleSeller(Request $request)
    {
        $user = $request->user();
        $profile = $user->logisticsProfile;
        if (! $profile) {
            abort(403);
        }

        $validated = $request->validate([
            'seller_profile_id' => ['required', 'integer', 'exists:seller_profiles,id'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        /** @var SellerProfile $sellerProfile */
        $sellerProfile = SellerProfile::with('user')->findOrFail($validated['seller_profile_id']);

        $settlement = DB::transaction(function () use ($profile, $sellerProfile, $user, $validated) {
            $records = CommissionRecord::with(['sellerOrder.payment', 'sellerOrder.order'])
                ->where('logistics_profile_id', $profile->id)
                ->where('seller_profile_id', $sellerProfile->id)
                ->where('status', 'recorded')
                ->orderBy('calculated_at', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            if ($records->isEmpty()) {
                throw ValidationException::withMessages([
                    'settlement' => 'There are no pending verified COD commissions to settle for this seller.',
                ]);
            }

            $grossSubtotal = round((float) $records->sum('product_subtotal'), 2);
            $adminTotal = round((float) $records->sum('admin_commission_amount'), 2);
            $netPayout = round((float) $records->sum('seller_net_earning'), 2);

            $newSettlement = SellerSettlement::create([
                'settlement_reference'   => 'STL-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'logistics_profile_id'   => $profile->id,
                'seller_profile_id'      => $sellerProfile->id,
                'seller_id'              => $sellerProfile->user_id,
                'gross_product_subtotal' => $grossSubtotal,
                'admin_commission_total' => $adminTotal,
                'net_settlement_amount'  => $netPayout,
                'status'                 => 'released',
                'processed_by'           => $user->id,
                'settled_at'             => now(),
                'notes'                  => $validated['notes'] ?? null,
            ]);

            foreach ($records as $rec) {
                SellerSettlementItem::create([
                    'seller_settlement_id'    => $newSettlement->id,
                    'seller_order_id'         => $rec->seller_order_id,
                    'commission_record_id'    => $rec->id,
                    'product_subtotal'        => $rec->product_subtotal,
                    'admin_commission_amount' => $rec->admin_commission_amount,
                    'seller_net_earning'      => $rec->seller_net_earning,
                ]);

                $rec->update([
                    'status'     => 'settled',
                    'settled_at' => now(),
                ]);

                /** @var SellerOrder|null $so */
                $so = $rec->sellerOrder;
                if ($so) {
                    $so->update([
                        'payment_status' => 'settled',
                    ]);
                    $so->payment?->update([
                        'status'     => 'settled',
                        'settled_at' => now(),
                    ]);
                    if ($so->order) {
                        $this->orderCompletionService->syncParentOrderStatus($so->order);
                    }
                }
            }

            return $newSettlement;
        });

        try {
            $sellerProfile->user?->notify(new OrderLifecycleNotification(
                title: 'Seller Settlement Released',
                message: "{$profile->business_name} released settlement #{$settlement->settlement_reference} of ₱" . number_format((float) $settlement->net_settlement_amount, 2) . " (90% of Product Subtotal).",
                type: 'info',
                url: route('seller.settlements.index')
            ));
        } catch (\Throwable $e) {
        }

        return back()->with('success', "Seller settlement #{$settlement->settlement_reference} (₱" . number_format((float) $settlement->net_settlement_amount, 2) . ") released to {$sellerProfile->business_name}.");
    }
}