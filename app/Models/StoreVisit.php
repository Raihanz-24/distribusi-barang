<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;

class StoreVisit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'visit_status' => VisitStatus::class,
            'amount_due' => 'integer',
            'amount_paid' => 'integer',
            'debt_amount' => 'integer',
            'opening_debt' => 'integer',
            'current_payment_amount' => 'integer',
            'debt_payment_amount' => 'integer',
            'closing_debt' => 'integer',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function dailyRoute()
    {
        return $this->belongsTo(DailyRoute::class);
    }

    public function dailyRouteStore()
    {
        return $this->belongsTo(DailyRouteStore::class);
    }

    public function items()
    {
        return $this->hasMany(StoreVisitItem::class);
    }

    public function accountEntries()
    {
        return $this->hasMany(StoreAccountEntry::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function reverser()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
