<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    /**
     * Get paginated services with optional filters.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return Service::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('service_code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('category', 'ilike', "%{$search}%");
                });
            })
            ->when(
                array_key_exists('is_active', $filters),
                fn ($query) => $query->where(
                    'is_active',
                    filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Get service detail.
     */
    public function getById(int $id): Service
    {
        return Service::query()->findOrFail($id);
    }

    /**
     * Create a new service.
     */
    public function create(array $data): Service
    {
        return DB::transaction(function () use ($data) {
            $service = Service::create($data);

            $this->auditLog->created(
                model: $service,
                module: 'services',
                description: "Service {$service->name} was created."
            );

            return $service->refresh();
        });
    }

    /**
     * Update an existing service.
     */
    public function update(int $id, array $data): Service
    {
        return DB::transaction(function () use ($id, $data) {
            $service = Service::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $oldValues = $service->getAttributes();

            $service->fill($data);

            $newValues = $service->getDirty();

            if (empty($newValues)) {
                return $service;
            }

            $service->save();

            $this->auditLog->updated(
                model: $service,
                module: 'services',
                description: "Service {$service->name} was updated.",
                oldValues: array_intersect_key($oldValues, $newValues),
                newValues: $newValues
            );

            return $service->refresh();
        });
    }

    /**
     * Activate or deactivate a service.
     */
    public function setActive(int $id, bool $isActive): Service
    {
        return DB::transaction(function () use ($id, $isActive) {
            $service = Service::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ((bool) $service->is_active === $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => [
                        $isActive
                            ? 'Service is already active.'
                            : 'Service is already inactive.',
                    ],
                ]);
            }

            $oldValues = [
                'is_active' => $service->is_active,
            ];

            $service->is_active = $isActive;
            $service->save();

            $this->auditLog->updated(
                model: $service,
                module: 'services',
                description: $isActive
                    ? "Service {$service->name} was activated."
                    : "Service {$service->name} was deactivated.",
                oldValues: $oldValues,
                newValues: [
                    'is_active' => $service->is_active,
                ]
            );

            return $service->refresh();
        });
    }
}
