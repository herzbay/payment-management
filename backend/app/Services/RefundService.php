<?php

namespace App\Services;

use App\Models\PaymentAllocation;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\RefundItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        private DocumentNumberingService $documentNumberingService,
        private AuditLogService $auditLogService,
    ) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return Refund::query()
            ->with([
                'paymentTransaction.customer',
                'customer',
                'requestedBy',
                'processedBy',
                'items.paymentAllocation.invoice',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery
                        ->where('refund_number', 'ilike', "%{$search}%")
                        ->orWhere('reason', 'ilike', "%{$search}%")
                        ->orWhereHas('paymentTransaction', function (Builder $paymentQuery) use ($search) {
                            $paymentQuery->where(
                                'transaction_number',
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
                $filters['customer_id'] ?? null,
                fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId)
            )
            ->when(
                $filters['payment_transaction_id'] ?? null,
                fn (Builder $query, int $paymentId) => $query->where('payment_transaction_id', $paymentId)
            )
            ->when(
                $filters['refund_date_from'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('refund_date', '>=', $date)
            )
            ->when(
                $filters['refund_date_to'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('refund_date', '<=', $date)
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id): Refund
    {
        return Refund::query()
            ->with([
                'paymentTransaction.customer',
                'paymentTransaction.allocations.invoice',
                'customer',
                'requestedBy',
                'processedBy',
                'items.paymentAllocation.invoice',
            ])
            ->findOrFail($id);
    }

    /**
     * Mengajukan refund berdasarkan alokasi pembayaran yang sudah diverifikasi.
     *
     * Format items:
     * [
     *     [
     *         'payment_allocation_id' => 1,
     *         'refund_amount' => 100000,
     *         'description' => 'Pengembalian dana',
     *     ],
     * ]
     */
    public function create(array $data): Refund
    {
        return DB::transaction(function () use ($data) {
            $payment = PaymentTransaction::query()
                ->lockForUpdate()
                ->findOrFail($data['payment_transaction_id']);

            if ($payment->status !== 'verified') {
                throw ValidationException::withMessages([
                    'payment_transaction_id' => [
                        'Refund hanya dapat diajukan dari pembayaran yang sudah diverifikasi.',
                    ],
                ]);
            }

            $items = $data['items'] ?? [];

            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => ['Minimal satu alokasi pembayaran harus dipilih.'],
                ]);
            }

            $allocationIds = collect($items)
                ->pluck('payment_allocation_id');

            if ($allocationIds->unique()->count() !== $allocationIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['Alokasi pembayaran tidak boleh dipilih lebih dari satu kali.'],
                ]);
            }

            $allocations = PaymentAllocation::query()
                ->whereIn('id', $allocationIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($allocations->count() !== $allocationIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['Salah satu alokasi pembayaran tidak ditemukan.'],
                ]);
            }

            $totalRefund = 0;
            $validatedItems = [];

            foreach ($items as $item) {
                $allocation = $allocations->get($item['payment_allocation_id']);

                if ((int) $allocation->payment_transaction_id !== (int) $payment->id) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Alokasi yang dipilih bukan bagian dari transaksi pembayaran tersebut.',
                        ],
                    ]);
                }

                $refundMinor = $this->toMinorUnits($item['refund_amount']);

                if ($refundMinor <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Jumlah refund harus lebih besar dari nol.'],
                    ]);
                }

                $allocatedMinor = $this->toMinorUnits($allocation->allocated_amount);

                $reservedMinor = $this->getReservedRefundAmount(
                    (int) $allocation->id
                );

                $availableMinor = $allocatedMinor - $reservedMinor;

                if ($refundMinor > $availableMinor) {
                    throw ValidationException::withMessages([
                        'items' => [
                            "Jumlah refund melebihi sisa alokasi pembayaran ID {$allocation->id}.",
                        ],
                    ]);
                }

                $totalRefund += $refundMinor;

                $validatedItems[] = [
                    'payment_allocation_id' => $allocation->id,
                    'description' => $item['description'] ?? null,
                    'refund_amount' => $this->fromMinorUnits($refundMinor),
                ];
            }

            if ($totalRefund > $this->toMinorUnits($payment->amount)) {
                throw ValidationException::withMessages([
                    'items' => ['Total refund melebihi jumlah transaksi pembayaran.'],
                ]);
            }

            $refund = Refund::create([
                'refund_number' => $this->documentNumberingService->generate('refund'),
                'payment_transaction_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'requested_by' => Auth::id(),
                'refund_date' => $data['refund_date'] ?? null,
                'currency' => $payment->currency,
                'refund_amount' => $this->fromMinorUnits($totalRefund),
                'refund_method' => $data['refund_method'] ?? null,
                'destination_account' => $data['destination_account'] ?? null,
                'refund_reference' => $data['refund_reference'] ?? null,
                'status' => 'pending_approval',
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
            ]);

            foreach ($validatedItems as $item) {
                $refund->items()->create($item);
            }

            $refund->load([
                'paymentTransaction.customer',
                'customer',
                'requestedBy',
                'items.paymentAllocation.invoice',
            ]);

            $this->auditLogService->created(
                module: 'refunds',
                auditable: $refund,
                description: "Pengajuan refund {$refund->refund_number} dibuat.",
                properties: [
                    'payment_transaction_id' => $payment->id,
                    'refund_amount' => $refund->refund_amount,
                    'currency' => $refund->currency,
                ],
            );

            return $refund;
        });
    }

    public function approve(int $id, ?string $notes = null): Refund
    {
        return $this->decide($id, 'approved', $notes);
    }

    public function reject(int $id, string $reason): Refund
    {
        return $this->decide($id, 'rejected', $reason);
    }

    private function decide(
        int $id,
        string $status,
        ?string $comments,
    ): Refund {
        return DB::transaction(function () use ($id, $status, $comments) {
            $refund = Refund::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($refund->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'status' => [
                        'Hanya refund dengan status pending approval yang dapat diputuskan.',
                    ],
                ]);
            }

            $oldValues = [
                'status' => $refund->status,
                'rejection_reason' => $refund->rejection_reason,
                'approved_at' => $refund->approved_at,
            ];

            $refund->update([
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? $comments : null,
                'approved_at' => $status === 'approved' ? now() : null,
            ]);

            $this->auditLogService->updated(
                module: 'refunds',
                auditable: $refund,
                description: "Refund {$refund->refund_number} {$status}.",
                oldValues: $oldValues,
                newValues: [
                    'status' => $refund->status,
                    'rejection_reason' => $refund->rejection_reason,
                    'approved_at' => $refund->approved_at,
                ],
            );

            return $refund->fresh([
                'paymentTransaction.customer',
                'customer',
                'requestedBy',
                'processedBy',
                'items.paymentAllocation.invoice',
            ]);
        });
    }

    public function markProcessing(int $id, array $data = []): Refund
    {
        return DB::transaction(function () use ($id, $data) {
            $refund = Refund::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($refund->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => ['Hanya refund yang sudah disetujui dapat diproses.'],
                ]);
            }

            $oldValues = ['status' => $refund->status];

            $refund->update([
                'status' => 'processing',
                'processed_by' => Auth::id(),
                'refund_method' => $data['refund_method'] ?? $refund->refund_method,
                'destination_account' => $data['destination_account'] ?? $refund->destination_account,
                'refund_reference' => $data['refund_reference'] ?? $refund->refund_reference,
                'refund_date' => $data['refund_date'] ?? $refund->refund_date,
                'processed_at' => now(),
                'notes' => $data['notes'] ?? $refund->notes,
            ]);

            $this->auditLogService->updated(
                module: 'refunds',
                auditable: $refund,
                description: "Refund {$refund->refund_number} mulai diproses.",
                oldValues: $oldValues,
                newValues: [
                    'status' => $refund->status,
                    'processed_by' => $refund->processed_by,
                    'processed_at' => $refund->processed_at,
                ],
            );

            return $refund->fresh([
                'paymentTransaction.customer',
                'customer',
                'requestedBy',
                'processedBy',
                'items.paymentAllocation.invoice',
            ]);
        });
    }

    public function complete(int $id, array $data = []): Refund
    {
        return DB::transaction(function () use ($id, $data) {
            $refund = Refund::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($refund->status !== 'processing') {
                throw ValidationException::withMessages([
                    'status' => ['Hanya refund yang sedang diproses dapat diselesaikan.'],
                ]);
            }

            $oldValues = [
                'status' => $refund->status,
                'completed_at' => $refund->completed_at,
            ];

            $refund->update([
                'status' => 'completed',
                'completed_at' => now(),
                'refund_reference' => $data['refund_reference'] ?? $refund->refund_reference,
                'proof_path' => $data['proof_path'] ?? $refund->proof_path,
                'notes' => $data['notes'] ?? $refund->notes,
            ]);

            $this->auditLogService->updated(
                module: 'refunds',
                auditable: $refund,
                description: "Refund {$refund->refund_number} telah selesai.",
                oldValues: $oldValues,
                newValues: [
                    'status' => $refund->status,
                    'completed_at' => $refund->completed_at,
                    'refund_reference' => $refund->refund_reference,
                ],
            );

            return $refund->fresh([
                'paymentTransaction.customer',
                'customer',
                'requestedBy',
                'processedBy',
                'items.paymentAllocation.invoice',
            ]);
        });
    }

    public function cancel(int $id, string $reason): Refund
    {
        return DB::transaction(function () use ($id, $reason) {
            $refund = Refund::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if (! in_array($refund->status, ['pending_approval', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Refund hanya dapat dibatalkan sebelum proses pengembalian dana dimulai.',
                    ],
                ]);
            }

            $oldValues = [
                'status' => $refund->status,
                'notes' => $refund->notes,
            ];

            $refund->update([
                'status' => 'cancelled',
                'notes' => trim(($refund->notes ?? '') . "\nPembatalan: " . $reason),
            ]);

            $this->auditLogService->updated(
                module: 'refunds',
                auditable: $refund,
                description: "Refund {$refund->refund_number} dibatalkan.",
                oldValues: $oldValues,
                newValues: [
                    'status' => $refund->status,
                    'notes' => $refund->notes,
                ],
            );

            return $refund->fresh([
                'paymentTransaction.customer',
                'customer',
                'requestedBy',
                'processedBy',
                'items.paymentAllocation.invoice',
            ]);
        });
    }

    /**
     * Menghitung refund yang masih memesan nilai suatu alokasi.
     * Status rejected dan cancelled tidak lagi memesan nilai.
     */
    private function getReservedRefundAmount(int $allocationId): int
    {
        $amount = RefundItem::query()
            ->where('payment_allocation_id', $allocationId)
            ->whereHas('refund', function (Builder $query) {
                $query->whereNotIn('status', ['rejected', 'cancelled']);
            })
            ->sum('refund_amount');

        return $this->toMinorUnits($amount);
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
