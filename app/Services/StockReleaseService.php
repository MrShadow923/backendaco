<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\StockReleaseStatus;
use App\Models\Department;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\RevenueCenter;
use App\Models\StockRelease;
use App\Models\StockReleaseItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockReleaseService
{
    /**
     * Create and release stock from inventory.
     *
     * @param  array  $data  Validated release data:
     *                         - department_id (int)
     *                         - revenue_center_id (int)
     *                         - notes (string|null)
     *                         - items (array of {inventory_item_id, quantity})
     * @param  User  $user
     * @return StockRelease
     *
     * @throws ValidationException
     */
    public function createAndRelease(array $data, User $user): StockRelease
    {
        $totalQuantity = 0;
        $totalAmount = 0;

        return DB::transaction(function () use ($data, $user, &$totalQuantity, &$totalAmount) {
            // Validate department and revenue center exist
            $department = Department::findOrFail($data['department_id']);
            $revenueCenter = RevenueCenter::findOrFail($data['revenue_center_id']);

            // Create the stock release record
            $stockRelease = StockRelease::create([
                'reference_number' => StockRelease::generateReferenceNumber(),
                'department_id' => $department->id,
                'revenue_center_id' => $revenueCenter->id,
                'status' => StockReleaseStatus::Released,
                'released_at' => now(),
                'released_by' => $user->id,
                'notes' => $data['notes'] ?? null,
                'total_quantity' => 0,
                'total_amount' => 0,
            ]);

            foreach ($data['items'] as $index => $itemData) {
                $inventoryItem = $this->lockAndGetInventoryItem($itemData['inventory_item_id']);

                $quantity = (float) $itemData['quantity'];

                if ($quantity > $inventoryItem->quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Insufficient stock for item '{$inventoryItem->display_name}'. Available: {$inventoryItem->quantity}, Requested: {$quantity}.",
                    ]);
                }

                $unitCost = (float) $inventoryItem->average_unit_cost;
                $lineTotal = $quantity * $unitCost;

                // Reduce inventory
                $inventoryItem->decrement('quantity', $quantity);

                // Recalculate average cost (simple approach: keep current avg cost,
                // the reduction doesn't change the per-unit cost of remaining stock)
                $inventoryItem->latest_unit_price = $unitCost;

                // Create inventory transaction log
                InventoryTransaction::create([
                    'inventory_item_id' => $inventoryItem->id,
                    'transaction_type' => InventoryTransactionType::Issued,
                    'reference_type' => StockRelease::class,
                    'reference_id' => $stockRelease->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitCost,
                    'unit_cost' => $inventoryItem->average_unit_cost,
                    'running_quantity' => $inventoryItem->quantity,
                    'running_avg_cost' => $inventoryItem->average_unit_cost,
                    'remarks' => "Stock released to {$department->name} via {$stockRelease->reference_number}",
                    'created_by' => $user->id,
                ]);

                // Create stock release item record
                StockReleaseItem::create([
                    'stock_release_id' => $stockRelease->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_amount' => $lineTotal,
                ]);

                $totalQuantity += $quantity;
                $totalAmount += $lineTotal;
                $inventoryItem->refresh();
            }

            $stockRelease->update([
                'total_quantity' => $totalQuantity,
                'total_amount' => $totalAmount,
            ]);

            return $stockRelease;
        });
    }

    /**
     * Lock an inventory item row for update to prevent race conditions.
     */
    protected function lockAndGetInventoryItem(int $itemId): InventoryItem
    {
        return InventoryItem::where('id', $itemId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
