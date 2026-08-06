<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\UserRole;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSignature;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ───────────────────────────────────────────────

    private function createUserWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * Create a submitted PR with items, owned by the given ACO.
     */
    private function createSubmittedPr(User $aco): PurchaseRequest
    {
        $pr = PurchaseRequest::factory()->create([
            'requested_by' => $aco->id,
            'status' => PurchaseRequestStatus::Submitted,
        ]);
        PurchaseRequestItem::factory(2)->create([
            'purchase_request_id' => $pr->id,
        ]);

        return $pr;
    }

    private function validPoPayload(int $prId): array
    {
        return [
            'purchase_request_id' => $prId,
            'supplier_name' => 'ABC Supplier Co.',
            'order_date' => '2026-08-01',
            'remarks' => 'Standard order',
            'items' => [
                [
                    'item_name' => 'A4 Paper',
                    'description' => '80gsm white paper',
                    'quantity' => 50,
                    'unit' => 'box',
                    'price' => 25.00,
                ],
                [
                    'item_name' => 'Ballpoint Pen',
                    'description' => 'Blue ink',
                    'quantity' => 100,
                    'unit' => 'pcs',
                    'price' => 2.50,
                ],
            ],
        ];
    }

    // ─── Tests ─────────────────────────────────────────────────

    public function test_purchaser_can_create_po_from_submitted_pr(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $pr = $this->createSubmittedPr($aco);

        $response = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $response->assertStatus(201)
            ->assertJsonPath('data.supplier_name', 'ABC Supplier Co.')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('purchase_orders', [
            'purchase_request_id' => $pr->id,
            'supplier_name' => 'ABC Supplier Co.',
            'status' => 'draft',
        ]);

        $this->assertDatabaseCount('purchase_order_items', 2);

        $po = PurchaseOrder::first();
        $this->assertNotNull($po->po_number);
        $this->assertStringStartsWith('PO-', $po->po_number);
    }

    public function test_creating_po_locks_the_pr(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $pr = $this->createSubmittedPr($aco);

        $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $pr->refresh();
        $this->assertEquals(PurchaseRequestStatus::ConvertedToPO, $pr->status);
    }

    public function test_purchaser_can_edit_draft_po(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $pr = $this->createSubmittedPr($aco);

        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        $response = $this->actingAs($purchaser)
            ->patchJson("/api/v1/purchase-orders/{$poId}", [
                'supplier_name' => 'Updated Supplier',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.supplier_name', 'Updated Supplier');
    }

    public function test_purchaser_can_submit_po_for_signatures(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $pr = $this->createSubmittedPr($aco);

        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        $response = $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/submit");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_finance_signature');
    }

    public function test_finance_can_sign_when_status_is_pending_finance_signature(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $finance = $this->createUserWithRole(UserRole::FinanceOfficer);
        $pr = $this->createSubmittedPr($aco);

        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        // Submit the PO
        $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/submit");

        // Finance signs
        $response = $this->actingAs($finance)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign", [
                'remarks' => 'Approved by finance',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_gm_signature');

        $this->assertDatabaseHas('purchase_order_signatures', [
            'purchase_order_id' => $poId,
            'role' => 'finance_officer',
            'action' => 'signed',
        ]);
    }

    public function test_finance_cannot_sign_when_status_is_not_pending_finance_signature(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $finance = $this->createUserWithRole(UserRole::FinanceOfficer);
        $pr = $this->createSubmittedPr($aco);

        // Create PO but don't submit (status = draft)
        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        // Finance tries to sign a draft PO — policy denies (403)
        $response = $this->actingAs($finance)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign", [
                'remarks' => 'Trying to sign early',
            ]);

        $response->assertStatus(403);
    }

    public function test_gm_can_sign_after_finance_signs(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $finance = $this->createUserWithRole(UserRole::FinanceOfficer);
        $gm = $this->createUserWithRole(UserRole::GeneralManager);
        $pr = $this->createSubmittedPr($aco);

        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        // Submit
        $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/submit");

        // Finance signs → status becomes pending_gm_signature
        $this->actingAs($finance)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign", [
                'remarks' => 'Finance approved',
            ]);

        // GM signs
        $response = $this->actingAs($gm)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign", [
                'remarks' => 'GM approved',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('purchase_order_signatures', [
            'purchase_order_id' => $poId,
            'role' => 'general_manager',
            'action' => 'signed',
        ]);
    }

    public function test_gm_cannot_sign_before_finance_signs(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $gm = $this->createUserWithRole(UserRole::GeneralManager);
        $pr = $this->createSubmittedPr($aco);

        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        // Submit → status = pending_finance_signature
        $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/submit");

        // GM tries to sign before finance — policy denies (403)
        $response = $this->actingAs($gm)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign", [
                'remarks' => 'Trying to skip finance',
            ]);

        $response->assertStatus(403);
    }

    public function test_finance_rejection_sets_po_status_to_rejected(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $finance = $this->createUserWithRole(UserRole::FinanceOfficer);
        $pr = $this->createSubmittedPr($aco);

        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        // Submit
        $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/submit");

        // Finance rejects
        $response = $this->actingAs($finance)
            ->postJson("/api/v1/purchase-orders/{$poId}/reject", [
                'remarks' => 'Budget exceeded',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('purchase_order_signatures', [
            'purchase_order_id' => $poId,
            'role' => 'finance_officer',
            'action' => 'rejected',
        ]);
    }

    public function test_gm_rejection_sets_po_status_to_rejected(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $finance = $this->createUserWithRole(UserRole::FinanceOfficer);
        $gm = $this->createUserWithRole(UserRole::GeneralManager);
        $pr = $this->createSubmittedPr($aco);

        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        // Submit
        $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/submit");

        // Finance signs first
        $this->actingAs($finance)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign");

        // GM rejects
        $response = $this->actingAs($gm)
            ->postJson("/api/v1/purchase-orders/{$poId}/reject", [
                'remarks' => 'Not justified',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('purchase_order_signatures', [
            'purchase_order_id' => $poId,
            'role' => 'general_manager',
            'action' => 'rejected',
        ]);
    }

    public function test_full_workflow_create_pr_to_approved_po(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $finance = $this->createUserWithRole(UserRole::FinanceOfficer);
        $gm = $this->createUserWithRole(UserRole::GeneralManager);

        // Step 1: ACO creates PR with items
        $createPrResponse = $this->actingAs($aco)
            ->postJson('/api/v1/purchase-requests', [
                'purpose' => 'Full workflow test',
                'request_date' => '2026-08-01',
                'items' => [
                    [
                        'item_name' => 'Laptop',
                        'quantity' => 5,
                        'unit' => 'pcs',
                        'estimated_price' => 1200.00,
                    ],
                ],
            ]);

        $createPrResponse->assertStatus(201);
        $prId = $createPrResponse->json('data.id');

        // Step 2: ACO submits PR
        $this->actingAs($aco)
            ->postJson("/api/v1/purchase-requests/{$prId}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');

        // Step 3: Purchaser creates PO from submitted PR
        $createPoResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', [
                'purchase_request_id' => $prId,
                'supplier_name' => 'Tech Corp',
                'order_date' => '2026-08-02',
                'items' => [
                    [
                        'item_name' => 'Laptop',
                        'quantity' => 5,
                        'unit' => 'pcs',
                        'price' => 1150.00,
                    ],
                ],
            ]);

        $createPoResponse->assertStatus(201);
        $poId = $createPoResponse->json('data.id');

        // Verify PR is locked
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $prId,
            'status' => 'converted_to_po',
        ]);

        // Step 4: Purchaser submits PO
        $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_finance_signature');

        // Step 5: Finance signs
        $this->actingAs($finance)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign", [
                'remarks' => 'Budget verified',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_gm_signature');

        // Step 6: GM signs → approved
        $this->actingAs($gm)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign", [
                'remarks' => 'Approved',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Final assertions
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $poId,
            'status' => 'approved',
        ]);

        $this->assertDatabaseCount('purchase_order_signatures', 2);
    }

    public function test_duplicate_signature_by_same_role_is_rejected(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $finance = $this->createUserWithRole(UserRole::FinanceOfficer);
        $pr = $this->createSubmittedPr($aco);

        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        // Submit
        $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/submit");

        // Finance signs first time
        $this->actingAs($finance)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign")
            ->assertStatus(200);

        // Finance tries to sign again — should be rejected (403 from policy)
        // Note: after first sign, status is pending_gm_signature, so the
        // policy denies because status doesn't match for finance_officer.
        // We'll also test with a second finance user to verify duplicate check.
        $finance2 = $this->createUserWithRole(UserRole::FinanceOfficer);

        $response = $this->actingAs($finance2)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign", [
                'remarks' => 'Duplicate attempt',
            ]);

        // Status is now pending_gm_signature, so finance can't sign (403)
        $response->assertStatus(403);
    }

    public function test_purchaser_cannot_sign_po(): void
    {
        $aco = $this->createUserWithRole(UserRole::AssetControlOfficer);
        $purchaser = $this->createUserWithRole(UserRole::Purchaser);
        $pr = $this->createSubmittedPr($aco);

        $createResponse = $this->actingAs($purchaser)
            ->postJson('/api/v1/purchase-orders', $this->validPoPayload($pr->id));

        $poId = $createResponse->json('data.id');

        // Submit
        $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/submit");

        // Purchaser tries to sign
        $response = $this->actingAs($purchaser)
            ->postJson("/api/v1/purchase-orders/{$poId}/sign", [
                'remarks' => 'Should not be allowed',
            ]);

        $response->assertStatus(403);
    }
}
