<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A buyer's report against a seller (store) account.
 *
 * Lifecycle:
 *   pending -> under_review -> resolved   (admin took action: warn / 7-day suspension / permanent ban)
 *                           -> dismissed  (admin found no violation)
 *
 * A buyer may only have ONE open (pending / under_review) report per seller at a time. That rule is
 * enforced by the unique `open_lock` column, which this model keeps in sync automatically whenever
 * a report is saved. Always change a report through the model (->update(), ->save()) so the lock is
 * refreshed. A raw query-builder update() would skip it.
 */
class SellerReport extends Model
{
    // Report lifecycle
    public const STATUS_PENDING      = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESOLVED     = 'resolved';
    public const STATUS_DISMISSED    = 'dismissed';

    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
    ];

    public const CLOSED_STATUSES = [
        self::STATUS_RESOLVED,
        self::STATUS_DISMISSED,
    ];

    // Actions an admin can take when resolving (the values match AccountController::toggleStatus)
    public const ACTION_WARN         = 'warn';
    public const ACTION_SUSPEND_TEMP = 'suspend_temp';
    public const ACTION_SUSPEND_PERM = 'suspend_perm';

    public const ACTIONS = [
        self::ACTION_WARN,
        self::ACTION_SUSPEND_TEMP,
        self::ACTION_SUSPEND_PERM,
    ];

    // Form limits
    public const MIN_DESCRIPTION_LENGTH = 20;
    public const MAX_DESCRIPTION_LENGTH = 1500;
    public const MAX_EVIDENCE_PHOTOS    = 5;
    public const MAX_EVIDENCE_KB        = 5120;

    protected $fillable = [
        'report_number',
        'reporter_id',
        'seller_profile_id',
        'seller_order_id',
        'reason_category',
        'description',
        'evidence_photos',
        'status',
        'admin_action',
        'admin_note',
        'reviewed_by_admin_id',
        'reviewed_at',
        'resolved_by_admin_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_photos' => 'array',
            'reviewed_at'     => 'datetime',
            'resolved_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the "one open report per buyer per seller" lock in step with the status.
        static::saving(function (SellerReport $report) {
            $report->open_lock = in_array($report->status, self::OPEN_STATUSES, true)
                ? $report->reporter_id . ':' . $report->seller_profile_id
                : null;
        });
    }

    // ------------------------------------------------------------------ relations

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    // ------------------------------------------------------------------ scopes

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', self::CLOSED_STATUSES);
    }

    // ------------------------------------------------------------------ reasons

    /**
     * Reasons a buyer can pick when reporting a seller.
     * The array key is what gets stored in `reason_category`.
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public static function categoryCatalog(): array
    {
        return [
            'counterfeit' => [
                'label' => 'Counterfeit or fake products',
                'hint'  => 'The seller is passing off imitation goods as genuine.',
            ],
            'not_as_described' => [
                'label' => 'Item not as described',
                'hint'  => 'What the seller listed does not match what was received.',
            ],
            'scam_fraud' => [
                'label' => 'Scam or fraud',
                'hint'  => 'The seller asks you to pay or chat outside Storkia, or took payment and never delivered.',
            ],
            'harassment' => [
                'label' => 'Harassment or abusive messages',
                'hint'  => 'The seller sent threatening, abusive or inappropriate messages.',
            ],
            'prohibited_items' => [
                'label' => 'Prohibited items',
                'hint'  => 'The seller is listing items that are illegal or not allowed on Storkia.',
            ],
            'misleading_listing' => [
                'label' => 'Misleading pricing or listings',
                'hint'  => 'Fake discounts, bait-and-switch prices or deceptive product details.',
            ],
            'other' => [
                'label' => 'Other',
                'hint'  => 'Something else that is not listed above.',
            ],
        ];
    }

    /** @return array<int, string> */
    public static function categoryKeys(): array
    {
        return array_keys(self::categoryCatalog());
    }

    // ------------------------------------------------------------------ helpers

    public static function generateReportNumber(): string
    {
        do {
            $number = 'SR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (self::where('report_number', $number)->exists());

        return $number;
    }

    /**
     * Does this buyer already have an open report against this seller?
     */
    public static function hasOpenReport(int $reporterId, int $sellerProfileId): bool
    {
        return self::open()
            ->where('reporter_id', $reporterId)
            ->where('seller_profile_id', $sellerProfileId)
            ->exists();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    // ------------------------------------------------------------------ accessors

    public function getCategoryLabelAttribute(): string
    {
        return self::categoryCatalog()[$this->reason_category]['label']
            ?? Str::headline((string) $this->reason_category);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING      => 'Pending',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_RESOLVED     => 'Resolved',
            self::STATUS_DISMISSED    => 'Dismissed',
            default                   => Str::headline((string) $this->status),
        };
    }

    public function getAdminActionLabelAttribute(): ?string
    {
        return match ($this->admin_action) {
            self::ACTION_WARN         => 'Seller warned',
            self::ACTION_SUSPEND_TEMP => 'Seller suspended for 7 days',
            self::ACTION_SUSPEND_PERM => 'Seller permanently banned',
            default                   => null,
        };
    }

    /**
     * Public URLs of the evidence photos (same storage convention as return evidence).
     *
     * @return array<int, string>
     */
    public function getEvidenceUrlsAttribute(): array
    {
        $photos = is_array($this->evidence_photos) ? $this->evidence_photos : [];

        return array_values(array_map(
            fn ($path) => asset('storage/' . ltrim((string) $path, '/')),
            array_filter($photos)
        ));
    }
}