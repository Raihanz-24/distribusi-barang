<?php

namespace App\Filament\Resources\RouteAreas\Pages;

use App\Filament\Resources\RouteAreas\RouteAreaResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateRouteArea extends CreateRecord
{
    protected static string $resource = RouteAreaResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
