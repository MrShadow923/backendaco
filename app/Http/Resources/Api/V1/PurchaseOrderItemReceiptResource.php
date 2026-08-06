<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'is_received' => $this->is_received,
            'received_item_name' => $this->received_item_name,
            'received_quantity' => $this->received_quantity,
            'received_unit' => $this->received_unit,
            'received_price' => $this->received_price,
            'alternative_item_name' => $this->alternative_item_name,
            'alternative_quantity' => $this->alternative_quantity,
            'alternative_unit' => $this->alternative_unit,
            'alternative_price' => $this->alternative_price,
            'alternative_reason' => $this->alternative_reason,
            'verified_by' => new UserResource($this->whenLoaded('verifiedBy')),
            'verified_at' => $this->verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
