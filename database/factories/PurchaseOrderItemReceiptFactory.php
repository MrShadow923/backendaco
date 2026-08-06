<?php

namespace Database\Factories;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItemReceipt>
 */
class PurchaseOrderItemReceiptFactory extends Factory
{
    protected $model = PurchaseOrderItemReceipt::class;

    public function definition(): array
    {
        return [
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'is_received' => true,
            'received_item_name' => fake()->words(3, true),
            'received_quantity' => fake()->randomFloat(2, 1, 100),
            'received_unit' => fake()->randomElement(['pcs', 'box', 'kg', 'liter', 'set']),
            'received_price' => fake()->randomFloat(2, 10, 10000),
            'alternative_item_name' => null,
            'alternative_quantity' => null,
            'alternative_unit' => null,
            'alternative_price' => null,
            'alternative_reason' => null,
            'verified_by' => User::factory(),
            'verified_at' => now(),
        ];
    }

    public function notReceived(): static
    {
        return $this->state([
            'is_received' => false,
            'received_item_name' => null,
            'received_quantity' => null,
            'received_unit' => null,
            'received_price' => null,
            'alternative_item_name' => fake()->words(3, true),
            'alternative_quantity' => fake()->randomFloat(2, 1, 100),
            'alternative_unit' => fake()->randomElement(['pcs', 'box', 'kg', 'liter', 'set']),
            'alternative_price' => fake()->randomFloat(2, 10, 10000),
            'alternative_reason' => fake()->sentence(),
        ]);
    }
}
