<?php

namespace App\Policies;

use App\Models\DailyRoute;
use App\Models\User;

class DailyRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, DailyRoute $dailyRoute): bool
    {
        return $user->isAdmin() || $dailyRoute->sales_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DailyRoute $dailyRoute): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, DailyRoute $dailyRoute): bool
    {
        return $user->isAdmin() && $dailyRoute->status->value === 'draft';
    }
}
