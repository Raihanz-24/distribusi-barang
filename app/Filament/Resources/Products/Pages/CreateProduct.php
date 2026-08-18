<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\UpdateProductPrice;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        app(UpdateProductPrice::class)->execute(
            $this->record,
            (int) $this->data['current_price_per_renteng'],
            auth()->user(),
        );
    }
}
