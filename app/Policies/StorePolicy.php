<?php

namespace App\Policies;

use App\Enums\VerificationStatus;
use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Store $store): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $store->created_by === $user->id
            || $store->dailyRouteStores()->whereHas(
                'dailyRoute',
                fn ($query) => $query->where('sales_id', $user->id)
            )->exists();
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Store $store): bool
    {
        return $user->isAdmin()
            || ($store->created_by === $user->id && $store->verification_status === VerificationStatus::Pending);
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }

    public function verify(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }
}
