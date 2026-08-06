<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReleaseItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'stock_release_id',
        'inventory_item_id',
        'quantity',
        'unit_cost',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function stockRelease(): BelongsTo
    {
        return $this->belongsTo(StockRelease::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
