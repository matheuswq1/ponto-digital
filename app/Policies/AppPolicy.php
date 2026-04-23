<?php

namespace App\Policies;

use App\Models\User;

class AppPolicy
{
    public function manageCompanies(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function manageEmployees(User $user): bool
    {
        return in_array($user->role, ['admin', 'gestor']);
    }

    public function approveEditRequests(User $user): bool
    {
        return in_array($user->role, ['admin', 'gestor']);
    }
}
