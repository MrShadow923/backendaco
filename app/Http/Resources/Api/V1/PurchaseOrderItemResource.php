<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\PurchaseOrderItemReceiptResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_request_item_id' => $this->purchase_request_item_id,
            'item_name' => $this->item_name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'price' => $this->price,
            'total_amount' => $this->total_amount,
            'receipt' => new PurchaseOrderItemReceiptResource($this->whenLoaded('receipt')),
        ];
    }
}