<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StockRelease;
use App\Models\User;

class StockReleasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Purchaser;
    }

    public function view(User $user, StockRelease $stockRelease): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }

    public function update(User $user, StockRelease $stockRelease): bool
    {
        return $user->role === UserRole::AssetControlOfficer
            && $stockRelease->canEdit();
    }

    public function release(User $user): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }
}
