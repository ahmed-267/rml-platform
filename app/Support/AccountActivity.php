<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

class AccountActivity
{
    /**
     * @return list<array{id: int, action: string, actor: string|null, created_at: string|null}>
     */
    public static function recentForUser(User $user, int $limit = 5): array
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->where('entity_type', User::class)
            ->where('entity_id', $user->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
