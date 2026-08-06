<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\V1\PurchaseOrderSignatureResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'purchase_request_id' => $this->purchase_request_id,
            'purchase_request' => new PurchaseRequestResource($this->whenLoaded('purchaseRequest')),
            'supplier_name' => $this->supplier_name,
            'order_date' => $this->order_date,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'receipt_remarks' => $this->receipt_remarks,
            'receipt_verified_at' => $this->receipt_verified_at?->toISOString(),
            'receipt_verified_by' => new UserResource($this->whenLoaded('receiptVerifiedBy')),
            'received_at' => $this->received_at?->toISOString(),
            'received_by' => new UserResource($this->whenLoaded('receivedBy')),
            'creator' => new UserResource($this->whenLoaded('createdBy')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'signatures' => PurchaseOrderSignatureResource::collection($this->whenLoaded('signatures')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}