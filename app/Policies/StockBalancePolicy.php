<?php

namespace App\Policies;

use App\Models\StockBalance;
use App\Models\User;

class StockBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, StockBalance $balance): bool
    {
        return $user->isAdmin()
            || ($balance->location->type->value === 'sales' && $balance->location->reference_id === $user->id);
    }
}
