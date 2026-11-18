<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
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
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'http_method' => $request->method(),
            'properties' => $properties,
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
}
