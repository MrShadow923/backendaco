<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'created_by' => User::factory(),
            'supplier_name' => fake()->company(),
            'order_date' => now()->toDateString(),
            'total_amount' => fake()->randomFloat(2, 100, 100000),
            'status' => PurchaseOrderStatus::Draft,
            'remarks' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::Draft]);
    }

    public function pendingFinanceSignature(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::PendingFinanceSignature]);
    }

    public function pendingGmSignature(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::PendingGmSignature]);
    }

    public function approved(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::Rejected]);
    }
}
