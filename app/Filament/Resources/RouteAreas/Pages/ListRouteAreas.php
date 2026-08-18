<?php

namespace App\Filament\Resources\RouteAreas\Pages;

use App\Filament\Resources\RouteAreas\RouteAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRouteAreas extends ListRecords
{
    protected static string $resource = RouteAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
