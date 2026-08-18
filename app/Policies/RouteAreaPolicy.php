<?php

namespace App\Policies;

use App\Models\RouteArea;
use App\Models\User;

class RouteAreaPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, RouteArea $routeArea): bool
    {
        return false;
    }
}
