<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItemReceipt extends Model
{
    protected $fillable = [
        'purchase_order_item_id',
        'is_received',
        'received_item_name',
        'received_quantity',
        'received_unit',
        'received_price',
        'alternative_item_name',
        'alternative_quantity',
        'alternative_unit',
        'alternative_price',
        'alternative_reason',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_received' => 'boolean',
            'received_quantity' => 'decimal:2',
            'received_price' => 'decimal:2',
            'alternative_quantity' => 'decimal:2',
            'alternative_price' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
