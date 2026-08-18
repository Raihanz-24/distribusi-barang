<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\UpdateProductPrice;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $currentPrice = $this->record->currentPrice;

        $data['current_price_per_renteng'] = $currentPrice?->price_per_renteng;

        return $data;
    }

    protected function afterSave(): void
    {
        app(UpdateProductPrice::class)->execute(
            $this->record,
            (int) $this->data['current_price_per_renteng'],
            auth()->user(),
        );

        $this->record->unsetRelation('currentPrice');
    }
}
