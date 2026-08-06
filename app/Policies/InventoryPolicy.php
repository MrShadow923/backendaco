<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::AssetControlOfficer,
            UserRole::FinanceOfficer,
            UserRole::GeneralManager,
        ]);
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $this->viewAny($user);
    }
}
