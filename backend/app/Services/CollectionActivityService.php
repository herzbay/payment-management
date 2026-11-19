<?php

namespace App\Services;

use App\Models\CollectionActivity;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionActivityService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected AccountReceivableService $accountReceivableService,
    ) {
    }

    /**
     * Get a paginated list of collection activities.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = CollectionActivity::query()
            ->with([
                'invoice',
                'customer',
                'assignedTo',
                'createdBy',
            ]);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('activity_type', 'ilike', "%{$search}%")
                    ->orWhere('outcome', 'ilike', "%{$search}%")
                    ->orWhereHas(
                        'invoice',
                        fn (Builder $invoiceQuery) => $invoiceQuery
                            ->where('invoice_number', 'ilike', "%{$search}%")
                    )
                    ->orWhereHas(
                        'customer',
                        fn (Builder $customerQuery) => $customerQuery
                            ->where('company_name', 'ilike', "%{$search}%")
                    );
            });
        }

        if (!empty($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['activity_type'])) {
            $query->where('activity_type', $filters['activity_type']);
        }

        if (!empty($filters['activity_date_from'])) {
            $query->whereDate(
                'activity_date',
                '>=',
                $filters['activity_date_from']
            );
        }

        if (!empty($filters['activity_date_to'])) {
            $query->whereDate(
                'activity_date',
                '<=',
                $filters['activity_date_to']
            );
        }

        if (isset($filters['has_follow_up'])) {
            if (filter_var($filters['has_follow_up'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('next_follow_up_at');
            } else {
                $query->whereNull('next_follow_up_at');
            }
        }

        return $query
            ->latest('activity_date')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get a collection activity detail.
     */
    public function getById(int $id): CollectionActivity
    {
        return CollectionActivity::query()
            ->with([
                'invoice.customer',
                'customer',
                'assignedTo',
                'createdBy',
            ])
            ->findOrFail($id);
    }

    /**
     * Create a collection activity for an invoice with outstanding balance.
     */
    public function create(array $data): CollectionActivity
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($data['invoice_id']);

            // Ensure the receivable reflects the latest verified payments.
            $receivable = $this->accountReceivableService
                ->syncFromInvoice($invoice);

            if ((float) $receivable->outstanding_amount <= 0) {
                throw ValidationException::withMessages([
                    'invoice_id' => [
                        'Aktivitas penagihan hanya dapat dibuat untuk invoice yang masih memiliki sisa piutang.',
                    ],
                ]);
            }

            $activity = CollectionActivity::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => Auth::id(),
                'activity_type' => $data['activity_type'],
                'activity_date' => $data['activity_date'] ?? now(),
                'contact_method' => $data['contact_method'] ?? null,
                'outcome' => $data['outcome'] ?? null,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $activity->load([
                'invoice',
                'customer',
                'assignedTo',
                'createdBy',
            ]);

            $this->auditLogService->log(
                event: 'created',
                module: 'accounts_receivable',
                description: "Aktivitas penagihan untuk invoice {$invoice->invoice_number} berhasil dicatat.",
                auditable: $activity,
                newValues: $activity->toArray(),
                properties: [
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'assigned_to' => $activity->assigned_to,
                    'activity_type' => $activity->activity_type,
                    'outstanding_amount' => $receivable->outstanding_amount,
                ],
            );

            return $activity;
        });
    }

    /**
     * Update an existing collection activity.
     *
     * Invoice and customer references remain immutable.
     */
    public function update(int $id, array $data): CollectionActivity
    {
        return DB::transaction(function () use ($id, $data) {
            $activity = CollectionActivity::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $oldValues = $activity->only([
                'assigned_to',
                'activity_type',
                'activity_date',
                'contact_method',
                'outcome',
                'next_follow_up_at',
                'notes',
            ]);

            $activity->update([
                'assigned_to' => array_key_exists('assigned_to', $data)? $data['assigned_to']: $activity->assigned_to,
                'activity_type' => $data['activity_type'] ?? $activity->activity_type,
                'activity_date' => $data['activity_date'] ?? $activity->activity_date,
                'contact_method' => array_key_exists('contact_method', $data)
                    ? $data['contact_method']
                    : $activity->contact_method,
                'outcome' => array_key_exists('outcome', $data)
                    ? $data['outcome']
                    : $activity->outcome,
                'next_follow_up_at' => array_key_exists('next_follow_up_at', $data)
                    ? $data['next_follow_up_at']
                    : $activity->next_follow_up_at,
                'notes' => array_key_exists('notes', $data)
                    ? $data['notes']
                    : $activity->notes,
            ]);

            $activity->load([
                'invoice',
                'customer',
                'assignedTo',
                'createdBy',
            ]);

            $this->auditLogService->log(
                event: 'updated',
                module: 'accounts_receivable',
                description: "Aktivitas penagihan untuk invoice {$activity->invoice->invoice_number} diperbarui.",
                auditable: $activity,
                oldValues: $oldValues,
                newValues: $activity->only([
                    'assigned_to',
                    'activity_type',
                    'activity_date',
                    'contact_method',
                    'outcome',
                    'next_follow_up_at',
                    'notes',
                ]),
            );

            return $activity;
        });
    }
}
