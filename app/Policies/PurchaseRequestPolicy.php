<?php

namespace App\Policies;

use App\Enums\PurchaseRequestStatus;
use App\Enums\UserRole;
use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::AssetControlOfficer,
            UserRole::Purchaser,
            UserRole::FinanceOfficer,
            UserRole::GeneralManager,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if ($user->role === UserRole::AssetControlOfficer) {
            return $purchaseRequest->requested_by === $user->id;
        }

        return in_array($user->role, [
            UserRole::Purchaser,
            UserRole::FinanceOfficer,
            UserRole::GeneralManager,
        ]);
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->role === UserRole::AssetControlOfficer
            && $purchaseRequest->requested_by === $user->id
            && $purchaseRequest->status === PurchaseRequestStatus::Draft;
    }

    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->role === UserRole::AssetControlOfficer
            && $purchaseRequest->requested_by === $user->id
            && $purchaseRequest->status === PurchaseRequestStatus::Draft;
    }

    public function cancel(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->role === UserRole::AssetControlOfficer
            && $purchaseRequest->requested_by === $user->id
            && in_array($purchaseRequest->status, [
                PurchaseRequestStatus::Draft,
                PurchaseRequestStatus::Submitted,
            ]);
    }

    public function convert(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->role === UserRole::Purchaser
            && $purchaseRequest->status === PurchaseRequestStatus::Submitted
            && ! $purchaseRequest->purchaseOrder()->exists();
    }
}