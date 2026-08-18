<?php

namespace App\Policies;

use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->isAdmin() || $transfer->sales_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $user->isAdmin()
            || (
                $transfer->transfer_type === StockTransferType::IssueToSales
                && $transfer->sales_id === $user->id
                && $transfer->status === StockTransferStatus::Issued
            );
    }

    public function delete(User $user, StockTransfer $transfer): bool
    {
        return false;
    }
}
