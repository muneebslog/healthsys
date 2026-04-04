<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Procedure;
use App\Models\User;

class ProcedurePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Staff, UserRole::Admin], true)
            || $user->role === UserRole::Doctor;
    }

    public function view(User $user, Procedure $procedure): bool
    {
        if (in_array($user->role, [UserRole::Staff, UserRole::Admin], true)) {
            return true;
        }

        if ($user->role === UserRole::Doctor && $user->doctor?->id === $procedure->doctor_id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Staff, UserRole::Admin], true);
    }

    public function update(User $user, Procedure $procedure): bool
    {
        return in_array($user->role, [UserRole::Staff, UserRole::Admin], true);
    }
}
