<?php

namespace Tests\Feature;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_data_endpoint_returns_items_for_purchaser()
    {
        if (! class_exists(\App\Http\Controllers\Api\PurchaseRequestController::class)) {
            $this->markTestSkipped('Api PurchaseRequestController not present');
        }

        // Create a Purchaser user
        $user = User::factory()->create(['role' => 'Purchaser']);

        // Create a submitted purchase request with items
        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $user->id,
            'status' => PurchaseRequestStatus::Submitted,
        ]);

        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $pr->id,
            'quantity' => 2,
            'estimated_price' => 100,
        ]);

        // Act as purchaser and request conversion data
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/purchase-requests/{$pr->id}/conversion-data");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['id', 'items']]);
        $this->assertNotEmpty($response->json('data.items'));
    }
}
