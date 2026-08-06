<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RevenueCenter;
use App\Models\User;

class RevenueCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }

    public function view(User $user, RevenueCenter $revenueCenter): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }

    public function update(User $user, RevenueCenter $revenueCenter): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }

    public function delete(User $user, RevenueCenter $revenueCenter): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }
}
