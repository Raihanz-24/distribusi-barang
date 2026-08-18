<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function record(
        string $action,
        ?Model $subject = null,
        ?User $actor = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor?->id ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'description' => $description,
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500) ?: null,
            'occurred_at' => now(),
        ]);
    }
}
