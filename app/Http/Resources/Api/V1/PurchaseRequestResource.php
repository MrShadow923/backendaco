<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'request_date' => $this->request_date,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'requester' => new UserResource($this->whenLoaded('requestedBy')),
            'requested_by' => new UserResource($this->whenLoaded('requestedBy')),
            'items' => PurchaseRequestItemResource::collection($this->whenLoaded('items')),
            'purchase_order' => new PurchaseOrderResource($this->whenLoaded('purchaseOrder')),
            'total_amount' => $this->total_amount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}