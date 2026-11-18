<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    /**
     * Get paginated customers with optional filters.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return Customer::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('customer_code', 'ilike', "%{$search}%")
                        ->orWhere('company_name', 'ilike', "%{$search}%")
                        ->orWhere('contact_person', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when(
                array_key_exists('is_active', $filters),
                fn ($query) => $query->where(
                    'is_active',
                    filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->orderBy('company_name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Get customer detail.
     */
    public function getById(int $id): Customer
    {
        return Customer::query()->findOrFail($id);
    }

    /**
     * Create a new customer.
     */
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create($data);

            $this->auditLog->created(
                model: $customer,
                module: 'customers',
                description: "Customer {$customer->company_name} was created."
            );

            return $customer->refresh();
        });
    }

    /**
     * Update an existing customer.
     */
    public function update(int $id, array $data): Customer
    {
        return DB::transaction(function () use ($id, $data) {
            $customer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $oldValues = $customer->getAttributes();
            $customer->fill($data);

            $newValues = [];

            foreach ($customer->getDirty() as $key => $value) {
                $newValues[$key] = $value;
            }

            if (empty($newValues)) {
                return $customer;
            }

            $customer->save();

            $this->auditLog->updated(
                model: $customer,
                module: 'customers',
                description: "Customer {$customer->company_name} was updated.",
                oldValues: array_intersect_key($oldValues, $newValues),
                newValues: $newValues
            );

            return $customer->refresh();
        });
    }

    /**
     * Activate or deactivate a customer.
     */
    public function setActive(int $id, bool $isActive): Customer
    {
        return DB::transaction(function () use ($id, $isActive) {
            $customer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ((bool) $customer->is_active === $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => [
                        $isActive
                            ? 'Customer is already active.'
                            : 'Customer is already inactive.',
                    ],
                ]);
            }

            $oldValues = [
                'is_active' => $customer->is_active,
            ];

            $customer->is_active = $isActive;
            $customer->save();

            $this->auditLog->updated(
                model: $customer,
                module: 'customers',
                description: $isActive
                    ? "Customer {$customer->company_name} was activated."
                    : "Customer {$customer->company_name} was deactivated.",
                oldValues: $oldValues,
                newValues: [
                    'is_active' => $customer->is_active,
                ]
            );

            return $customer->refresh();
        });
    }
}
