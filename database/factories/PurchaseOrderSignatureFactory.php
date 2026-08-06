<?php

namespace Database\Factories;

use App\Enums\SignatureAction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderSignature>
 */
class PurchaseOrderSignatureFactory extends Factory
{
    protected $model = PurchaseOrderSignature::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'user_id' => User::factory(),
            'role' => 'finance_officer',
            'action' => SignatureAction::Signed,
            'remarks' => fake()->optional()->sentence(),
            'signed_at' => now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function signed(): static
    {
        return $this->state(['action' => SignatureAction::Signed]);
    }

    public function rejected(): static
    {
        return $this->state(['action' => SignatureAction::Rejected]);
    }
}
