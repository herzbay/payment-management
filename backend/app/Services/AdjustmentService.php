<?php

namespace App\Services;

use App\Models\Adjustment;
use App\Models\AdjustmentApproval;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustmentService
{
    private const TYPES = [
        'credit_note',
        'debit_note',
        'discount',
        'write_off',
        'correction',
    ];

    private const DIRECTIONS = [
        'decrease',
        'increase',
    ];

    public function __construct(
        private DocumentNumberingService $documentNumberingService,
        private AuditLogService $auditLogService,
    ) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return Adjustment::query()
            ->with([
                'invoice.customer',
                'customer',
                'createdBy',
                'appliedBy',
                'approvals.approver',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery
                        ->where('adjustment_number', 'ilike', "%{$search}%")
                        ->orWhere('reason', 'ilike', "%{$search}%")
                        ->orWhere('reference_number', 'ilike', "%{$search}%")
                        ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($search) {
                            $invoiceQuery->where(
                                'invoice_number',
                                'ilike',
                                "%{$search}%"
                            );
                        })
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                            $customerQuery->where(
                                'company_name',
                                'ilike',
                                "%{$search}%"
                            );
                        });
                });
            })
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['adjustment_type'] ?? null,
                fn (Builder $query, string $type) => $query->where('adjustment_type', $type)
            )
            ->when(
                $filters['direction'] ?? null,
                fn (Builder $query, string $direction) => $query->where('direction', $direction)
            )
            ->when(
                $filters['invoice_id'] ?? null,
                fn (Builder $query, int $invoiceId) => $query->where('invoice_id', $invoiceId)
            )
            ->when(
                $filters['customer_id'] ?? null,
                fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId)
            )
            ->when(
                $filters['adjustment_date_from'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('adjustment_date', '>=', $date)
            )
            ->when(
                $filters['adjustment_date_to'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('adjustment_date', '<=', $date)
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id): Adjustment
    {
        return Adjustment::query()
            ->with([
                'invoice.customer',
                'invoice.items.service',
                'customer',
                'createdBy',
                'appliedBy',
                'approvals.approver',
            ])
            ->findOrFail($id);
    }

    /**
     * Membuat pengajuan adjustment dan menetapkan approver level pertama.
     */
    public function create(array $data): Adjustment
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($data['invoice_id']);

            if (! in_array($data['adjustment_type'], self::TYPES, true)) {
                throw ValidationException::withMessages([
                    'adjustment_type' => ['Jenis adjustment tidak valid.'],
                ]);
            }

            if (! in_array($data['direction'], self::DIRECTIONS, true)) {
                throw ValidationException::withMessages([
                    'direction' => ['Arah adjustment tidak valid.'],
                ]);
            }

            $amountMinor = $this->toMinorUnits($data['amount']);

            if ($amountMinor <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Jumlah adjustment harus lebih besar dari nol.'],
                ]);
            }

            $approver = User::query()
                ->with('role')
                ->findOrFail($data['approver_id']);

            if (! in_array(
                $approver->role?->slug,
                ['super-admin', 'finance-manager'],
                true
            )) {
                throw ValidationException::withMessages([
                    'approver_id' => [
                        'Approver harus memiliki role Super Admin atau Finance Manager.',
                    ],
                ]);
            }

            if ((int) $approver->id === (int) Auth::id()) {
                throw ValidationException::withMessages([
                    'approver_id' => [
                        'Pengaju tidak boleh menyetujui pengajuannya sendiri.',
                    ],
                ]);
            }

            $adjustment = Adjustment::create([
                'adjustment_number' => $this->documentNumberingService->generate('adjustment'),
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'created_by' => Auth::id(),
                'adjustment_type' => $data['adjustment_type'],
                'direction' => $data['direction'],
                'currency' => $invoice->currency,
                'amount' => $this->fromMinorUnits($amountMinor),
                'reference_number' => $data['reference_number'] ?? null,
                'adjustment_date' => $data['adjustment_date'] ?? now()->toDateString(),
                'status' => 'pending_approval',
                'reason' => $data['reason'],
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $adjustment->approvals()->create([
                'approver_id' => $approver->id,
                'approval_level' => 1,
                'status' => 'pending',
                'assigned_at' => now(),
            ]);

            $adjustment->load([
                'invoice.customer',
                'customer',
                'createdBy',
                'approvals.approver',
            ]);

            $this->auditLogService->created(
                module: 'adjustments',
                auditable: $adjustment,
                description: "Pengajuan adjustment {$adjustment->adjustment_number} dibuat.",
                properties: [
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'adjustment_type' => $adjustment->adjustment_type,
                    'direction' => $adjustment->direction,
                    'amount' => $adjustment->amount,
                    'approver_id' => $approver->id,
                ],
            );

            return $adjustment;
        });
    }

    public function approve(int $id, ?string $comments = null): Adjustment
    {
        return $this->decide($id, 'approved', $comments);
    }

    public function reject(int $id, string $reason): Adjustment
    {
        return $this->decide($id, 'rejected', $reason);
    }

    private function decide(
        int $id,
        string $decision,
        ?string $comments,
    ): Adjustment {
        return DB::transaction(function () use ($id, $decision, $comments) {
            $adjustment = Adjustment::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($adjustment->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'status' => [
                        'Hanya adjustment berstatus pending approval yang dapat diputuskan.',
                    ],
                ]);
            }

            $approval = AdjustmentApproval::query()
                ->where('adjustment_id', $adjustment->id)
                ->where('approval_level', 1)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $approval) {
                throw ValidationException::withMessages([
                    'approval' => ['Data persetujuan adjustment tidak ditemukan.'],
                ]);
            }

            if ((int) $approval->approver_id !== (int) Auth::id()) {
                throw ValidationException::withMessages([
                    'approver_id' => [
                        'Anda tidak memiliki penugasan untuk memutuskan adjustment ini.',
                    ],
                ]);
            }

            $oldValues = [
                'status' => $adjustment->status,
                'rejection_reason' => $adjustment->rejection_reason,
                'approved_at' => $adjustment->approved_at,
            ];

            $approval->update([
                'status' => $decision,
                'comments' => $comments,
                'decided_at' => now(),
            ]);

            $adjustment->update([
                'status' => $decision,
                'rejection_reason' => $decision === 'rejected' ? $comments : null,
                'approved_at' => $decision === 'approved' ? now() : null,
            ]);

            $this->auditLogService->updated(
                module: 'adjustments',
                auditable: $adjustment,
                description: "Adjustment {$adjustment->adjustment_number} {$decision}.",
                oldValues: $oldValues,
                newValues: [
                    'status' => $adjustment->status,
                    'rejection_reason' => $adjustment->rejection_reason,
                    'approved_at' => $adjustment->approved_at,
                    'approval_id' => $approval->id,
                ],
            );

            return $adjustment->fresh([
                'invoice.customer',
                'customer',
                'createdBy',
                'appliedBy',
                'approvals.approver',
            ]);
        });
    }

    /**
     * Menerapkan adjustment yang telah disetujui.
     * Nilai invoice asli tidak diubah di sini.
     */
    public function apply(int $id): Adjustment
    {
        return DB::transaction(function () use ($id) {
            $adjustment = Adjustment::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($adjustment->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => [
                        'Hanya adjustment yang sudah disetujui dapat diterapkan.',
                    ],
                ]);
            }

            $invoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($adjustment->invoice_id);

            if ($invoice->currency !== $adjustment->currency) {
                throw ValidationException::withMessages([
                    'currency' => [
                        'Mata uang adjustment tidak sesuai dengan mata uang invoice.',
                    ],
                ]);
            }

            $oldValues = [
                'status' => $adjustment->status,
                'applied_by' => $adjustment->applied_by,
                'applied_at' => $adjustment->applied_at,
            ];

            $adjustment->update([
                'status' => 'applied',
                'applied_by' => Auth::id(),
                'applied_at' => now(),
            ]);

            $this->auditLogService->updated(
                module: 'adjustments',
                auditable: $adjustment,
                description: "Adjustment {$adjustment->adjustment_number} diterapkan.",
                oldValues: $oldValues,
                newValues: [
                    'status' => $adjustment->status,
                    'applied_by' => $adjustment->applied_by,
                    'applied_at' => $adjustment->applied_at,
                    'invoice_id' => $invoice->id,
                    'direction' => $adjustment->direction,
                    'amount' => $adjustment->amount,
                ],
            );

            return $adjustment->fresh([
                'invoice.customer',
                'customer',
                'createdBy',
                'appliedBy',
                'approvals.approver',
            ]);
        });
    }

    public function cancel(int $id, string $reason): Adjustment
    {
        return DB::transaction(function () use ($id, $reason) {
            $adjustment = Adjustment::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if (! in_array(
                $adjustment->status,
                ['pending_approval', 'approved'],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Adjustment hanya dapat dibatalkan sebelum diterapkan.',
                    ],
                ]);
            }

            $oldValues = [
                'status' => $adjustment->status,
                'notes' => $adjustment->notes,
            ];

            $adjustment->update([
                'status' => 'cancelled',
                'notes' => trim(
                    ($adjustment->notes ?? '') . "\nPembatalan: " . $reason
                ),
            ]);

            AdjustmentApproval::query()
                ->where('adjustment_id', $adjustment->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'comments' => $reason,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->auditLogService->updated(
                module: 'adjustments',
                auditable: $adjustment,
                description: "Adjustment {$adjustment->adjustment_number} dibatalkan.",
                oldValues: $oldValues,
                newValues: [
                    'status' => $adjustment->status,
                    'notes' => $adjustment->notes,
                ],
            );

            return $adjustment->fresh([
                'invoice.customer',
                'customer',
                'createdBy',
                'appliedBy',
                'approvals.approver',
            ]);
        });
    }

    private function toMinorUnits(int|float|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    private function fromMinorUnits(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
