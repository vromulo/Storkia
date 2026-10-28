<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodRemittanceItem extends Model
{
    protected $fillable = [
        'cod_remittance_id',
        'cod_collection_id',
        'seller_order_id',
        'collected_amount',
        'rider_commission_amount',
    ];

    protected function casts(): array
    {
        return [
            'collected_amount'        => 'decimal:2',
            'rider_commission_amount' => 'decimal:2',
        ];
    }

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(CodRemittance::class, 'cod_remittance_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(CodCollection::class, 'cod_collection_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }
}