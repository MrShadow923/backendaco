<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePurchaseRequestFormatRequest;
use App\Http\Requests\Api\V1\UpdatePurchaseRequestFormatRequest;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestFormat;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseRequestActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequestFormatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $formats = PurchaseRequestFormat::with('createdBy')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json($formats);
    }

    public function store(StorePurchaseRequestFormatRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $format = PurchaseRequestFormat::create([
            'name' => $validated['name'] ?? null,
            'format_data' => $validated['format_data'],
            'status' => $validated['status'] ?? 'draft',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Purchase request format created successfully.',
            'data' => $format,
        ], 201);
    }

    public function show(Request $request, PurchaseRequestFormat $format): JsonResponse
    {
        $format->load('createdBy');

        return response()->json([
            'data' => $format,
        ]);
    }

    public function update(UpdatePurchaseRequestFormatRequest $request, PurchaseRequestFormat $format): JsonResponse
    {
        $format->update($request->validated());

        return response()->json([
            'message' => 'Purchase request format updated successfully.',
            'data' => $format,
        ]);
    }

    public function destroy(Request $request, PurchaseRequestFormat $format): JsonResponse
    {
        $format->delete();

        return response()->json([
            'message' => 'Purchase request format deleted successfully.',
        ]);
    }

    public function createPurchaseRequest(Request $request, PurchaseRequestFormat $format): JsonResponse
    {
        $this->authorize('create', PurchaseRequest::class);

        $formatData = $format->format_data;

        if (empty($formatData['purpose']) || empty($formatData['items']) || !is_array($formatData['items'])) {
            return response()->json([
                'message' => 'Invalid format data. Purpose and items are required.',
            ], 422);
        }

        $purchaseRequest = DB::transaction(function () use ($formatData, $request, $format) {
            $purchaseRequest = PurchaseRequest::create([
                'requested_by' => $request->user()->id,
                'request_date' => now()->toDateString(),
                'purpose' => $formatData['purpose'],
                'remarks' => $formatData['remarks'] ?? null,
                'status' => \App\Enums\PurchaseRequestStatus::Draft,
            ]);

            $totalAmount = 0;
            foreach ($formatData['items'] as $item) {
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
                'details' => ['total_amount' => $totalAmount, 'items_count' => count($formatData['items']), 'from_format_id' => $format->id],
                'created_at' => now(),
            ]);

            return $purchaseRequest;
        });

        $purchaseRequest->load(['items', 'requestedBy']);

        return response()->json([
            'message' => 'Purchase request created from format successfully.',
            'data' => $purchaseRequest,
        ], 201);
    }
}
