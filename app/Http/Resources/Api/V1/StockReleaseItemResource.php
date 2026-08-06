<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReleaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stock_release_id' => $this->stock_release_id,
            'inventory_item' => new InventoryItemResource($this->whenLoaded('inventoryItem')),
            'inventory_item_id' => $this->inventory_item_id,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_amount' => $this->total_amount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
