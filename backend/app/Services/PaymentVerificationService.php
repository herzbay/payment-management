<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\PaymentVerification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentVerificationService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private AccountReceivableService $accountReceivableService,
    ) {}

    /**
     * Get payment transactions for verification.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = PaymentTransaction::query()
            ->with([
                'customer',
                'recordedBy',
                'allocations.invoice',
                'verifications.verifiedBy',
            ])
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('transaction_number', 'ilike', "%{$search}%")
                            ->orWhere('payment_reference', 'ilike', "%{$search}%")
                            ->orWhereHas('customer', function ($customerQuery) use ($search) {
                                $customerQuery->where(
                                    'company_name',
                                    'ilike',
                                    "%{$search}%"
                                );
                            });
                    });
                }
            )
            ->when(
                $filters['payment_date_from'] ?? null,
                fn ($query, $date) => $query->whereDate(
                    'payment_date',
                    '>=',
                    $date
                )
            )
            ->when(
                $filters['payment_date_to'] ?? null,
                fn ($query, $date) => $query->whereDate(
                    'payment_date',
                    '<=',
                    $date
                )
            )
            ->latest('payment_date')
            ->latest('id');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get payment transaction detail for verification.
     */
    public function getById(int $id): PaymentTransaction
    {
        return PaymentTransaction::query()
            ->with([
                'customer',
                'recordedBy',
                'allocations.invoice.customer',
                'verifications.verifiedBy',
            ])
            ->findOrFail($id);
    }

    /**
     * Approve a payment transaction.
     */
    public function approve(
        int $transactionId,
        array $data
    ): PaymentTransaction {
        return DB::transaction(function () use ($transactionId, $data) {
            $transaction = PaymentTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transactionId);

            $this->ensurePending($transaction);

            $confirmedAmount = $data['confirmed_amount']
                ?? $transaction->amount;

            if (
                $this->toMinorUnits($confirmedAmount)
                !== $this->toMinorUnits($transaction->amount)
            ) {
                throw ValidationException::withMessages([
                    'confirmed_amount' => [
                        'Nominal yang dikonfirmasi harus sama dengan nominal transaksi pembayaran.',
                    ],
                ]);
            }

            $now = now();

            $verification = PaymentVerification::create([
                'payment_transaction_id' => $transaction->id,
                'verified_by' => Auth::id(),
                'status' => 'verified',
                'confirmed_amount' => $confirmedAmount,
                'confirmed_reference' => $data['confirmed_reference']
                    ?? $transaction->payment_reference,
                'notes' => $data['notes'] ?? null,
                'rejection_reason' => null,
                'verified_at' => $now,
            ]);

            $transaction->update([
                'status' => 'verified',
                'verified_at' => $now,
            ]);

            $this->recalculateInvoices($transaction);

            $this->auditLogService->log(
                event: 'verified',
                module: 'payment_verifications',
                description: "Transaksi pembayaran {$transaction->transaction_number} berhasil diverifikasi.",
                auditable: $transaction,
                oldValues: [
                    'status' => 'pending_verification',
                    'verified_at' => null,
                ],
                newValues: [
                    'status' => 'verified',
                    'verified_at' => $now->toDateTimeString(),
                ],
                properties: [
                    'verification_id' => $verification->id,
                    'verified_by' => Auth::id(),
                    'confirmed_amount' => $confirmedAmount,
                    'confirmed_reference' => $verification->confirmed_reference,
                ]
            );

            return $this->getById($transaction->id);
        });
    }

    /**
     * Reject a payment transaction.
     */
    public function reject(
        int $transactionId,
        array $data
    ): PaymentTransaction {
        return DB::transaction(function () use ($transactionId, $data) {
            $transaction = PaymentTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transactionId);

            $this->ensurePending($transaction);

            $now = now();

            $verification = PaymentVerification::create([
                'payment_transaction_id' => $transaction->id,
                'verified_by' => Auth::id(),
                'status' => 'rejected',
                'confirmed_amount' => $data['confirmed_amount'] ?? null,
                'confirmed_reference' => $data['confirmed_reference']
                    ?? $transaction->payment_reference,
                'notes' => $data['notes'] ?? null,
                'rejection_reason' => $data['rejection_reason'],
                'verified_at' => $now,
            ]);

            $transaction->update([
                'status' => 'rejected',
                'verified_at' => null,
            ]);

            $this->auditLogService->log(
                event: 'rejected',
                module: 'payment_verifications',
                description: "Transaksi pembayaran {$transaction->transaction_number} ditolak.",
                auditable: $transaction,
                oldValues: [
                    'status' => 'pending_verification',
                ],
                newValues: [
                    'status' => 'rejected',
                ],
                properties: [
                    'verification_id' => $verification->id,
                    'verified_by' => Auth::id(),
                    'rejection_reason' => $verification->rejection_reason,
                ]
            );

            return $this->getById($transaction->id);
        });
    }

    /**
     * Ensure transaction is eligible for verification.
     */
    private function ensurePending(PaymentTransaction $transaction): void
    {
        if ($transaction->status !== 'pending_verification') {
            throw ValidationException::withMessages([
                'payment' => [
                    'Transaksi ini sudah diproses dan tidak dapat diverifikasi kembali.',
                ],
            ]);
        }

        if (! $transaction->allocations()->exists()) {
            throw ValidationException::withMessages([
                'payment' => [
                    'Transaksi tidak memiliki alokasi invoice.',
                ],
            ]);
        }
    }

    /**
     * Recalculate invoice payment status using verified allocations only.
     */
    private function recalculateInvoices(
        PaymentTransaction $transaction
    ): void {
        $invoiceIds = $transaction->allocations()
            ->pluck('invoice_id')
            ->unique()
            ->sort()
            ->values();

        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($invoices as $invoice) {
            $paidAmount = DB::table('payment_allocations as pa')
                ->join(
                    'payment_transactions as pt',
                    'pt.id',
                    '=',
                    'pa.payment_transaction_id'
                )
                ->where('pa.invoice_id', $invoice->id)
                ->where('pt.status', 'verified')
                ->sum('pa.allocated_amount');

            $paidMinor = $this->toMinorUnits($paidAmount ?? 0);
            $totalMinor = $this->toMinorUnits($invoice->total_amount);

            if ($paidMinor <= 0) {
                $paymentStatus = 'unpaid';
            } elseif ($paidMinor < $totalMinor) {
                $paymentStatus = 'partially_paid';
            } elseif ($paidMinor === $totalMinor) {
                $paymentStatus = 'paid';
            } else {
                $paymentStatus = 'overpaid';
            }

            if ($invoice->payment_status !== $paymentStatus) {
                $oldStatus = $invoice->payment_status;

                $invoice->update([
                    'payment_status' => $paymentStatus,
                ]);

                $this->auditLogService->log(
                    event: 'updated',
                    module: 'invoices',
                    description: "Status pembayaran invoice {$invoice->invoice_number} diperbarui.",
                    auditable: $invoice,
                    oldValues: [
                        'payment_status' => $oldStatus,
                    ],
                    newValues: [
                        'payment_status' => $paymentStatus,
                    ],
                    properties: [
                        'paid_amount' => number_format($paidMinor / 100, 2, '.', ''),
                        'invoice_total' => $invoice->total_amount,
                    ]
                );
            }
            $this->accountReceivableService->syncFromInvoice($invoice);
        }
    }

    /**
     * Convert monetary values to integer minor units.
     */
    private function toMinorUnits(string|int|float $amount): int
    {
        $value = trim((string) $amount);

        if (! preg_match('/^\d{1,16}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'confirmed_amount' => [
                    'Format nominal tidak valid. Gunakan maksimal dua angka desimal.',
                ],
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        $fraction = str_pad($fraction, 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }
}