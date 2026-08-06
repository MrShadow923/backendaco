<?php

namespace Database\Factories;

use App\Models\StockReleaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockReleaseItem>
 */
class StockReleaseItemFactory extends Factory
{
    protected $model = StockReleaseItem::class;

    public function definition(): array
    {
        return [
            'stock_release_id' => \App\Models\StockRelease::factory(),
            'inventory_item_id' => \App\Models\InventoryItem::factory(),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'unit_cost' => fake()->randomFloat(2, 10, 10000),
            'total_amount' => fake()->randomFloat(2, 10, 100000),
        ];
    }
}
