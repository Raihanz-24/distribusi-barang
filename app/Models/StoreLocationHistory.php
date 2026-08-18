<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreLocationHistory extends Model
{
    protected $guarded = [];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
