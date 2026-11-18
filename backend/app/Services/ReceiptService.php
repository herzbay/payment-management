<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Services\AuditLogService;
use App\Services\DocumentNumberingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    public function __construct(
        protected DocumentNumberingService $numberingService,
        protected AuditLogService $auditLogService,
    ) {
    }

    /**
     * Get a paginated list of receipts.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Receipt::query()
            ->with([
                'paymentTransaction.customer',
                'paymentTransaction.recordedBy',
                'issuedBy',
                'items.paymentAllocation.invoice',
            ]);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('receipt_number', 'ilike', "%{$search}%")
                    ->orWhereHas(
                        'paymentTransaction',
                        function (Builder $transactionQuery) use ($search) {
                            $transactionQuery
                                ->where('transaction_number', 'ilike', "%{$search}%")
                                ->orWhereHas(
                                    'customer',
                                    fn (Builder $customerQuery) => $customerQuery
                                        ->where('company_name', 'ilike', "%{$search}%")
                                );
                        }
                    );
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->whereHas(
                'paymentTransaction',
                fn (Builder $builder) => $builder->where(
                    'customer_id',
                    $filters['customer_id']
                )
            );
        }

        if (!empty($filters['receipt_date_from'])) {
            $query->whereDate(
                'receipt_date',
                '>=',
                $filters['receipt_date_from']
            );
        }

        if (!empty($filters['receipt_date_to'])) {
            $query->whereDate(
                'receipt_date',
                '<=',
                $filters['receipt_date_to']
            );
        }

        return $query
            ->latest('receipt_date')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get a receipt with its related information.
     */
    public function getById(int $id): Receipt
    {
        return Receipt::query()
            ->with([
                'paymentTransaction.customer',
                'paymentTransaction.recordedBy',
                'issuedBy',
                'items.paymentAllocation.invoice',
            ])
            ->findOrFail($id);
    }

    /**
     * Create a receipt from a verified payment transaction.
     *
     * One payment transaction can only have one receipt.
     */
    public function createFromPaymentTransaction(
        int $paymentTransactionId,
        array $data = []
    ): Receipt {
        return DB::transaction(function () use ($paymentTransactionId, $data) {
            $paymentTransaction = PaymentTransaction::query()
                ->lockForUpdate()
                ->findOrFail($paymentTransactionId);

            if ($paymentTransaction->status !== 'verified') {
                throw ValidationException::withMessages([
                    'payment_transaction_id' => [
                        'Receipt hanya dapat dibuat dari transaksi pembayaran yang sudah diverifikasi.',
                    ],
                ]);
            }

            $existingReceipt = Receipt::query()
                ->where('payment_transaction_id', $paymentTransaction->id)
                ->exists();

            if ($existingReceipt) {
                throw ValidationException::withMessages([
                    'payment_transaction_id' => [
                        'Transaksi pembayaran ini sudah memiliki receipt.',
                    ],
                ]);
            }

            $allocations = $paymentTransaction->allocations()
                ->with('invoice')
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages([
                    'payment_transaction_id' => [
                        'Transaksi pembayaran belum memiliki alokasi invoice.',
                    ],
                ]);
            }

            $allocatedTotal = 0;

            foreach ($allocations as $allocation) {
                if (!$allocation->invoice) {
                    throw ValidationException::withMessages([
                        'payment_transaction_id' => [
                            'Salah satu alokasi pembayaran tidak memiliki invoice yang valid.',
                        ],
                    ]);
                }

                $allocatedTotal += $this->toMinorUnits(
                    $allocation->allocated_amount
                );
            }

            $transactionAmount = $this->toMinorUnits(
                $paymentTransaction->amount
            );

            if ($allocatedTotal !== $transactionAmount) {
                throw ValidationException::withMessages([
                    'payment_transaction_id' => [
                        'Total alokasi invoice harus sama dengan nominal transaksi pembayaran sebelum receipt dapat diterbitkan.',
                    ],
                ]);
            }

            $receipt = Receipt::create([
                'receipt_number' => $this->numberingService->generate('receipt'),
                'payment_transaction_id' => $paymentTransaction->id,
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'currency' => $paymentTransaction->currency,
                'total_amount' => $paymentTransaction->amount,
                'issued_by' => Auth::id(),
                'status' => 'issued',
                'document_path' => $data['document_path'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($allocations as $allocation) {
                $invoice = $allocation->invoice;

                $receipt->items()->create([
                    'payment_allocation_id' => $allocation->id,
                    'description' => sprintf(
                        'Pembayaran invoice %s',
                        $invoice->invoice_number
                    ),
                    'amount' => $allocation->allocated_amount,
                ]);
            }

            $receipt->load([
                'paymentTransaction.customer',
                'paymentTransaction.recordedBy',
                'issuedBy',
                'items.paymentAllocation.invoice',
            ]);

            $this->auditLogService->log(
                event: 'created',
                module: 'receipt_management',
                description: "Receipt {$receipt->receipt_number} diterbitkan untuk transaksi {$paymentTransaction->transaction_number}.",
                auditable: $receipt,
                newValues: $receipt->toArray(),
                properties: [
                    'payment_transaction_id' => $paymentTransaction->id,
                    'payment_transaction_number' => $paymentTransaction->transaction_number,
                    'total_amount' => $receipt->total_amount,
                    'currency' => $receipt->currency,
                    'items_count' => $receipt->items->count(),
                ],
            );

            return $receipt;
        });
    }

    /**
     * Convert a decimal monetary value to integer minor units.
     * Example: 150000.50 becomes 15000050.
     */
    private function toMinorUnits(string|int|float $amount): int
    {
        $value = trim((string) $amount);

        if (!preg_match('/^\d{1,16}(\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => ['Format nominal pembayaran tidak valid.'],
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '0'
        );

        return ((int) $whole * 100)
            + (int) str_pad($fraction, 2, '0');
    }
}
