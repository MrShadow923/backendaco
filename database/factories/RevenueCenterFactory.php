<?php

namespace Database\Factories;

use App\Models\RevenueCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevenueCenter>
 */
class RevenueCenterFactory extends Factory
{
    protected $model = RevenueCenter::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('RC-???')),
            'name' => fake()->company() . ' Revenue Center',
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
