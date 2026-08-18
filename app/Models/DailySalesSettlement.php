<?php

namespace App\Models;

use App\Enums\SalesSettlementStatus;
use Illuminate\Database\Eloquent\Model;

class DailySalesSettlement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'status' => SalesSettlementStatus::class,
            'total_stores' => 'integer',
            'total_sold_pieces' => 'integer',
            'total_deposited_pieces' => 'integer',
            'total_withdrawn_pieces' => 'integer',
            'stock_shortage_pieces' => 'integer',
            'total_collection' => 'integer',
            'actual_cash_received' => 'integer',
            'cash_difference' => 'integer',
            'equivalent_renteng' => 'integer',
            'sales_fee' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function pickupTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'pickup_transfer_id');
    }

    public function returnTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'return_transfer_id');
    }

    public function dailyRoute()
    {
        return $this->belongsTo(DailyRoute::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getVerifiedCollectionAttribute(): int
    {
        return (int) ($this->actual_cash_received ?? $this->total_collection);
    }
}
