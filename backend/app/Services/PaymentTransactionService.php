<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\PaymentAllocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentTransactionService
{
    public function __construct(
        private DocumentNumberingService $documentNumberingService,
        private AuditLogService $auditLogService
    ) {}

    /**
     * Get payment transactions with filters and pagination.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = PaymentTransaction::query()
            ->with([
                'customer',
                'recordedBy',
                'allocations.invoice',
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('transaction_number', 'ilike', "%{$search}%")
                        ->orWhere('payment_reference', 'ilike', "%{$search}%")
                        ->orWhere('payer_name', 'ilike', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
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
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['payment_method'] ?? null,
                fn ($query, $method) => $query->where('payment_method', $method)
            )
            ->when(
                $filters['customer_id'] ?? null,
                fn ($query, $customerId) => $query->where('customer_id', $customerId)
            )
            ->when(
                $filters['payment_date_from'] ?? null,
                fn ($query, $date) => $query->whereDate('payment_date', '>=', $date)
            )
            ->when(
                $filters['payment_date_to'] ?? null,
                fn ($query, $date) => $query->whereDate('payment_date', '<=', $date)
            )
            ->latest('payment_date')
            ->latest('id');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get payment transaction detail.
     */
    public function getById(int $id): PaymentTransaction
    {
        return PaymentTransaction::query()
            ->with([
                'customer',
                'recordedBy',
                'allocations.invoice',
                'verifications.verifiedBy',
            ])
            ->findOrFail($id);
    }

    /**
     * Record a new payment transaction and allocate it to invoices.
     *
     * Expected allocations format:
     * [
     *     [
     *         'invoice_id' => 1,
     *         'allocated_amount' => 100000,
     *         'notes' => 'Pembayaran invoice pertama',
     *     ],
     * ]
     */
    public function create(array $data): PaymentTransaction
    {
        return DB::transaction(function () use ($data) {
            $amountMinor = $this->toMinorUnits($data['amount']);

            if ($amountMinor <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Nominal pembayaran harus lebih besar dari nol.'],
                ]);
            }

            $allocations = $data['allocations'] ?? [];

            if (empty($allocations)) {
                throw ValidationException::withMessages([
                    'allocations' => [
                        'Minimal satu invoice harus dipilih untuk alokasi pembayaran.',
                    ],
                ]);
            }

            $allocationTotalMinor = 0;
            $invoiceIds = [];

            foreach ($allocations as $index => $allocation) {
                $invoiceId = (int) ($allocation['invoice_id'] ?? 0);

                if ($invoiceId <= 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.invoice_id" => [
                            'Invoice wajib dipilih.',
                        ],
                    ]);
                }

                if (in_array($invoiceId, $invoiceIds, true)) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.invoice_id" => [
                            'Invoice yang sama tidak boleh dialokasikan lebih dari satu kali.',
                        ],
                    ]);
                }

                $invoiceIds[] = $invoiceId;

                $allocatedMinor = $this->toMinorUnits(
                    $allocation['allocated_amount'] ?? 0
                );

                if ($allocatedMinor <= 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.allocated_amount" => [
                            'Nominal alokasi harus lebih besar dari nol.',
                        ],
                    ]);
                }

                $allocationTotalMinor += $allocatedMinor;
            }

            if ($allocationTotalMinor > $amountMinor) {
                throw ValidationException::withMessages([
                    'allocations' => [
                        'Total alokasi tidak boleh melebihi nominal pembayaran.',
                    ],
                ]);
            }

            // Lock invoices in a consistent order to reduce deadlock risk.
            $invoices = Invoice::query()
                ->whereIn('id', $invoiceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages([
                    'allocations' => [
                        'Satu atau beberapa invoice tidak ditemukan.',
                    ],
                ]);
            }

            $currency = strtoupper($data['currency'] ?? 'IDR');

            foreach ($allocations as $index => $allocation) {
                $invoiceId = (int) $allocation['invoice_id'];
                $invoice = $invoices->get($invoiceId);

                if ((int) $invoice->customer_id !== (int) $data['customer_id']) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.invoice_id" => [
                            'Invoice harus dimiliki oleh pelanggan yang sama dengan transaksi pembayaran.',
                        ],
                    ]);
                }

                if (strtoupper($invoice->currency) !== $currency) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.invoice_id" => [
                            'Mata uang transaksi harus sama dengan mata uang invoice.',
                        ],
                    ]);
                }

                if ($invoice->status === 'cancelled') {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.invoice_id" => [
                            'Invoice yang dibatalkan tidak dapat menerima pembayaran.',
                        ],
                    ]);
                }

                $existingAllocated = DB::table('payment_allocations as pa')
                    ->join(
                        'payment_transactions as pt',
                        'pt.id',
                        '=',
                        'pa.payment_transaction_id'
                    )
                    ->where('pa.invoice_id', $invoiceId)
                    ->whereIn('pt.status', [
                        'pending_verification',
                        'verified',
                    ])
                    ->sum('pa.allocated_amount');

                $existingAllocatedMinor = $this->toMinorUnits(
                    $existingAllocated ?? 0
                );

                $invoiceTotalMinor = $this->toMinorUnits(
                    $invoice->total_amount
                );

                $remainingMinor = $invoiceTotalMinor - $existingAllocatedMinor;

                $requestedMinor = $this->toMinorUnits(
                    $allocation['allocated_amount']
                );

                if ($remainingMinor <= 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.invoice_id" => [
                            'Invoice sudah lunas atau seluruh tagihannya telah dialokasikan.',
                        ],
                    ]);
                }

                if ($requestedMinor > $remainingMinor) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.allocated_amount" => [
                            'Nominal alokasi melebihi sisa tagihan invoice.',
                        ],
                    ]);
                }
            }

            $transaction = PaymentTransaction::create([
                'transaction_number' => $this->documentNumberingService
                    ->generate('payment'),
                'customer_id' => $data['customer_id'],
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'currency' => $currency,
                'payment_method' => $data['payment_method'],
                'payment_channel' => $data['payment_channel'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'account_name' => $data['account_name'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payer_name' => $data['payer_name'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
                'proof_original_name' => $data['proof_original_name'] ?? null,
                'status' => 'pending_verification',
                'recorded_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($allocations as $allocation) {
                PaymentAllocation::create([
                    'payment_transaction_id' => $transaction->id,
                    'invoice_id' => $allocation['invoice_id'],
                    'allocated_amount' => $allocation['allocated_amount'],
                    'notes' => $allocation['notes'] ?? null,
                ]);
            }

            $this->auditLogService->log(
                'created',
                'payment_transactions',
                "Transaksi pembayaran {$transaction->transaction_number} berhasil dicatat.",
                $transaction,
                null,
                [
                    'transaction_number' => $transaction->transaction_number,
                    'customer_id' => $transaction->customer_id,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'allocations' => $allocations,
                ]
            );

            return $this->getById($transaction->id);
        });
    }

    /**
     * Update editable details of a pending payment transaction.
     *
     * Financial amount, customer, currency, and allocations
     * cannot be changed through this method.
     */
    public function update(int $id, array $data): PaymentTransaction
    {
        return DB::transaction(function () use ($id, $data) {
            $transaction = PaymentTransaction::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($transaction->status !== 'pending_verification') {
                throw ValidationException::withMessages([
                    'payment' => [
                        'Hanya transaksi yang menunggu verifikasi yang dapat diperbarui.',
                    ],
                ]);
            }

            $oldValues = $transaction->only([
                'payment_date',
                'payment_method',
                'payment_channel',
                'bank_name',
                'account_name',
                'payment_reference',
                'payer_name',
                'proof_path',
                'proof_original_name',
                'notes',
            ]);

            $allowedFields = [
                'payment_date',
                'payment_method',
                'payment_channel',
                'bank_name',
                'account_name',
                'payment_reference',
                'payer_name',
                'proof_path',
                'proof_original_name',
                'notes',
            ];

            $updateData = array_intersect_key(
                $data,
                array_flip($allowedFields)
            );

            if (empty($updateData)) {
                throw ValidationException::withMessages([
                    'payment' => [
                        'Minimal satu data transaksi harus diperbarui.',
                    ],
                ]);
            }

            $transaction->update($updateData);

            $newValues = $transaction->only($allowedFields);

            $this->auditLogService->log(
                'updated',
                'payment_transactions',
                "Transaksi pembayaran {$transaction->transaction_number} diperbarui.",
                $transaction,
                $oldValues,
                $newValues
            );

            return $this->getById($transaction->id);
        });
    }

    /**
     * Convert a decimal monetary value to integer minor units (cents).
     * This avoids floating-point arithmetic during financial comparisons.
     */
    private function toMinorUnits(string|int|float $amount): int
    {
        $value = trim((string) $amount);

        if (! preg_match('/^\d{1,16}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => [
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