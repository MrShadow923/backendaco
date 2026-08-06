<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'revenue_center' => new RevenueCenterResource($this->whenLoaded('revenueCenter')),
            'status' => $this->status,
            'status_label' => $this->status?->label(),
            'released_at' => $this->released_at?->toISOString(),
            'released_by' => new UserResource($this->whenLoaded('releasedBy')),
            'notes' => $this->notes,
            'total_quantity' => $this->total_quantity,
            'total_amount' => $this->total_amount,
            'items' => StockReleaseItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
