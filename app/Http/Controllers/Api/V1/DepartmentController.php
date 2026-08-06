<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDepartmentRequest;
use App\Http\Requests\Api\V1\UpdateDepartmentRequest;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Http\Resources\Api\V1\StockReleaseResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::when($request->boolean('active_only'), fn ($q) => $q->active())
            ->latest()
            ->paginate($request->input('per_page', 15));

        return DepartmentResource::collection($departments);
    }

    public function store(StoreDepartmentRequest $request): DepartmentResource
    {
        $this->authorize('create', Department::class);

        $department = Department::create($request->validated());

        return new DepartmentResource($department);
    }

    public function show(Request $request, Department $department): DepartmentResource
    {
        $this->authorize('view', $department);

        $department->load('stockReleases');

        return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        return new DepartmentResource($department);
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        $department->delete();

        return response()->json(['message' => 'Department deleted successfully.']);
    }

    public function stockReleases(Request $request, Department $department): AnonymousResourceCollection
    {
        $this->authorize('view', $department);

        $releases = $department->stockReleases()
            ->with(['items'])
            ->latest('released_at')
            ->paginate($request->input('per_page', 15));

        return StockReleaseResource::collection($releases);
    }
}