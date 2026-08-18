<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\AuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        app(AuditLogger::class)->record(
            'user.created',
            $this->record,
            auth()->user(),
            newValues: $this->record->only(['name', 'username', 'role', 'is_active', 'phone']),
            description: 'Akun pengguna dibuat.',
        );
    }
}
