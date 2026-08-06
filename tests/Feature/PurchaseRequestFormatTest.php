<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestFormatTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_aco_can_create_a_purchase_request_format(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $response = $this->actingAs($aco)
            ->postJson('/api/v1/purchase-request-formats', [
                'name' => 'Office Supplies Template',
                'format_data' => [
                    'purpose' => 'Purchase of office supplies',
                    'remarks' => 'Submitted for purchaser',
                    'submitted_for' => 'purchaser',
                    'items' => [
                        [
                            'item_name' => 'A4 Paper',
                            'description' => '80gsm white paper',
                            'quantity' => 50,
                            'unit' => 'box',
                            'estimated_price' => 25.00,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $format = \App\Models\PurchaseRequestFormat::first();
        $this->assertNotNull($format);
        $this->assertEquals('Office Supplies Template', $format->name);
        $this->assertEquals('draft', $format->status);
        $this->assertEquals($aco->id, $format->created_by);
    }

    public function test_aco_can_view_their_purchase_request_formats(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $format = \App\Models\PurchaseRequestFormat::create([
            'name' => 'Test Format',
            'format_data' => [
                'purpose' => 'Test purpose',
                'items' => [
                    [
                        'item_name' => 'Item 1',
                        'quantity' => 1,
                        'unit' => 'pcs',
                        'estimated_price' => 10,
                    ],
                ],
            ],
            'status' => 'draft',
            'created_by' => $aco->id,
        ]);

        $response = $this->actingAs($aco)
            ->getJson("/api/v1/purchase-request-formats/{$format->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Test Format');
    }

    public function test_aco_can_update_their_purchase_request_format(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $format = \App\Models\PurchaseRequestFormat::create([
            'name' => 'Old Name',
            'format_data' => [
                'purpose' => 'Old purpose',
                'items' => [
                    [
                        'item_name' => 'Item 1',
                        'quantity' => 1,
                        'unit' => 'pcs',
                        'estimated_price' => 10,
                    ],
                ],
            ],
            'status' => 'draft',
            'created_by' => $aco->id,
        ]);

        $response = $this->actingAs($aco)
            ->patchJson("/api/v1/purchase-request-formats/{$format->id}", [
                'name' => 'New Name',
                'format_data' => [
                    'purpose' => 'New purpose',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('purchase_request_formats', [
            'id' => $format->id,
            'name' => 'New Name',
        ]);
    }

    public function test_aco_can_delete_their_purchase_request_format(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $format = \App\Models\PurchaseRequestFormat::create([
            'name' => 'To Delete',
            'format_data' => ['purpose' => 'Test', 'items' => []],
            'status' => 'draft',
            'created_by' => $aco->id,
        ]);

        $response = $this->actingAs($aco)
            ->deleteJson("/api/v1/purchase-request-formats/{$format->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('purchase_request_formats', ['id' => $format->id]);
    }

    public function test_aco_can_create_purchase_request_from_format(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $format = \App\Models\PurchaseRequestFormat::create([
            'name' => 'PR Template',
            'format_data' => [
                'purpose' => 'Purchase of office supplies',
                'remarks' => 'Submitted for purchaser',
                'submitted_for' => 'purchaser',
                'items' => [
                    [
                        'item_name' => 'A4 Paper',
                        'description' => '80gsm white paper',
                        'quantity' => 50,
                        'unit' => 'box',
                        'estimated_price' => 25.00,
                    ],
                ],
            ],
            'status' => 'draft',
            'created_by' => $aco->id,
        ]);

        $response = $this->actingAs($aco)
            ->postJson("/api/v1/purchase-request-formats/{$format->id}/purchase-request");

        $response->assertStatus(201)
            ->assertJsonPath('data.purpose', 'Purchase of office supplies')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('purchase_requests', [
            'purpose' => 'Purchase of office supplies',
            'requested_by' => $aco->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('purchase_request_items', [
            'item_name' => 'A4 Paper',
            'quantity' => 50,
            'unit' => 'box',
        ]);
    }

    public function test_purchaser_cannot_create_purchase_request_from_format(): void
    {
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);

        $format = \App\Models\PurchaseRequestFormat::create([
            'name' => 'PR Template',
            'format_data' => [
                'purpose' => 'Test',
                'items' => [
                    [
                        'item_name' => 'Item 1',
                        'quantity' => 1,
                        'unit' => 'pcs',
                        'estimated_price' => 10,
                    ],
                ],
            ],
            'status' => 'draft',
            'created_by' => $purchaser->id,
        ]);

        $response = $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-request-formats/{$format->id}/purchase-request");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_formats(): void
    {
        $format = \App\Models\PurchaseRequestFormat::create([
            'name' => 'Test',
            'format_data' => ['purpose' => 'Test', 'items' => []],
            'status' => 'draft',
        ]);

        $response = $this->getJson("/api/v1/purchase-request-formats/{$format->id}");
        $response->assertStatus(401);
    }
}
