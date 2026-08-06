<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRevenueCenterRequest;
use App\Http\Requests\Api\V1\UpdateRevenueCenterRequest;
use App\Http\Resources\Api\V1\RevenueCenterResource;
use App\Models\RevenueCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RevenueCenterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RevenueCenter::class);

        $centers = RevenueCenter::when($request->boolean('active_only'), fn ($q) => $q->active())
            ->latest()
            ->paginate($request->input('per_page', 15));

        return RevenueCenterResource::collection($centers);
    }

    public function store(StoreRevenueCenterRequest $request): RevenueCenterResource
    {
        $this->authorize('create', RevenueCenter::class);

        $center = RevenueCenter::create($request->validated());

        return new RevenueCenterResource($center);
    }

    public function show(Request $request, RevenueCenter $revenueCenter): RevenueCenterResource
    {
        $this->authorize('view', $revenueCenter);

        $revenueCenter->load('stockReleases');

        return new RevenueCenterResource($revenueCenter);
    }

    public function update(UpdateRevenueCenterRequest $request, RevenueCenter $revenueCenter): RevenueCenterResource
    {
        $this->authorize('update', $revenueCenter);

        $revenueCenter->update($request->validated());

        return new RevenueCenterResource($revenueCenter);
    }

    public function destroy(Request $request, RevenueCenter $revenueCenter): JsonResponse
    {
        $this->authorize('delete', $revenueCenter);

        $revenueCenter->delete();

        return response()->json(['message' => 'Revenue center deleted successfully.']);
    }
}
