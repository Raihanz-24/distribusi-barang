<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreAccountEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function visit()
    {
        return $this->belongsTo(StoreVisit::class, 'store_visit_id');
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reversedEntry()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
