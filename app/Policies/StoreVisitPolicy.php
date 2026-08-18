<?php

namespace App\Policies;

use App\Models\StoreVisit;
use App\Models\User;

class StoreVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, StoreVisit $visit): bool
    {
        return $user->isAdmin() || $visit->sales_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isSales() && $user->is_active;
    }

    public function update(User $user, StoreVisit $visit): bool
    {
        return $user->isAdmin() || ($visit->sales_id === $user->id && $visit->completed_at === null);
    }
}
