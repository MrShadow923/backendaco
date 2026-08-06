<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'item_name',
        'display_name',
        'quantity',
        'unit',
        'latest_unit_price',
        'average_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'latest_unit_price' => 'decimal:2',
            'average_unit_cost' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public static function normalizeItemName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower($name)));
    }

    public static function findByName(string $name): ?self
    {
        return static::where('item_name', self::normalizeItemName($name))->first();
    }
}
