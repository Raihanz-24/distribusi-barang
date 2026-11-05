<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Services\AuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function beforeCreate(): void
    {
        if (($this->data['role'] ?? null) === UserRole::Developer->value && ! auth()->user()->isDeveloper()) {
            throw ValidationException::withMessages(['data.role' => 'Role Developer hanya dapat dibuat oleh Developer.']);
        }
    }

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
