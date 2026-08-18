<?php

namespace App\Filament\Resources\RouteAreas\Pages;

use App\Filament\Resources\RouteAreas\RouteAreaResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditRouteArea extends EditRecord
{
    protected static string $resource = RouteAreaResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
