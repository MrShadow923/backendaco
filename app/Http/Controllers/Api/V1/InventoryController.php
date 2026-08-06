<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InventoryItemResource;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryItem::class);

        $items = InventoryItem::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('display_name', 'like', "%{$request->input('search')}%")
                    ->orWhere('item_name', 'like', "%{$request->input('search')}%");
            })
            ->orderBy('display_name')
            ->with('transactions')
            ->paginate($request->input('per_page', 20));

        return InventoryItemResource::collection($items);
    }

    public function show(Request $request, InventoryItem $inventoryItem)
    {
        $this->authorize('view', $inventoryItem);

        return new InventoryItemResource($inventoryItem->load(['transactions.createdBy']));
    }

    public function transactions(Request $request, InventoryItem $inventoryItem)
    {
        $this->authorize('view', $inventoryItem);

        $transactions = $inventoryItem->transactions()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return InventoryTransactionResource::collection($transactions);
    }
}
