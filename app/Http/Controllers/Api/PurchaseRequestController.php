<?php

namespace App\Http\Controllers\Api;

use App\Enums\PurchaseRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $query = PurchaseRequest::with(['requestedBy', 'items', 'purchaseOrder'])
            ->latest();

        if ($request->user()->role === 'Purchaser') {
            $query->whereIn('status', [
                PurchaseRequestStatus::Submitted,
                PurchaseRequestStatus::ConvertedToPo,
            ]);
        }

        return PurchaseRequestResource::collection(
            $query->paginate($request->integer('per_page', 10))
        );
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        return new PurchaseRequestResource(
            $purchaseRequest->load(['requestedBy', 'items', 'purchaseOrder'])
        );
    }

    public function conversionData(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('convert', $purchaseRequest);

        return new PurchaseRequestResource(
            $purchaseRequest->load('items')
        );
    }
}
