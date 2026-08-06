<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InventoryPostingTest extends TestCase
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
            'received_at' => null,
            'received_by' => null,
        ]);
    }

    protected function createPurchaseOrderItem(PurchaseOrder $purchaseOrder, string $name = 'Bond Paper', float $qty = 10, float $price = 50): PurchaseOrderItem
    {
        return PurchaseOrderItem::forceCreate([
            'purchase_order_id' => $purchaseOrder->id,
            'item_name' => $name,
            'description' => 'A4 paper',
            'quantity' => $qty,
            'unit' => 'ream',
            'price' => $price,
            'total_amount' => $qty * $price,
        ]);
    }

    public function test_receive_creates_inventory_item_for_new_item(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $po = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($po, 'Bond Paper', 10, 50);

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
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

        $inventoryItem = InventoryItem::where('item_name', 'bond paper')->first();
        $this->assertNotNull($inventoryItem);
        $this->assertEquals(10, $inventoryItem->quantity);
        $this->assertEquals(50, $inventoryItem->latest_unit_price);
        $this->assertEquals(50, $inventoryItem->average_unit_cost);
        $this->assertEquals('Bond Paper', $inventoryItem->display_name);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'transaction_type' => 'po_receipt',
            'reference_type' => 'PurchaseOrder',
            'reference_id' => $po->id,
            'quantity' => 10,
            'unit_price' => 50,
            'running_quantity' => 10,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'received',
            'received_by' => $aco->id,
        ]);

        $this->assertNotNull($po->fresh()->received_at);
    }

    public function test_receive_increases_quantity_for_existing_item(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $po = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($po, 'Bond Paper', 10, 50);

        // Pre-existing inventory
        InventoryItem::create([
            'item_name' => 'bond paper',
            'display_name' => 'Bond Paper',
            'quantity' => 20,
            'unit' => 'ream',
            'latest_unit_price' => 40,
            'average_unit_cost' => 40,
        ]);

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
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

        $inventoryItem = InventoryItem::where('item_name', 'bond paper')->first();
        $this->assertEquals(30, $inventoryItem->quantity);
        $this->assertEquals(50, $inventoryItem->latest_unit_price);

        // Weighted average: ((20 * 40) + (10 * 50)) / 30 = 1300 / 30 = 43.33
        $this->assertEqualsWithDelta(43.33, $inventoryItem->average_unit_cost, 0.01);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 10,
            'unit_price' => 50,
            'running_quantity' => 30,
            'running_avg_cost' => $inventoryItem->average_unit_cost,
        ]);
    }

    public function test_item_name_normalization(): void
    {
        $this->assertEquals('bond paper', InventoryItem::normalizeItemName('Bond Paper'));
        $this->assertEquals('bond paper', InventoryItem::normalizeItemName('  Bond   Paper  '));
        $this->assertEquals('office chair', InventoryItem::normalizeItemName(' office chair '));
        $this->assertEquals('office chair', InventoryItem::normalizeItemName('OFFICE   CHAIR'));
    }

    public function test_mismatched_item_name_creates_different_inventory_item(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $po = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($po, 'Bond Paper', 10, 50);

        // Receive with a different received_item_name (e.g. alternative brand)
        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'is_received' => true,
                    'received_item_name' => 'A3 Paper',
                    'received_quantity' => 10,
                    'received_unit' => 'ream',
                    'received_price' => 50,
                ],
            ],
        ]);

        // Mismatch detected — item_name differs, so this goes to callback_requested
        $response->assertStatus(422);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'callback_requested',
        ]);

        // No inventory should be created on mismatch
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_receive_with_not_received_items_does_not_create_inventory(): void
    {
        $aco = $this->createUser('asset_control_officer');
        $po = $this->createPendingPurchaseOrder();
        $item = $this->createPurchaseOrderItem($po, 'Bond Paper', 10, 50);

        $response = $this->actingAs($aco)->postJson("/api/v1/purchase-orders/{$po->id}/receive", [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'is_received' => false,
                    'alternative_item_name' => 'A3 Paper',
                    'alternative_quantity' => 10,
                    'alternative_unit' => 'ream',
                    'alternative_price' => 80,
                    'alternative_reason' => 'Supplier sent wrong item.',
                ],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'callback_requested',
        ]);

        // No inventory should be created
        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_inventory_route_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/inventory');
        $response->assertUnauthorized();
    }

    public function test_aco_can_view_inventory(): void
    {
        InventoryItem::create([
            'item_name' => 'test item',
            'display_name' => 'Test Item',
            'quantity' => 10,
            'unit' => 'pcs',
            'latest_unit_price' => 100,
            'average_unit_cost' => 100,
        ]);

        $aco = $this->createUser('asset_control_officer');

        $response = $this->actingAs($aco)->getJson('/api/v1/inventory');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id', 'item_name', 'display_name', 'quantity', 'unit',
                        'latest_unit_price', 'average_unit_cost', 'total_value',
                        'transactions', 'created_at', 'updated_at',
                    ],
                ],
            ]);

        $response->assertJsonFragment(['item_name' => 'test item']);
    }
}
