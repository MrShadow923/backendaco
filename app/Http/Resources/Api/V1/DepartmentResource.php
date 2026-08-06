<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'stock_releases' => StockReleaseResource::collection($this->whenLoaded('stockReleases')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}