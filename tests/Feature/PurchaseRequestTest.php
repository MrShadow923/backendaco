<?php

namespace Tests\Feature;

use App\Enums\PurchaseRequestStatus;
use App\Enums\UserRole;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Database\Factories\PurchaseRequestFactory;
use Database\Factories\PurchaseRequestItemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ───────────────────────────────────────────────

    private function createUserWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function validPayload(): array
    {
        return [
            'purpose' => 'Office supplies for Q3',
            'request_date' => '2026-08-01',
            'remarks' => 'Urgent request',
            'items' => [
                [
                    'item_name' => 'A4 Paper',
                    'description' => '80gsm white paper',
                    'quantity' => 50,
                    'unit' => 'box',
                    'estimated_price' => 25.00,
                ],
                [
                    'item_name' => 'Ballpoint Pen',
                    'description' => 'Blue ink',
                    'quantity' => 100,
                    'unit' => 'pcs',
                    'estimated_price' => 2.50,
                ],
            ],
        ];
    }

    // ─── Tests ─────────────────────────────────────────────────

    public function test_aco_can_create_a_purchase_request_with_items(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $response = $this->actingAs($aco)
            ->postJson('/api/v1/purchase-requests', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.purpose', 'Office supplies for Q3')
            ->assertJsonPath('data.status', 'draft');

        // Assert the PR was created for the right user
        $this->assertDatabaseHas('purchase_requests', [
            'requested_by' => $aco->id,
            'purpose' => 'Office supplies for Q3',
            'status' => 'draft',
        ]);

        // Assert items were created
        $this->assertDatabaseCount('purchase_request_items', 2);

        // Assert the request number was auto-generated
        $pr = PurchaseRequest::first();
        $this->assertNotNull($pr->request_number);
        $this->assertStringStartsWith('PR-', $pr->request_number);
    }

    public function test_aco_can_update_their_own_draft_request(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $aco->id,
            'status' => PurchaseRequestStatus::Draft,
        ]);
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $pr->id,
        ]);

        $response = $this->actingAs($aco)
            ->patchJson("/api/v1/purchase-requests/{$pr->id}", [
                'purpose' => 'Updated purpose',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.purpose', 'Updated purpose');

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'purpose' => 'Updated purpose',
        ]);
    }

    public function test_aco_cannot_update_submitted_request(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $aco->id,
            'status' => PurchaseRequestStatus::Submitted,
        ]);

        $response = $this->actingAs($aco)
            ->patchJson("/api/v1/purchase-requests/{$pr->id}", [
                'purpose' => 'Should not update',
            ]);

        $response->assertStatus(403);
    }

    public function test_aco_can_submit_a_request_with_items(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $aco->id,
            'status' => PurchaseRequestStatus::Draft,
        ]);
        PurchaseRequestItem::factory(2)->create([
            'purchase_request_id' => $pr->id,
        ]);

        $response = $this->actingAs($aco)
            ->postJson("/api/v1/purchase-requests/{$pr->id}/submit");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'status' => 'submitted',
        ]);
    }

    public function test_aco_cannot_submit_a_request_without_items(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $aco->id,
            'status' => PurchaseRequestStatus::Draft,
        ]);

        // No items created — submit should fail
        $response = $this->actingAs($aco)
            ->postJson("/api/v1/purchase-requests/{$pr->id}/submit");

        $response->assertStatus(403);
    }

    public function test_aco_can_cancel_their_own_draft_request(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $aco->id,
            'status' => PurchaseRequestStatus::Draft,
        ]);

        $response = $this->actingAs($aco)
            ->postJson("/api/v1/purchase-requests/{$pr->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_purchaser_cannot_create_a_purchase_request(): void
    {
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);

        $response = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-requests', $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_non_aco_cannot_update_someone_elses_request(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);

        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $aco->id,
            'status' => PurchaseRequestStatus::Draft,
        ]);

        $response = $this->actingAs($purchaser)
            ->patchJson("/api/v1/purchase-requests/{$pr->id}", [
                'purpose' => 'Hacked purpose',
            ]);

        $response->assertStatus(403);
    }
}
