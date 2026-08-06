<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrderFromRequestService
{
    public function handle(PurchaseRequest $purchaseRequest, array $data, int $userId): PurchaseOrder
    {
        if ($purchaseRequest->status !== PurchaseRequestStatus::Submitted) {
            throw ValidationException::withMessages([
                'purchase_request' => ['Only submitted purchase requests can be converted to a purchase order.'],
            ]);
        }

        if ($purchaseRequest->purchaseOrder()->exists()) {
            throw ValidationException::withMessages([
                'purchase_request' => ['This purchase request has already been converted to a purchase order.'],
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $data, $userId) {
            $totalAmount = 0;

            $purchaseOrder = PurchaseOrder::create([
                'po_number' => 'PO-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
                'purchase_request_id' => $purchaseRequest->id,
                'created_by' => $userId,
                'supplier_name' => $data['supplier_name'],
                'order_date' => $data['order_date'],
                'remarks' => $data['remarks'] ?? null,
                'status' => PurchaseOrderStatus::Draft,
                'total_amount' => 0,
            ]);

            foreach ($data['items'] as $item) {
                $requestItem = $purchaseRequest->items()
                    ->findOrFail($item['purchase_request_item_id']);

                $itemTotal = $requestItem->quantity * $item['price'];
                $totalAmount += $itemTotal;

                $purchaseOrder->items()->create([
                    'purchase_request_item_id' => $requestItem->id,
                    'item_name' => $requestItem->item_name,
                    'description' => $requestItem->description,
                    'quantity' => $requestItem->quantity,
                    'unit' => $requestItem->unit,
                    'price' => $item['price'],
                    'total_amount' => $itemTotal,
                ]);
            }

            $purchaseOrder->update([
                'total_amount' => $totalAmount,
            ]);

            $purchaseRequest->update([
                'status' => PurchaseRequestStatus::ConvertedToPo,
            ]);

            return $purchaseOrder->load('items');
        });
    }
}