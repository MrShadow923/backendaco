<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\SignatureAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RejectPurchaseOrderRequest;
use App\Http\Requests\Api\V1\SignPurchaseOrderRequest;
use App\Http\Requests\Api\V1\StorePurchaseOrderRequest;
use App\Http\Requests\Api\V1\UpdatePurchaseOrderRequest;
use App\Http\Resources\Api\V1\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItemReceipt;
use App\Models\PurchaseOrderSignature;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestActivity;
use App\Services\InventoryPostingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    /**
     * List purchase orders (paginated, filterable by status).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseOrder::with(['items', 'signatures.user', 'createdBy', 'purchaseRequest']);

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->latest()->paginate($request->input('per_page', 15));

        return PurchaseOrderResource::collection($orders);
    }

    /**
     * Create a purchase order from a submitted purchase request.
     */
    public function store(StorePurchaseOrderRequest $request): PurchaseOrderResource
    {
        $this->authorize('create', PurchaseOrder::class);

        $purchaseOrder = DB::transaction(function () use ($request) {
            $purchaseRequest = PurchaseRequest::findOrFail($request->validated('purchase_request_id'));

            // PR must be in submitted status
            if ($purchaseRequest->status !== PurchaseRequestStatus::Submitted) {
                abort(422, 'Purchase request must be in submitted status to create a purchase order.');
            }

            // Check for duplicate PO
            if ($purchaseRequest->purchaseOrder()->exists()) {
                abort(422, 'A purchase order has already been created for this purchase request.');
            }

            $totalAmount = 0;

            $purchaseOrder = PurchaseOrder::create([
                'purchase_request_id' => $purchaseRequest->id,
                'created_by' => $request->user()->id,
                'supplier_name' => $request->validated('supplier_name'),
                'order_date' => $request->validated('order_date', now()->toDateString()),
                'remarks' => $request->validated('remarks'),
                'status' => PurchaseOrderStatus::Draft,
            ]);

            foreach ($request->validated('items') as $item) {
                $itemTotal = $item['quantity'] * $item['price'];
                $purchaseOrder->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'total_amount' => $itemTotal,
                ]);
                $totalAmount += $itemTotal;
            }

            $purchaseOrder->update(['total_amount' => $totalAmount]);

            // Lock the PR — set status to converted_to_po
            $purchaseRequest->update(['status' => PurchaseRequestStatus::ConvertedToPO]);

            // Log activity on the PR
            PurchaseRequestActivity::create([
                'user_id' => $request->user()->id,
                'action' => 'created_po',
                'entity_type' => 'purchase_request',
                'entity_id' => $purchaseRequest->id,
                'details' => ['po_id' => $purchaseOrder->id, 'po_number' => $purchaseOrder->po_number],
                'created_at' => now(),
            ]);

            // Log activity on the PO
            PurchaseRequestActivity::create([
                'user_id' => $request->user()->id,
                'action' => 'created',
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->id,
                'details' => ['total_amount' => $totalAmount, 'items_count' => count($request->validated('items'))],
                'created_at' => now(),
            ]);

            return $purchaseOrder;
        });

        $purchaseOrder->load(['items', 'signatures.user', 'createdBy', 'purchaseRequest']);

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Show a single purchase order with items and signatures.
     */
    public function show(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['items.receipt', 'signatures.user', 'createdBy', 'purchaseRequest', 'receivedBy', 'receiptVerifiedBy', 'activities.user']);

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Update a draft or rejected purchase order.
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder = DB::transaction(function () use ($request, $purchaseOrder) {
            $purchaseOrder->update(
                $request->only(['supplier_name', 'order_date', 'remarks'])
            );

            if ($request->has('items')) {
                $purchaseOrder->items()->delete();

                $totalAmount = 0;
                foreach ($request->validated('items') as $item) {
                    $itemTotal = $item['quantity'] * $item['price'];
                    $purchaseOrder->items()->create([
                        'item_name' => $item['item_name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'],
                        'price' => $item['price'],
                        'total_amount' => $itemTotal,
                    ]);
                    $totalAmount += $itemTotal;
                }

                $purchaseOrder->update(['total_amount' => $totalAmount]);
            }

            PurchaseRequestActivity::create([
                'user_id' => $request->user()->id,
                'action' => 'updated',
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->id,
                'details' => $request->validated(),
                'created_at' => now(),
            ]);

            return $purchaseOrder;
        });

        $purchaseOrder->load(['items', 'signatures.user', 'createdBy']);

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Submit a draft/rejected purchase order for signatures.
     * Sets status to pending_finance_signature.
     */
    public function submit(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('submit', $purchaseOrder);

        $purchaseOrder->update(['status' => PurchaseOrderStatus::PendingFinanceSignature]);

        PurchaseRequestActivity::create([
            'user_id' => $request->user()->id,
            'action' => 'submitted',
            'entity_type' => 'purchase_order',
            'entity_id' => $purchaseOrder->id,
            'details' => ['new_status' => PurchaseOrderStatus::PendingFinanceSignature->value],
            'created_at' => now(),
        ]);

        $purchaseOrder->load(['items', 'signatures.user', 'createdBy']);

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Sign a purchase order (Finance Officer or GM, sequential).
     */
    public function sign(SignPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('sign', $purchaseOrder);

        $user = $request->user();
        $role = $user->role->value;

        $signature = DB::transaction(function () use ($request, $purchaseOrder, $user, $role) {
            $signature = PurchaseOrderSignature::create([
                'purchase_order_id' => $purchaseOrder->id,
                'user_id' => $user->id,
                'role' => $role,
                'action' => SignatureAction::Signed,
                'remarks' => $request->validated('remarks'),
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Update PO status based on who signed
            if ($role === 'finance_officer') {
                $purchaseOrder->update(['status' => PurchaseOrderStatus::PendingGmSignature]);
            } elseif ($role === 'general_manager') {
                $purchaseOrder->update(['status' => PurchaseOrderStatus::PendingReceipt]);
            }

            PurchaseRequestActivity::create([
                'user_id' => $user->id,
                'action' => 'signed',
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->id,
                'details' => [
                    'role' => $role,
                    'new_status' => $purchaseOrder->fresh()->status->value,
                ],
                'created_at' => now(),
            ]);

            return $signature;
        });

        $purchaseOrder->load(['items', 'signatures.user', 'createdBy', 'purchaseRequest']);

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Reject a purchase order (Finance Officer or GM).
     */
    public function reject(RejectPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('reject', $purchaseOrder);

        $user = $request->user();
        $role = $user->role->value;

        DB::transaction(function () use ($request, $purchaseOrder, $user, $role) {
            PurchaseOrderSignature::create([
                'purchase_order_id' => $purchaseOrder->id,
                'user_id' => $user->id,
                'role' => $role,
                'action' => SignatureAction::Rejected,
                'remarks' => $request->validated('remarks'),
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $purchaseOrder->update(['status' => PurchaseOrderStatus::Rejected]);

            PurchaseRequestActivity::create([
                'user_id' => $user->id,
                'action' => 'rejected',
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->id,
                'details' => [
                    'role' => $role,
                    'new_status' => PurchaseOrderStatus::Rejected->value,
                ],
                'created_at' => now(),
            ]);
        });

        $purchaseOrder->load(['items', 'signatures.user', 'createdBy', 'purchaseRequest']);

        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * Receive a purchase order and verify received items.
     *
     * For each item the ACO marks whether it was received. Received items are
     * compared against the PO line; not-received items must provide an
     * alternative item and reason. If any item is not received or any received
     * item mismatches → callback_requested. If all received items match → received.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('receive', $purchaseOrder);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.is_received' => 'boolean',
            'items.*.received_item_name' => 'nullable|string',
            'items.*.received_quantity' => 'nullable|numeric',
            'items.*.received_unit' => 'nullable|string',
            'items.*.received_price' => 'nullable|numeric',
            'items.*.alternative_item_name' => 'nullable|string',
            'items.*.alternative_quantity' => 'nullable|numeric',
            'items.*.alternative_unit' => 'nullable|string',
            'items.*.alternative_price' => 'nullable|numeric',
            'items.*.alternative_reason' => 'nullable|string|max:1000',
        ]);

        $errors = [];
        $mismatches = [];
        $unreceivedItems = [];
        $user = $request->user();

        foreach ($validated['items'] as $idx => $receivedItem) {
            $isReceived = $receivedItem['is_received'] ?? true;

            if ($isReceived) {
                if (! isset($receivedItem['received_item_name']) || $receivedItem['received_item_name'] === '') {
                    $errors["items.$idx.received_item_name"][] = 'The received item name is required.';
                }
                if (! isset($receivedItem['received_quantity'])) {
                    $errors["items.$idx.received_quantity"][] = 'The received quantity is required.';
                }
                if (! isset($receivedItem['received_unit']) || $receivedItem['received_unit'] === '') {
                    $errors["items.$idx.received_unit"][] = 'The received unit is required.';
                }
                if (! isset($receivedItem['received_price'])) {
                    $errors["items.$idx.received_price"][] = 'The received price is required.';
                }
            } else {
                if (! isset($receivedItem['alternative_item_name']) || $receivedItem['alternative_item_name'] === '') {
                    $errors["items.$idx.alternative_item_name"][] = 'The alternative item name is required when item is not received.';
                }
                if (! isset($receivedItem['alternative_unit']) || $receivedItem['alternative_unit'] === '') {
                    $errors["items.$idx.alternative_unit"][] = 'The alternative unit is required when item is not received.';
                }
                if (! isset($receivedItem['alternative_reason']) || $receivedItem['alternative_reason'] === '') {
                    $errors["items.$idx.alternative_reason"][] = 'The reason for the alternative is required.';
                }
            }
        }

        if (! empty($errors)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        $purchaseOrder = DB::transaction(function () use ($validated, $purchaseOrder, $user, &$mismatches, &$unreceivedItems) {
            foreach ($validated['items'] as $receivedItem) {
                $poItem = $purchaseOrder->items()->find($receivedItem['purchase_order_item_id']);

                if (! $poItem) {
                    continue;
                }

                $isReceived = $receivedItem['is_received'] ?? true;

                if ($isReceived) {
                    if ($poItem->item_name !== $receivedItem['received_item_name'] ||
                        $poItem->quantity != $receivedItem['received_quantity'] ||
                        $poItem->unit !== $receivedItem['received_unit'] ||
                        $poItem->price != $receivedItem['received_price']) {
                        $mismatches[] = $poItem->item_name;
                    }

                    PurchaseOrderItemReceipt::create([
                        'purchase_order_item_id' => $poItem->id,
                        'is_received' => true,
                        'received_item_name' => $receivedItem['received_item_name'],
                        'received_quantity' => $receivedItem['received_quantity'],
                        'received_unit' => $receivedItem['received_unit'],
                        'received_price' => $receivedItem['received_price'],
                        'verified_by' => $user->id,
                        'verified_at' => now(),
                    ]);
                } else {
                    $unreceivedItems[] = $poItem->item_name;

                    PurchaseOrderItemReceipt::create([
                        'purchase_order_item_id' => $poItem->id,
                        'is_received' => false,
                        'alternative_item_name' => $receivedItem['alternative_item_name'],
                        'alternative_quantity' => $receivedItem['alternative_quantity'],
                        'alternative_unit' => $receivedItem['alternative_unit'],
                        'alternative_price' => $receivedItem['alternative_price'],
                        'alternative_reason' => $receivedItem['alternative_reason'],
                        'verified_by' => $user->id,
                        'verified_at' => now(),
                    ]);
                }
            }

            return $purchaseOrder;
        });

        $allIssues = array_merge($mismatches, $unreceivedItems);

        if (count($allIssues) > 0) {
            $remarks = [];
            if (! empty($mismatches)) {
                $remarks[] = 'Mismatch detected in: ' . implode(', ', $mismatches);
            }
            if (! empty($unreceivedItems)) {
                $remarks[] = 'Not received: ' . implode(', ', $unreceivedItems);
            }

            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::CallbackRequested,
                'receipt_remarks' => implode('. ', $remarks),
                'receipt_verified_at' => now(),
                'receipt_verified_by' => $user->id,
            ]);

            PurchaseRequestActivity::create([
                'user_id' => $user->id,
                'action' => 'receipt_failed',
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->id,
                'details' => [
                    'mismatches' => $mismatches,
                    'unreceived_items' => $unreceivedItems,
                ],
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'Receipt verification failed. Status set to Callback Requested.',
                'mismatches' => $mismatches,
                'unreceived_items' => $unreceivedItems,
            ], 422);
        }

        DB::transaction(function () use ($purchaseOrder, $user) {
            $inventoryService = new InventoryPostingService();
            $inventoryService->postPurchaseOrderReceipt($purchaseOrder->load('items.receipt'), $user->id);

            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Received,
                'receipt_remarks' => 'All items verified and received successfully.',
                'receipt_verified_at' => now(),
                'receipt_verified_by' => $user->id,
                'received_at' => now(),
                'received_by' => $user->id,
            ]);

            PurchaseRequestActivity::create([
                'user_id' => $user->id,
                'action' => 'received',
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->id,
                'details' => ['total_amount' => $purchaseOrder->total_amount],
                'created_at' => now(),
            ]);
        });

        return new PurchaseOrderResource($purchaseOrder->load(['items.receipt', 'signatures.user', 'createdBy', 'purchaseRequest']));
    }

    /**
     * Request a callback for a purchase order with receipt remarks.
     */
    public function callback(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('callback', $purchaseOrder);

        $validated = $request->validate([
            'remarks' => 'required|string|max:1000',
        ]);

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::CallbackRequested,
            'receipt_remarks' => $validated['remarks'],
            'receipt_verified_at' => now(),
            'receipt_verified_by' => $request->user()->id,
        ]);

        return new PurchaseOrderResource($purchaseOrder->load('items'));
    }
}
