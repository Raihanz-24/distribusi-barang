<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;

class DailyRouteStore extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['visit_status' => VisitStatus::class];
    }

    public function dailyRoute()
    {
        return $this->belongsTo(DailyRoute::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function latestVisit()
    {
        return $this->hasOne(StoreVisit::class)->whereNull('reversed_at')->latestOfMany('completed_at');
    }
}
