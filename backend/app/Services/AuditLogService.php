<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Sensitive attributes that must never be stored in audit logs.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'api_token',
        'secret',
        'client_secret',
    ];

    /**
     * Record an activity in the audit log.
     */
    public function log(
        string $event,
        string $module,
        string $description,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $properties = [],
        ?Request $request = null
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'module' => $module,
            'description' => $description,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'http_method' => $request->method(),
            'properties' => $this->sanitize($properties) ?? [],
        ]);
    }

    /**
     * Record a newly created model.
     */
    public function created(
        Model $model,
        string $module,
        string $description,
        array $properties = []
    ): AuditLog {
        return $this->log(
            event: 'created',
            module: $module,
            description: $description,
            auditable: $model,
            newValues: $model->getAttributes(),
            properties: $properties
        );
    }

    /**
     * Record updated model attributes.
     */
    public function updated(
        Model $model,
        string $module,
        string $description,
        array $oldValues,
        array $newValues,
        array $properties = []
    ): AuditLog {
        return $this->log(
            event: 'updated',
            module: $module,
            description: $description,
            auditable: $model,
            oldValues: $oldValues,
            newValues: $newValues,
            properties: $properties
        );
    }

    /**
     * Record a deleted model.
     */
    public function deleted(
        Model $model,
        string $module,
        string $description,
        array $properties = []
    ): AuditLog {
        return $this->log(
            event: 'deleted',
            module: $module,
            description: $description,
            auditable: $model,
            oldValues: $model->getAttributes(),
            properties: $properties
        );
    }

    /**
     * Remove sensitive values recursively before storing audit data.
     */
    private function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sanitized = [];

        foreach ($data as $key => $value) {
            if (
                is_string($key)
                && in_array(strtolower($key), self::SENSITIVE_KEYS, true)
            ) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
