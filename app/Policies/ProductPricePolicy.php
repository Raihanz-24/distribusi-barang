<?php

namespace App\Policies;

use App\Models\ProductPrice;
use App\Models\User;

class ProductPricePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ProductPrice $productPrice): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProductPrice $productPrice): bool
    {
        return false;
    }

    public function delete(User $user, ProductPrice $productPrice): bool
    {
        return false;
    }
}
