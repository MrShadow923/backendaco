<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
{
    /**
     * The resource is an array of summary data, not an Eloquent model.
     */
    public function toArray(Request $request): array
    {
        return [
            'pending_requests_count' => $this->resource['pending_requests_count'] ?? 0,
            'pending_orders_count' => $this->resource['pending_orders_count'] ?? 0,
            'pending_my_signature_count' => $this->resource['pending_my_signature_count'] ?? 0,
            'recently_completed_count' => $this->resource['recently_completed_count'] ?? 0,
        ];
    }
}
