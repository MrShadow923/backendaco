<?php

namespace Database\Factories;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'request_date' => now()->toDateString(),
            'purpose' => fake()->sentence(),
            'status' => PurchaseRequestStatus::Draft,
            'remarks' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::Draft]);
    }

    public function submitted(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::Submitted]);
    }

    public function convertedToPo(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::ConvertedToPO]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::Cancelled]);
    }
}
