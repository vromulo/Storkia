<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ReturnRequest extends Model
{
    public const RESOLUTION_RETURN_REFUND = 'return_refund';
    public const RESOLUTION_REFUND_ONLY   = 'refund_only';

    public const STATUS_PENDING_SELLER_RESPONSE = 'pending_seller_response';
    public const STATUS_REJECTED_BY_SELLER      = 'rejected_by_seller';
    public const STATUS_ESCALATED               = 'escalated';
    public const STATUS_RETURN_IN_TRANSIT       = 'return_in_transit';
    public const STATUS_AWAITING_SELLER_CONFIRM = 'awaiting_seller_confirmation';
    public const STATUS_RECEIPT_DISPUTED        = 'receipt_disputed';
    public const STATUS_REFUND_PENDING          = 'refund_pending';
    public const STATUS_REFUNDED                = 'refunded';
    public const STATUS_REJECTED                = 'rejected';
    public const STATUS_CLOSED                  = 'closed';

    public const TERMINAL_STATUSES = [
        self::STATUS_REFUNDED,
        self::STATUS_REJECTED,
        self::STATUS_CLOSED,
    ];

    public const DECISION_APPROVED_RETURN      = 'approved_return';
    public const DECISION_APPROVED_REFUND_ONLY = 'approved_refund_only';
    public const DECISION_REJECTED             = 'rejected';

    public const REFUND_NOT_DUE       = 'not_due';
    public const REFUND_PENDING_ADMIN = 'pending_admin_refund';
    public const REFUND_REFUNDED      = 'refunded';

    public const RECEIPT_OK              = 'received_ok';
    public const RECEIPT_NOT_AS_EXPECTED = 'received_not_as_expected';

    public const SELLER_RESPONSE_HOURS = 72;
    public const HANDOVER_HOURS        = 72;
    public const SELLER_CONFIRM_HOURS  = 72;
    public const REFUND_PAYOUT_HOURS   = 72;

    protected $fillable = [
        'request_number',
        'seller_order_id',
        'order_id',
        'buyer_id',
        'seller_profile_id',
        'logistics_profile_id',
        'requested_resolution',
        'approved_resolution',
        'status',
        'reason_category',
        'reason',
        'evidence_photos',
        'refund_amount',
        'refund_payout_method',
        'refund_account_name',
        'refund_account_number',
        'refund_status',
        'refund_reference',
        'refund_remarks',
        'refund_due_at',
        'refunded_at',
        'refunded_by_admin_id',
        'seller_decision',
        'seller_decision_reason',
        'seller_response_deadline_at',
        'seller_responded_at',
        'auto_approved',
        'is_escalated',
        'escalated_at',
        'escalation_note',
        'admin_decision',
        'admin_remarks',
        'admin_resolved_by',
        'admin_resolved_at',
        'handover_deadline_at',
        'seller_receipt_outcome',
        'seller_receipt_notes',
        'seller_receipt_photos',
        'seller_receipt_deadline_at',
        'seller_received_at',
        'ledger_settled_at',
        'seller_deduction_amount',
        'settlement_snapshot',
        'closed_at',
        'closed_reason',
    ];

    protected function casts(): array
    {
        return [
            'evidence_photos'             => 'array',
            'seller_receipt_photos'       => 'array',
            'settlement_snapshot'         => 'array',
            'refund_amount'               => 'decimal:2',
            'seller_deduction_amount'     => 'decimal:2',
            'auto_approved'               => 'boolean',
            'is_escalated'                => 'boolean',
            'refund_due_at'               => 'datetime',
            'refunded_at'                 => 'datetime',
            'seller_response_deadline_at' => 'datetime',
            'seller_responded_at'         => 'datetime',
            'escalated_at'                => 'datetime',
            'admin_resolved_at'           => 'datetime',
            'handover_deadline_at'        => 'datetime',
            'seller_receipt_deadline_at'  => 'datetime',
            'seller_received_at'          => 'datetime',
            'ledger_settled_at'           => 'datetime',
            'closed_at'                   => 'datetime',
        ];
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function returnShipment(): HasOne
    {
        return $this->hasOne(ReturnShipment::class, 'return_request_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    public function scopeAwaitingRefundPayout(Builder $query): Builder
    {
        return $query->where('refund_status', self::REFUND_PENDING_ADMIN);
    }

    public static function reasonCatalog(): array
    {
        return [
            'Item not received' => [
                'resolution' => self::RESOLUTION_REFUND_ONLY,
                'hint'       => 'The parcel is marked delivered but you never got it.',
            ],
            'Empty package or missing items' => [
                'resolution' => self::RESOLUTION_REFUND_ONLY,
                'hint'       => 'The box was empty or part of your order was not inside.',
            ],
            'Damaged item' => [
                'resolution' => self::RESOLUTION_RETURN_REFUND,
                'hint'       => 'The item arrived broken, dented or crushed.',
            ],
            'Wrong item received' => [
                'resolution' => self::RESOLUTION_RETURN_REFUND,
                'hint'       => 'You received a different item, size, color or variant.',
            ],
            'Item not as described' => [
                'resolution' => self::RESOLUTION_RETURN_REFUND,
                'hint'       => 'The item does not match the listing photos or description.',
            ],
            'Defective or not working' => [
                'resolution' => self::RESOLUTION_RETURN_REFUND,
                'hint'       => 'The item does not work properly.',
            ],
            'Other' => [
                'resolution' => self::RESOLUTION_RETURN_REFUND,
                'hint'       => 'Another problem with your order.',
            ],
        ];
    }

    public static function suggestedResolutionFor(?string $reasonCategory): string
    {
        return self::reasonCatalog()[$reasonCategory]['resolution'] ?? self::RESOLUTION_RETURN_REFUND;
    }

    public static function generateRequestNumber(): string
    {
        do {
            $number = 'RR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (self::where('request_number', $number)->exists());

        return $number;
    }

    public function effectiveResolution(): string
    {
        return $this->approved_resolution ?: $this->requested_resolution;
    }

    public function isReturnRefund(): bool
    {
        return $this->effectiveResolution() === self::RESOLUTION_RETURN_REFUND;
    }

    public function isRefundOnly(): bool
    {
        return $this->effectiveResolution() === self::RESOLUTION_REFUND_ONLY;
    }

    public function isWalletRefund(): bool
    {
        return strtoupper((string) ($this->refund_payout_method ?: 'WALLET')) === 'WALLET';
    }

    public function isGcashRefund(): bool
    {
        return strtoupper((string) ($this->refund_payout_method ?: 'WALLET')) === 'GCASH';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    public function isAwaitingSellerResponse(): bool
    {
        return $this->status === self::STATUS_PENDING_SELLER_RESPONSE;
    }

    public function canBuyerEscalate(): bool
    {
        return $this->status === self::STATUS_REJECTED_BY_SELLER && ! $this->is_escalated;
    }

    public function isRefundPaid(): bool
    {
        return $this->refund_status === self::REFUND_REFUNDED;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_SELLER_RESPONSE => 'Waiting for Seller Response',
            self::STATUS_REJECTED_BY_SELLER      => 'Rejected by Seller',
            self::STATUS_ESCALATED               => 'Escalated to Storkia Admin',
            self::STATUS_RETURN_IN_TRANSIT       => 'Return In Progress',
            self::STATUS_AWAITING_SELLER_CONFIRM => 'Waiting for Seller to Confirm Receipt',
            self::STATUS_RECEIPT_DISPUTED        => 'Returned Item Under Admin Review',
            self::STATUS_REFUND_PENDING          => 'Refund Being Processed',
            self::STATUS_REFUNDED                => 'Refunded',
            self::STATUS_REJECTED                => 'Request Rejected',
            self::STATUS_CLOSED                  => 'Request Closed',
            default                              => ucwords(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getResolutionLabelAttribute(): string
    {
        return $this->isRefundOnly()
            ? 'Refund Only (Keep the Item)'
            : 'Return & Refund (Send the Item Back)';
    }
}