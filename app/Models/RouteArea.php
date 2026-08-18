<?php

namespace App\Models;

use App\Services\DocumentNumber;
use Database\Factories\RouteAreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteArea extends Model
{
    /** @use HasFactory<RouteAreaFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (RouteArea $area): void {
            $area->code ??= self::nextCode();
        });
    }

    public static function nextCode(): string
    {
        return app(DocumentNumber::class)->next('route_area', 'AREA-', 4);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class)->withPivot(['priority', 'preferred_visit_day', 'notes'])->withTimestamps();
    }

    public function dailyRoutes()
    {
        return $this->hasMany(DailyRoute::class);
    }

    public function assignedDailyRoutes()
    {
        return $this->belongsToMany(DailyRoute::class, 'daily_route_area')
            ->withPivot('area_order')
            ->withTimestamps();
    }
}
