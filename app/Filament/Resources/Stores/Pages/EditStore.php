<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Enums\VerificationStatus;
use App\Filament\Resources\Stores\StoreResource;
use App\Services\IndonesianRegionResolver;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify')
                ->label('Verifikasi toko')
                ->visible(fn () => auth()->user()->can('verify', $this->record))
                ->requiresConfirmation()
                ->action(fn () => $this->record->update([
                    'verification_status' => VerificationStatus::Verified,
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                ])),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(IndonesianRegionResolver::class)->namesFromCodes($data);
    }
}
