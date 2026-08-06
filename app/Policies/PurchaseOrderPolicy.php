<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    /**
     * Anyone authenticated can view purchase orders.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    /**
     * Only Purchasers can create purchase orders.
     */
    public function create(User $user): bool
    {
        return $user->isPurchaser();
    }

    /**
     * Only the creator (Purchaser) can update, and only while editable (draft or rejected).
     */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isPurchaser()
            && $purchaseOrder->created_by === $user->id
            && $purchaseOrder->isEditable();
    }

    /**
     * Only the creator (Purchaser) can submit, and only while editable (draft or rejected).
     */
    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isPurchaser()
            && $purchaseOrder->created_by === $user->id
            && $purchaseOrder->isEditable();
    }

    /**
     * Finance Officer can sign when status = pending_finance_signature.
     * GM can sign when status = pending_gm_signature.
     * No duplicate signatures for the same role.
     */
    public function sign(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->hasSignatureForRole($user->role->value)) {
            return false;
        }

        return match ($user->role) {
            UserRole::FinanceOfficer => $purchaseOrder->status === PurchaseOrderStatus::PendingFinanceSignature,
            UserRole::GeneralManager => $purchaseOrder->status === PurchaseOrderStatus::PendingGmSignature,
            default => false,
        };
    }

    /**
     * Finance Officer can reject when status = pending_finance_signature.
     * GM can reject when status = pending_gm_signature.
     */
    public function reject(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return match ($user->role) {
            UserRole::FinanceOfficer => $purchaseOrder->status === PurchaseOrderStatus::PendingFinanceSignature,
            UserRole::GeneralManager => $purchaseOrder->status === PurchaseOrderStatus::PendingGmSignature,
            default => false,
        };
    }

    /**
     * Only the Asset Control Officer can mark a PO as received when it is pending receipt.
     */
    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->role === UserRole::AssetControlOfficer
            && $purchaseOrder->status === PurchaseOrderStatus::PendingReceipt;
    }

    /**
     * Only the Asset Control Officer can request a callback when a PO is pending receipt.
     */
    public function callback(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->role === UserRole::AssetControlOfficer
            && $purchaseOrder->status === PurchaseOrderStatus::PendingReceipt;
    }
}
