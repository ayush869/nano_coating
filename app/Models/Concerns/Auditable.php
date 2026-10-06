<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Writes every create/update to audit_logs (rate card, rules, company profile).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::create([
                'user_id' => Auth::id(),
                'table_name' => $model->getTable(),
                'record_id' => $model->getKey(),
                'action' => 'created',
                'old_json' => null,
                'new_json' => collect($model->getAttributes())->except(['created_at', 'updated_at'])->all(),
            ]);
        });

        static::updated(function ($model) {
            $changes = collect($model->getChanges())->except(['created_at', 'updated_at']);
            if ($changes->isEmpty()) {
                return;
            }
            AuditLog::create([
                'user_id' => Auth::id(),
                'table_name' => $model->getTable(),
                'record_id' => $model->getKey(),
                'action' => 'updated',
                'old_json' => $changes->keys()->mapWithKeys(fn ($k) => [$k => $model->getOriginal($k)])->all(),
                'new_json' => $changes->all(),
            ]);
        });
    }
}
