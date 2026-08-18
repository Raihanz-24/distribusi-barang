<?php

namespace App\Models;

use App\Enums\StoreStatus;
use App\Enums\VerificationStatus;
use App\Services\DocumentNumber;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_accuracy' => 'decimal:2',
            'status' => StoreStatus::class,
            'verification_status' => VerificationStatus::class,
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Store $store): void {
            $store->code ??= self::nextCode();
            $store->created_by ??= Auth::id();
        });

        static::updating(function (Store $store): void {
            if (! $store->isDirty(['latitude', 'longitude'])) {
                return;
            }

            $store->locationHistories()->create([
                'old_latitude' => $store->getOriginal('latitude'),
                'old_longitude' => $store->getOriginal('longitude'),
                'new_latitude' => $store->latitude,
                'new_longitude' => $store->longitude,
                'location_accuracy' => $store->location_accuracy,
                'changed_by' => Auth::id() ?? $store->created_by,
            ]);
        });
    }

    public static function nextCode(): string
    {
        return app(DocumentNumber::class)->next('store', 'TKO-', 6);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function locationHistories()
    {
        return $this->hasMany(StoreLocationHistory::class);
    }

    public function routeAreas()
    {
        return $this->belongsToMany(RouteArea::class)->withPivot(['priority', 'preferred_visit_day', 'notes'])->withTimestamps();
    }

    public function dailyRouteStores()
    {
        return $this->hasMany(DailyRouteStore::class);
    }

    public function visits()
    {
        return $this->hasMany(StoreVisit::class);
    }

    public function accountEntries()
    {
        return $this->hasMany(StoreAccountEntry::class);
    }

    public function getOutstandingDebtAttribute(): int
    {
        return max(0, (int) $this->accountEntries()->sum('amount'));
    }

    public function latestVisit()
    {
        return $this->hasOne(StoreVisit::class)->whereNull('reversed_at')->latestOfMany('arrived_at');
    }
}
