<?php

namespace Database\Factories;

use App\Models\StockRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockRelease>
 */
class StockReleaseFactory extends Factory
{
    protected $model = StockRelease::class;

    public function definition(): array
    {
        return [
            'reference_number' => 'SR-' . date('Y') . '-' . fake()->unique()->numberBetween(1, 9999),
            'department_id' => \App\Models\Department::factory(),
            'revenue_center_id' => \App\Models\RevenueCenter::factory(),
            'status' => 'draft',
            'released_at' => null,
            'released_by' => null,
            'notes' => fake()->sentence(),
            'total_quantity' => 0,
            'total_amount' => 0,
        ];
    }

    public function released(): static
    {
        return $this->state([
            'status' => 'released',
            'released_at' => now(),
            'released_by' => \App\Models\User::factory(),
        ]);
    }
}
