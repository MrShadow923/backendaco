<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderSignatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'role' => $this->role,
            'action' => $this->action->value,
            'remarks' => $this->remarks,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'ip_address' => $this->ip_address,
        ];
    }
}
