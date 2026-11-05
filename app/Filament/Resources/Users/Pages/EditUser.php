<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private array $auditBefore = [];

    protected function beforeSave(): void
    {
        if (($this->data['role'] ?? null) === UserRole::Developer->value && ! auth()->user()->isDeveloper()) {
            throw ValidationException::withMessages(['data.role' => 'Role Developer hanya dapat dikelola oleh Developer.']);
        }

        $this->auditBefore = $this->record->only(['name', 'username', 'role', 'is_active', 'phone', 'address']);

        $willRemainPrivileged = in_array(($this->data['role'] ?? null), [
            UserRole::Developer->value,
            UserRole::Admin->value,
        ], true)
            && (bool) ($this->data['is_active'] ?? false);

        if ($this->record->is(auth()->user()) && ! $willRemainPrivileged) {
            throw ValidationException::withMessages([
                'data.role' => 'Anda tidak dapat menonaktifkan atau menurunkan peran akun sendiri.',
            ]);
        }

        if ($this->record->role === UserRole::Admin && ! $willRemainPrivileged) {
            $otherActiveAdmins = User::query()
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
