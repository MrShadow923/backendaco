<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Models\Department;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\RevenueCenter;
use App\Models\StockRelease;
use App\Models\StockReleaseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockReleaseTest extends TestCase
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

    protected function createStockReleaseData(InventoryItem $item, int $quantity): array
    {
        $center = RevenueCenter::factory()->create();
        $department = Department::factory()->create();

        return [
            'department_id' => $department->id,
            'revenue_center_id' => $center->id,
            'notes' => 'Stock release for testing',
            'items' => [
                [
                    'inventory_item_id' => $item->id,
                    'quantity' => $quantity,
                ],
            ],
        ];
    }

    public function test_stock_release_can_be_created(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create([
            'quantity' => 100,
            'average_unit_cost' => 50,
        ]);

        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 10));

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'reference_number', 'department', 'revenue_center', 'status',
                    'released_at', 'released_by', 'notes', 'total_quantity', 'total_amount',
                    'items', 'created_at', 'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('stock_releases', [
            'reference_number' => $response->json('data.reference_number'),
            'status' => 'released',
            'notes' => 'Stock release for testing',
        ]);
    }

    public function test_stock_release_reduces_inventory_quantity(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create([
            'quantity' => 100,
            'average_unit_cost' => 50,
        ]);

        $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 30));

        $item->refresh();

        $this->assertEquals(70, $item->quantity);
    }

    public function test_stock_release_creates_inventory_transaction_logs(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create([
            'quantity' => 100,
            'average_unit_cost' => 50,
        ]);

        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 25));

        $releaseId = $response->json('data.id');

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'transaction_type' => InventoryTransactionType::Issued->value,
            'reference_type' => StockRelease::class,
            'reference_id' => $releaseId,
            'quantity' => 25,
            'created_by' => $user->id,
        ]);
    }

    public function test_stock_release_records_department(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);

        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 5));

        $departmentId = $response->json('data.department.id');

        $this->assertDatabaseHas('stock_releases', [
            'id' => $response->json('data.id'),
            'department_id' => $departmentId,
        ]);
    }

    public function test_stock_release_records_revenue_center(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);

        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 5));

        $this->assertDatabaseHas('stock_releases', [
            'id' => $response->json('data.id'),
            'revenue_center_id' => $response->json('data.revenue_center.id'),
        ]);
    }

    public function test_stock_release_records_released_date_and_time(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);

        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 5));

        $response->assertJsonStructure(['data' => ['released_at']]);
        $this->assertNotNull($response->json('data.released_at'));
        $this->assertDatabaseHas('stock_releases', [
            'released_at' => $response->json('data.released_at'),
        ]);
    }

    public function test_stock_release_records_released_user(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);

        $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 5));

        $this->assertDatabaseHas('stock_releases', [
            'released_by' => $user->id,
        ]);
    }

    public function test_stock_release_fails_when_no_items_provided(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $center = RevenueCenter::factory()->create();
        $department = Department::factory()->create();

        $response = $this->postJson('/api/v1/stock-releases', [
            'department_id' => $department->id,
            'revenue_center_id' => $center->id,
            'notes' => 'No items',
            'items' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_stock_release_fails_when_quantity_exceeds_available_inventory(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create([
            'quantity' => 10,
            'average_unit_cost' => 50,
        ]);

        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 50));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('stock_releases', ['reference_number' => $response->json('errors')]);
    }

    public function test_stock_release_fails_when_inventory_item_does_not_exist(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $center = RevenueCenter::factory()->create();
        $department = Department::factory()->create();

        $response = $this->postJson('/api/v1/stock-releases', [
            'department_id' => $department->id,
            'revenue_center_id' => $center->id,
            'items' => [
                ['inventory_item_id' => 99999, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_stock_release_fails_when_department_does_not_exist(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);
        $center = RevenueCenter::factory()->create();

        $response = $this->postJson('/api/v1/stock-releases', [
            'department_id' => 99999,
            'revenue_center_id' => $center->id,
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_stock_release_fails_when_revenue_center_does_not_exist(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);
        $department = Department::factory()->create();

        $response = $this->postJson('/api/v1/stock-releases', [
            'department_id' => $department->id,
            'revenue_center_id' => 99999,
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthorized_user_cannot_release_stock(): void
    {
        $purchaser = $this->createUser('purchaser');
        $this->actingAs($purchaser, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);
        $center = RevenueCenter::factory()->create();
        $department = Department::factory()->create();

        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 5));

        $response->assertForbidden();
    }

    public function test_released_stock_release_cannot_be_edited(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);

        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 5));

        $releaseId = $response->json('data.id');

        $editResponse = $this->patchJson("/api/v1/stock-releases/{$releaseId}", [
            'notes' => 'Trying to edit',
        ]);

        $editResponse->assertStatus(405);
    }

    public function test_stock_release_list_can_be_viewed(): void
    {
        $user = $this->createUser('finance_officer');
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/stock-releases');

        $response->assertOk();
    }

    public function test_stock_release_detail_can_be_viewed(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);
        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 5));

        $releaseId = $response->json('data.id');

        $detailResponse = $this->getJson("/api/v1/stock-releases/{$releaseId}");

        $detailResponse->assertOk()
            ->assertJsonStructure(['data' => ['id', 'reference_number', 'status', 'items']]);
    }

    public function test_quantity_validation_prevents_exceeding_available(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create([
            'quantity' => 5,
            'average_unit_cost' => 50,
        ]);

        $response = $this->postJson('/api/v1/stock-releases', $this->createStockReleaseData($item, 10));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $user = $this->createUser('asset_control_officer');
        $this->actingAs($user, 'sanctum');

        $item = InventoryItem::factory()->create(['quantity' => 100, 'average_unit_cost' => 10]);
        $center = RevenueCenter::factory()->create();
        $department = Department::factory()->create();

        $response = $this->postJson('/api/v1/stock-releases', [
            'department_id' => $department->id,
            'revenue_center_id' => $center->id,
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 0],
            ],
        ]);

        $response->assertStatus(422);
    }
}
