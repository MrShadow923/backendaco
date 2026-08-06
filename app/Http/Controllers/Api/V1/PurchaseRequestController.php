<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PurchaseRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePurchaseRequestRequest;
use App\Http\Requests\Api\V1\UpdatePurchaseRequestRequest;
use App\Http\Resources\Api\V1\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PurchaseRequestController extends Controller
{
    /**
     * List purchase requests (paginated, filterable by status).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseRequest::with(['items', 'requestedBy']);

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Asset Control Officers only see their own requests
        if ($request->user()->isAssetControlOfficer()) {
            $query->where('requested_by', $request->user()->id);
        }

        $requests = $query->latest()->paginate($request->input('per_page', 15));

        return PurchaseRequestResource::collection($requests);
    }

    /**
     * Create a new purchase request with items.
     */
    public function store(StorePurchaseRequestRequest $request): PurchaseRequestResource
    {
        $this->authorize('create', PurchaseRequest::class);

        $purchaseRequest = DB::transaction(function () use ($request) {
            $purchaseRequest = PurchaseRequest::create([
                'requested_by' => $request->user()->id,
                'request_date' => $request->validated('request_date', now()->toDateString()),
                'purpose' => $request->validated('purpose'),
                'remarks' => $request->validated('remarks'),
                'status' => PurchaseRequestStatus::Draft,
            ]);

            $totalAmount = 0;
            foreach ($request->validated('items') as $item) {
                $itemTotal = $item['quantity'] * $item['estimated_price'];
                $purchaseRequest->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'estimated_price' => $item['estimated_price'],
                    'total_amount' => $itemTotal,
                ]);
                $totalAmount += $itemTotal;
            }

            PurchaseRequestActivity::create([
                'user_id' => $request->user()->id,
                'action' => 'created',
                'entity_type' => 'purchase_request',
                'entity_id' => $purchaseRequest->id,
                'details' => ['total_amount' => $totalAmount, 'items_count' => count($request->validated('items'))],
                'created_at' => now(),
            ]);

            return $purchaseRequest;
        });

        $purchaseRequest->load(['items', 'requestedBy']);

        return new PurchaseRequestResource($purchaseRequest);
    }

    /**
     * Show a single purchase request with items.
     */
    public function show(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load(['items', 'requestedBy', 'purchaseOrder', 'activities.user']);

        return new PurchaseRequestResource($purchaseRequest);
    }

    /**
     * Update a draft purchase request.
     */
    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('update', $purchaseRequest);

        $purchaseRequest = DB::transaction(function () use ($request, $purchaseRequest) {
            $purchaseRequest->update(
                $request->only(['purpose', 'request_date', 'remarks'])
            );

            if ($request->has('items')) {
                // Soft-delete existing items and recreate
                $purchaseRequest->items()->delete();

                foreach ($request->validated('items') as $item) {
                    $purchaseRequest->items()->create([
                        'item_name' => $item['item_name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'],
                        'estimated_price' => $item['estimated_price'],
                        'total_amount' => $item['quantity'] * $item['estimated_price'],
                    ]);
                }
            }

            PurchaseRequestActivity::create([
                'user_id' => $request->user()->id,
                'action' => 'updated',
                'entity_type' => 'purchase_request',
                'entity_id' => $purchaseRequest->id,
                'details' => $request->validated(),
                'created_at' => now(),
            ]);

            return $purchaseRequest;
        });

        $purchaseRequest->load(['items', 'requestedBy']);

        return new PurchaseRequestResource($purchaseRequest);
    }

    /**
     * Submit a draft purchase request for processing.
     */
    public function submit(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('submit', $purchaseRequest);

        $purchaseRequest->update(['status' => PurchaseRequestStatus::Submitted]);

        PurchaseRequestActivity::create([
            'user_id' => $request->user()->id,
            'action' => 'submitted',
            'entity_type' => 'purchase_request',
            'entity_id' => $purchaseRequest->id,
            'details' => null,
            'created_at' => now(),
        ]);

        $purchaseRequest->load(['items', 'requestedBy']);

        return new PurchaseRequestResource($purchaseRequest);
    }

    /**
     * Cancel a purchase request (draft or submitted only).
     */
    public function cancel(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('cancel', $purchaseRequest);

        $purchaseRequest->update(['status' => PurchaseRequestStatus::Cancelled]);

        PurchaseRequestActivity::create([
            'user_id' => $request->user()->id,
            'action' => 'cancelled',
            'entity_type' => 'purchase_request',
            'entity_id' => $purchaseRequest->id,
            'details' => null,
            'created_at' => now(),
        ]);

        $purchaseRequest->load(['items', 'requestedBy']);

        return new PurchaseRequestResource($purchaseRequest);
    }
}
