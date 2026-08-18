<?php

namespace App\Models;

use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use Database\Factories\DailyRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyRoute extends Model
{
    /** @use HasFactory<DailyRouteFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'route_date' => 'date',
            'status' => DailyRouteStatus::class,
            'optimization_status' => OptimizationStatus::class,
            'route_geometry' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function area()
    {
        return $this->belongsTo(RouteArea::class, 'route_area_id');
    }

    public function areas()
    {
        return $this->belongsToMany(RouteArea::class, 'daily_route_area')
            ->withPivot('area_order')
            ->withTimestamps()
            ->orderByPivot('area_order');
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function routeStores()
    {
        return $this->hasMany(DailyRouteStore::class)->orderByRaw('COALESCE(optimized_order, planned_order, 999999)');
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'daily_route_stores')->withPivot([
            'planned_order', 'optimized_order', 'actual_order', 'priority', 'visit_status',
        ])->withTimestamps();
    }

    public function optimizationRuns()
    {
        return $this->hasMany(RouteOptimizationRun::class);
    }

    public function visits()
    {
        return $this->hasMany(StoreVisit::class)->whereNull('reversed_at');
    }

    public function settlement()
    {
        return $this->hasOne(DailySalesSettlement::class);
    }
}
