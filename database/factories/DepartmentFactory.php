<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = \App\Models\Department::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DEPT-???')),
            'name' => fake()->company() . ' Department',
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}