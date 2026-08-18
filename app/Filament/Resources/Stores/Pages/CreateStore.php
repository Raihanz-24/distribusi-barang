<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Enums\VerificationStatus;
use App\Filament\Resources\Stores\StoreResource;
use App\Services\IndonesianRegionResolver;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = app(IndonesianRegionResolver::class)->namesFromCodes($data);
        $data['created_by'] = auth()->id();
        $data['verification_status'] = auth()->user()->isAdmin()
            ? ($data['verification_status'] ?? VerificationStatus::Pending->value)
            : VerificationStatus::Pending->value;

        return $data;
    }
}
