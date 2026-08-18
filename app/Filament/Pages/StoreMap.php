<?php

namespace App\Filament\Pages;

use App\Models\RouteArea;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class StoreMap extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Peta Toko';

    protected static ?string $title = 'Peta Internal Toko';

    protected static string|\UnitEnum|null $navigationGroup = 'Peta & Rute';

    protected string $view = 'filament.pages.store-map';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getViewData(): array
    {
        return [
            'mapConfig' => [
                'styleUrl' => config('maps.style_url'),
                'latitude' => config('maps.default_latitude'),
                'longitude' => config('maps.default_longitude'),
                'zoom' => config('maps.default_zoom'),
                'dataUrl' => route('internal.store-map-data'),
                'areas' => RouteArea::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            ],
        ];
    }
}
