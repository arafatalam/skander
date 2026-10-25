<?php

namespace App\Policies;

use App\Models\User;

class CorrectionSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }
}
