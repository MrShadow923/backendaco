<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 0, 1000);
        $price = fake()->randomFloat(2, 10, 10000);

        return [
            'item_name' => strtolower(fake()->words(3, true)),
            'display_name' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit' => fake()->randomElement(['pcs', 'box', 'kg', 'liter', 'set']),
            'latest_unit_price' => $price,
            'average_unit_cost' => $price,
        ];
    }
}

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    protected $model = InventoryTransaction::class;

    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'transaction_type' => 'po_receipt',
            'reference_type' => 'PurchaseOrder',
            'reference_id' => PurchaseOrder::factory(),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'unit_price' => fake()->randomFloat(2, 10, 10000),
            'unit_cost' => fake()->randomFloat(2, 10, 10000),
            'running_quantity' => fake()->randomFloat(2, 0, 1000),
            'running_avg_cost' => fake()->randomFloat(2, 10, 10000),
            'remarks' => fake()->sentence(),
            'created_by' => \App\Models\User::factory(),
        ];
    }

    public function poReceipt(): static
    {
        return $this->state([
            'transaction_type' => 'po_receipt',
        ]);
    }
}
