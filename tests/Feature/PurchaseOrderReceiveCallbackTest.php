<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PurchaseOrderReceiveCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(string $role): User
    {
        $email = strtolower(str_replace(' ', '-', $role)) . '-' . uniqid() . '@test.com';

        return User::forceCreate([
            'name' => $role . ' User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    protected function createPendingPurchaseOrder(): PurchaseOrder
    {
        $purchaser = $this->createUser('purchaser');
        $purchaseRequest = \App\Models\PurchaseRequest::forceCreate([
            'requested_by' => $purchaser->id,
            'purpose' => 'Test request',
            'status' => 'submitted',
            'request_number' => 'PR-' . strtoupper(uniqid()),
            'request_date' => now()->toDateString(),
        ]);

        return PurchaseOrder::forceCreate([
            'purchase_request_id' => $purchaseRequest->id,
            'po_number' => 'PO-TEST-' . uniqid(),
            'created_by' => $purchaser->id,
            'supplier_name' => 'Test Supplier',
            'order_date' => now()->toDateString(),
            'total_amount' => 500,
            'status' => 'pending_receipt',
            'remarks' => null,
            'receipt_remarks' => null,
            'receipt_verified_at' => null,
            'receipt_verified_by' => null,
        ]);
    }

    protected function createPurchaseOrderItem(PurchaseOrder $purchaseOrder): PurchaseOrderItem
    {
        return PurchaseOrderItem::forceCreate([
            'purchase_order_id' => $purchaseOrder->id,
            'item_name' => 'Bond Paper',
            'description' => 'A4 paper',
            'quantity' => 10,
            'unit' => 'ream',
            'price' => 50,
            'total_amount' => 500,
        ]);
    }

    public function test_asset_control_officer_can_receive_matching_purchase_order(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($purchaseOrder);

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'is_received' => true,
                    'received_item_name' => 'Bond Paper',
                    'received_quantity' => 10,
                    'received_unit' => 'ream',
                    'received_price' => 50,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'received',
            'receipt_verified_by' => $aco->id,
        ]);

        $this->assertDatabaseHas('purchase_order_item_receipts', [
            'purchase_order_item_id' => $item->id,
            'is_received' => true,
        ]);
    }

    public function test_asset_control_officer_can_receive_matching_purchase_order_without_is_received_flag(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($purchaseOrder);

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'received_item_name' => 'Bond Paper',
                    'received_quantity' => 10,
                    'received_unit' => 'ream',
                    'received_price' => 50,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'received',
        ]);
    }

    public function test_asset_control_officer_cannot_receive_mismatched_purchase_order(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($purchaseOrder);

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'is_received' => true,
                    'received_item_name' => 'Bond Paper',
                    'received_quantity' => 5,
                    'received_unit' => 'ream',
                    'received_price' => 50,
                ],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'callback_requested',
            'receipt_verified_by' => $aco->id,
        ]);
    }

    public function test_receive_with_not_received_item_and_alternative_sets_callback_requested(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($purchaseOrder);

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'is_received' => false,
                    'alternative_item_name' => 'A3 Paper',
                    'alternative_quantity' => 10,
                    'alternative_unit' => 'ream',
                    'alternative_price' => 80,
                    'alternative_reason' => 'Supplier sent A3 instead of A4.',
                ],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'callback_requested',
            'receipt_verified_by' => $aco->id,
        ]);

        $this->assertDatabaseHas('purchase_order_item_receipts', [
            'purchase_order_item_id' => $item->id,
            'is_received' => false,
            'alternative_item_name' => 'A3 Paper',
        ]);

        $response->assertJsonStructure([
            'message',
            'mismatches',
            'unreceived_items',
        ]);

        $response->assertJsonFragment([
            'unreceived_items' => ['Bond Paper'],
        ]);
    }

    public function test_receive_with_partial_items_not_received_sets_callback_requested(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();
        $item1 = $this->createPurchaseOrderItem($purchaseOrder);

        $item2 = PurchaseOrderItem::forceCreate([
            'purchase_order_id' => $purchaseOrder->id,
            'item_name' => 'Stapler',
            'description' => 'Stapler',
            'quantity' => 5,
            'unit' => 'pcs',
            'price' => 200,
            'total_amount' => 1000,
        ]);

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item1->id,
                    'is_received' => true,
                    'received_item_name' => 'Bond Paper',
                    'received_quantity' => 10,
                    'received_unit' => 'ream',
                    'received_price' => 50,
                ],
                [
                    'purchase_order_item_id' => $item2->id,
                    'is_received' => false,
                    'alternative_item_name' => 'Stapler Pro',
                    'alternative_quantity' => 5,
                    'alternative_unit' => 'pcs',
                    'alternative_price' => 250,
                    'alternative_reason' => 'Wrong model received.',
                ],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('purchase_order_item_receipts', [
            'purchase_order_item_id' => $item1->id,
            'is_received' => true,
        ]);

        $this->assertDatabaseHas('purchase_order_item_receipts', [
            'purchase_order_item_id' => $item2->id,
            'is_received' => false,
            'alternative_item_name' => 'Stapler Pro',
        ]);

        $response->assertJsonFragment([
            'unreceived_items' => ['Stapler'],
        ]);
    }

    public function test_receive_not_received_item_requires_alternative_fields(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($purchaseOrder);

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'is_received' => false,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_purchaser_cannot_receive_purchase_order(): void
    {
        $purchaser = $this->createUser('purchaser');
        $purchaseOrder = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($purchaseOrder);

        $response = $this->actingAs($purchaser)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'received_item_name' => 'Bond Paper',
                    'received_quantity' => 10,
                    'received_unit' => 'ream',
                    'received_price' => 50,
                ],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_finance_cannot_receive_purchase_order(): void
    {
        $finance = $this->createUser('finance_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($purchaseOrder);

        $response = $this->actingAs($finance)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'received_item_name' => 'Bond Paper',
                    'received_quantity' => 10,
                    'received_unit' => 'ream',
                    'received_price' => 50,
                ],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_general_manager_cannot_receive_purchase_order(): void
    {
        $gm = $this->createUser('general_manager');
        $purchaseOrder = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($purchaseOrder);

        $response = $this->actingAs($gm)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'received_item_name' => 'Bond Paper',
                    'received_quantity' => 10,
                    'received_unit' => 'ream',
                    'received_price' => 50,
                ],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_asset_control_officer_can_request_callback(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/callback", [
            'remarks' => 'The delivered quantity does not match the purchase order.',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'callback_requested',
            'receipt_remarks' => 'The delivered quantity does not match the purchase order.',
            'receipt_verified_by' => $aco->id,
        ]);
    }

    public function test_callback_requires_remarks(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/callback", [
            'remarks' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_purchaser_cannot_request_callback(): void
    {
        $purchaser = $this->createUser('purchaser');
        $purchaseOrder = $this->createPendingPurchaseOrder();

        $response = $this->actingAs($purchaser)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/callback", [
            'remarks' => 'Testing callback.',
        ]);

        $response->assertForbidden();
    }

    public function test_pending_receipt_purchase_order_cannot_be_received_by_non_aco(): void
    {
        $finance = $this->createUser('finance_officer');
        $purchaseOrder = $this->createPendingPurchaseOrder();

        $response = $this->actingAs($finance)->postJson("/api/v1/purchase-orders/{$purchaseOrder->id}/callback", [
            'remarks' => 'Finance should not be able to do this.',
        ]);

        $response->assertForbidden();
    }
}