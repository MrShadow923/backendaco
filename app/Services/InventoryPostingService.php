<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemReceipt;
use Illuminate\Support\Facades\DB;

class InventoryPostingService
{
    /**
     * Post all received items from a Purchase Order into inventory.
     *
     * For each received PO item:
     * 1. Check if an inventory item exists by normalized item name.
     * 2. If it exists: increase quantity, update latest price, recompute weighted average cost.
     * 3. If it does not exist: create it with opening quantity and cost.
     * 4. Create an immutable inventory transaction log for every posting.
     *
     * @param  PurchaseOrder  $purchaseOrder
     * @param  int  $userId  The user performing the receipt (ACO).
     * @return void
     */
    public function postPurchaseOrderReceipt(PurchaseOrder $purchaseOrder, int $userId): void
    {
        DB::transaction(function () use ($purchaseOrder, $userId) {
            /** @var PurchaseOrderItem $item */
            foreach ($purchaseOrder->items as $item) {
                $receipt = $item->receipt;

                if (! $receipt || ! $receipt->is_received) {
                    continue;
                }

                $receivedQty = (float) $receipt->received_quantity;
                $receivedPrice = (float) $receipt->received_price;
                $itemName = $receipt->received_item_name ?? $item->item_name;
                $unit = $receipt->received_unit ?? $item->unit;

                $normalizedName = InventoryItem::normalizeItemName($itemName);

                $inventoryItem = InventoryItem::firstOrNew(['item_name' => $normalizedName]);

                if (! $inventoryItem->exists) {
                    $inventoryItem->display_name = $itemName;
                    $inventoryItem->quantity = $receivedQty;
                    $inventoryItem->unit = $unit;
                    $inventoryItem->latest_unit_price = $receivedPrice;
                    $inventoryItem->average_unit_cost = $receivedPrice;
                    $inventoryItem->save();
                } else {
                    $newQuantity = (float) $inventoryItem->quantity + $receivedQty;
                    $weightedCost = ((float) $inventoryItem->quantity * (float) $inventoryItem->average_unit_cost)
                        + ($receivedQty * $receivedPrice);
                    $newAvgCost = $newQuantity > 0 ? $weightedCost / $newQuantity : 0;

                    $inventoryItem->quantity = $newQuantity;
                    $inventoryItem->latest_unit_price = $receivedPrice;
                    $inventoryItem->average_unit_cost = $newAvgCost;
                    $inventoryItem->save();
                }

                InventoryTransaction::create([
                    'inventory_item_id' => $inventoryItem->id,
                    'transaction_type' => 'po_receipt',
                    'reference_type' => 'PurchaseOrder',
                    'reference_id' => $purchaseOrder->id,
                    'quantity' => $receivedQty,
                    'unit_price' => $receivedPrice,
                    'unit_cost' => $inventoryItem->average_unit_cost,
                    'running_quantity' => $inventoryItem->quantity,
                    'running_avg_cost' => $inventoryItem->average_unit_cost,
                    'remarks' => "Received from PO {$purchaseOrder->po_number}"
                        . ($receipt->received_item_name !== $item->item_name
                            ? " (alternative: {$receipt->received_item_name})"
                            : ''),
                    'created_by' => $userId,
                ]);
            }
        });
    }
}
