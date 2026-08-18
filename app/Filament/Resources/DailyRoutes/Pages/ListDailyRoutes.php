<?php

namespace App\Filament\Resources\DailyRoutes\Pages;

use App\Filament\Resources\DailyRoutes\DailyRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDailyRoutes extends ListRecords
{
    protected static string $resource = DailyRouteResource::class;

    protected function getHeaderActions(): array
    {
        return auth()->user()->isAdmin() ? [CreateAction::make()] : [];
    }
}
