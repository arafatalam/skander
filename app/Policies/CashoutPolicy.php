<?php

namespace App\Policies;

use App\Models\Cashout;
use App\Models\User;

class CashoutPolicy
{
    public function viewCorrections(User $user, Cashout $cashout): bool
    {
        return $user->role === 'admin';
    }

    public function correct(User $user, Cashout $cashout): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cashout $cashout): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cashout $cashout): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cashout $cashout): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Cashout $cashout): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Cashout $cashout): bool
    {
        return false;
    }
}
