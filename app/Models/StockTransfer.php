<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Services\DocumentNumber;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'transfer_type' => StockTransferType::class,
            'issued_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockTransfer $transfer): void {
            $transfer->number ??= app(DocumentNumber::class)->next(
                'stock_transfer',
                'TRF-'.now()->format('Ymd').'-',
                5,
            );
        });
    }

    public function sourceLocation()
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function dailyRoute()
    {
        return $this->belongsTo(DailyRoute::class);
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function pickupTransfer()
    {
        return $this->belongsTo(self::class, 'pickup_transfer_id');
    }

    public function returnTransfer()
    {
        return $this->hasOne(self::class, 'pickup_transfer_id');
    }

    public function pickupSettlement()
    {
        return $this->hasOne(DailySalesSettlement::class, 'pickup_transfer_id');
    }

    public function returnSettlement()
    {
        return $this->hasOne(DailySalesSettlement::class, 'return_transfer_id');
    }

    public function displayStatusLabel(): string
    {
        if ($this->transfer_type === StockTransferType::IssueToSales
            && $this->status === StockTransferStatus::Received) {
            $hasReturn = $this->relationLoaded('returnTransfer')
                ? $this->returnTransfer !== null
                : $this->returnTransfer()->exists();

            if (! $hasReturn) {
                return 'Dalam perjalanan';
            }
        }

        return $this->status->label();
    }
}
