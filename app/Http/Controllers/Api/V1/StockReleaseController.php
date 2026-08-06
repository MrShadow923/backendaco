<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStockReleaseRequest;
use App\Http\Resources\Api\V1\StockReleaseResource;
use App\Models\StockRelease;
use App\Services\StockReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockReleaseController extends Controller
{
    public function __construct(
        protected StockReleaseService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockRelease::class);

        $query = StockRelease::with(['department', 'revenueCenter', 'releasedBy']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $releases = $query->latest('released_at')->paginate($request->input('per_page', 15));

        return StockReleaseResource::collection($releases);
    }

    public function store(StoreStockReleaseRequest $request): StockReleaseResource
    {
        $this->authorize('create', StockRelease::class);

        $stockRelease = $this->service->createAndRelease(
            $request->validated(),
            $request->user(),
        );

        $stockRelease->load(['department', 'revenueCenter', 'releasedBy', 'items.inventoryItem']);

        return new StockReleaseResource($stockRelease);
    }

    public function show(Request $request, StockRelease $stockRelease): StockReleaseResource
    {
        $this->authorize('view', $stockRelease);

        $stockRelease->load(['department', 'revenueCenter', 'releasedBy', 'items.inventoryItem']);

        return new StockReleaseResource($stockRelease);
    }
}
