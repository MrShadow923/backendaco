<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_name' => $this->item_name,
            'display_name' => $this->display_name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'latest_unit_price' => $this->latest_unit_price,
            'average_unit_cost' => $this->average_unit_cost,
            'total_value' => $this->quantity * $this->average_unit_cost,
            'transactions' => InventoryTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
