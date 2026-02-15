<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            self::logAudit('created', $model);
        });

        static::updated(function (Model $model) {
            self::logAudit('updated', $model);
        });

        static::deleted(function (Model $model) {
            self::logAudit('deleted', $model);
        });
    }

    protected static function logAudit(string $action, Model $model)
    {
        // Skip logging if running from console (seeding/migrations) unless explicitly needed
        // But for now we want to log everything.

        $oldValues = null;
        $newValues = null;

        if ($action === 'updated') {
            $changes = $model->getChanges();
            // Ignore timestamps unless that's the only change
            $ignore = ['updated_at'];
            $changes = array_diff_key($changes, array_flip($ignore));

            if (empty($changes)) {
                return;
            }

            $original = $model->getOriginal();

            $newValues = $changes;
            $oldValues = [];
            foreach ($changes as $key => $value) {
                $oldValues[$key] = $original[$key] ?? null;
            }
        } elseif ($action === 'created') {
            $newValues = $model->getAttributes();
        } elseif ($action === 'deleted') {
            $oldValues = $model->getAttributes();
        }

        AuditLog::create([
            'user_id' => Auth::id(), // Nullable if system action
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
