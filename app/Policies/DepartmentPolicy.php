<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Purchaser;
    }

    public function view(User $user, Department $department): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }

    public function update(User $user, Department $department): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->role === UserRole::AssetControlOfficer;
    }
}
