<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\AuditLogger;
use App\Enums\UserRole;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private array $auditBefore = [];

    protected function beforeSave(): void
    {
        $this->auditBefore = $this->record->only(['name', 'username', 'role', 'is_active', 'phone', 'address']);

        $willRemainAdmin = ($this->data['role'] ?? null) === UserRole::Admin->value
            && (bool) ($this->data['is_active'] ?? false);

        if ($this->record->is(auth()->user()) && ! $willRemainAdmin) {
            throw ValidationException::withMessages([
                'data.role' => 'Anda tidak dapat menonaktifkan atau menurunkan peran akun sendiri.',
            ]);
        }

        if ($this->record->isAdmin() && ! $willRemainAdmin) {
            $otherActiveAdmins = \App\Models\User::query()
                ->whereKeyNot($this->record->id)
                ->where('role', UserRole::Admin)
                ->where('is_active', true)
                ->exists();

            if (! $otherActiveAdmins) {
                throw ValidationException::withMessages([
                    'data.role' => 'Minimal satu Admin aktif harus tetap tersedia.',
                ]);
            }
        }
    }

    protected function afterSave(): void
    {
        app(AuditLogger::class)->record(
            'user.updated',
            $this->record,
            auth()->user(),
            oldValues: $this->auditBefore,
            newValues: $this->record->only(['name', 'username', 'role', 'is_active', 'phone', 'address']),
            description: 'Data atau akses pengguna diperbarui.',
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
