<?php

namespace App\Traits;

use App\Models\AuditLog;

trait HasAuditLog
{
    protected static function bootHasAuditLog(): void
    {
        static::created(function ($model) {
            static::writeLog('created', $model, null, $model->toArray());
        });

        static::updated(function ($model) {
            static::writeLog('updated', $model, $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function ($model) {
            static::writeLog('deleted', $model, $model->toArray(), null);
        });
    }

    protected static function writeLog(string $action, $model, ?array $old, ?array $new): void
    {
        try {
            AuditLog::create([
                'user_id'     => auth()->id(),
                'action'      => $action,
                'module'      => class_basename($model),
                'model_type'  => get_class($model),
                'model_id'    => $model->getKey(),
                'old_values'  => $old ? static::filterSensitive($old) : null,
                'new_values'  => $new ? static::filterSensitive($new) : null,
                'ip_address'  => request()->ip(),
            ]);
        } catch (\Throwable) {
            // Never let audit log failure break the main flow
        }
    }

    protected static function filterSensitive(array $data): array
    {
        $sensitive = ['password', 'token', 'secret', 'api_key', 'aadhaar_number', 'account_number'];
        foreach ($sensitive as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***';
            }
        }
        return $data;
    }
}
