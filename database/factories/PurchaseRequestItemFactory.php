<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestItem>
 */
class PurchaseRequestItemFactory extends Factory
{
    protected $model = PurchaseRequestItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $price = fake()->randomFloat(2, 10, 10000);

        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'item_name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit' => fake()->randomElement(['pcs', 'box', 'kg', 'liter', 'set']),
            'estimated_price' => $price,
            'total_amount' => $quantity * $price,
        ];
    }
}
