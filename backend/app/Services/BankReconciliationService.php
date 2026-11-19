<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankReconciliationService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Get unmatched bank statement lines.
     */
    public function getUnmatchedLines(
        int $statementId,
        int $perPage = 20
    ) {
        $statement = BankStatement::findOrFail($statementId);

        return $statement->lines()
            ->with([
                'paymentTransaction',
                'refund',
                'matchedBy',
            ])
            ->where('match_status', 'unmatched')
            ->orderBy('transaction_date')
            ->orderBy('line_number')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Get possible internal transaction candidates.
     */
    public function getCandidates(
        int $lineId,
        array $filters = []
    ): array {
        $line = BankStatementLine::query()
            ->with('bankStatement')
            ->findOrFail($lineId);

        if ($line->match_status !== 'unmatched') {
            throw ValidationException::withMessages([
                'line' => [
                    'Baris transaksi ini sudah dicocokkan.',
                ],
            ]);
        }

        $search = $filters['search'] ?? null;

        if ($line->transaction_type === 'credit') {
            $query = PaymentTransaction::query()
                ->where('status', 'verified')
                ->where(
                    'currency',
                    $line->bankStatement->currency
                )
                ->where('amount', $line->amount)
                ->whereDoesntHave('bankStatementLines');

            if ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where(
                        'transaction_number',
                        'ilike',
                        "%{$search}%"
                    )->orWhere(
                        'payment_reference',
                        'ilike',
                        "%{$search}%"
                    )->orWhere(
                        'payer_name',
                        'ilike',
                        "%{$search}%"
                    );
                });
            }

            return [
                'transaction_type' => 'payment',
                'bank_line' => $line,
                'candidates' => $query
                    ->orderByDesc('payment_date')
                    ->limit(100)
                    ->get(),
            ];
        }

        if ($line->transaction_type === 'debit') {
            $query = Refund::query()
                ->where('status', 'completed')
                ->where(
                    'currency',
                    $line->bankStatement->currency
                )
                ->where('refund_amount', $line->amount)
                ->whereDoesntHave('bankStatementLines');

            if ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where(
                        'refund_number',
                        'ilike',
                        "%{$search}%"
                    )->orWhere(
                        'refund_reference',
                        'ilike',
                        "%{$search}%"
                    );
                });
            }

            return [
                'transaction_type' => 'refund',
                'bank_line' => $line,
                'candidates' => $query
                    ->orderByDesc('refund_date')
                    ->limit(100)
                    ->get(),
            ];
        }

        throw ValidationException::withMessages([
            'transaction_type' => [
                'Tipe transaksi bank tidak dikenali.',
            ],
        ]);
    }

    /**
     * Match a bank statement line with an internal transaction.
     */
    public function matchLine(
        int $lineId,
        string $transactionType,
        int $transactionId,
        ?string $notes = null
    ): BankStatementLine {
        return DB::transaction(function () use (
            $lineId,
            $transactionType,
            $transactionId,
            $notes
        ) {
            $line = BankStatementLine::query()
                ->lockForUpdate()
                ->findOrFail($lineId);

            $statement = BankStatement::query()
                ->lockForUpdate()
                ->findOrFail($line->bank_statement_id);

            if ($statement->status === 'reconciled') {
                throw ValidationException::withMessages([
                    'statement' => [
                        'Statement yang sudah direkonsiliasi tidak dapat diubah.',
                    ],
                ]);
            }

            if ($line->match_status !== 'unmatched') {
                throw ValidationException::withMessages([
                    'line' => [
                        'Baris transaksi sudah dicocokkan.',
                    ],
                ]);
            }

            $oldValues = $line->only([
                'match_status',
                'payment_transaction_id',
                'refund_id',
                'matched_by',
                'matched_at',
                'match_notes',
            ]);

            if ($transactionType === 'payment') {
                $transaction = $this->matchPayment(
                    $line,
                    $statement,
                    $transactionId
                );
            } elseif ($transactionType === 'refund') {
                $transaction = $this->matchRefund(
                    $line,
                    $statement,
                    $transactionId
                );
            } else {
                throw ValidationException::withMessages([
                    'transaction_type' => [
                        'Tipe pencocokan harus payment atau refund.',
                    ],
                ]);
            }

            $line->update([
                'payment_transaction_id' => $transactionType === 'payment'
                    ? $transaction->id
                    : null,
                'refund_id' => $transactionType === 'refund'
                    ? $transaction->id
                    : null,
                'match_status' => 'matched',
                'matched_by' => auth()->id(),
                'matched_at' => now(),
                'match_notes' => $notes,
            ]);

            $this->refreshStatementCounters($statement);

            $this->auditLogService->log(
                event: 'matched',
                module: 'bank_reconciliation',
                description: "Baris transaksi bank nomor {$line->line_number} berhasil dicocokkan dengan {$transactionType} {$transaction->id}.",
                auditable: $line,
                oldValues: $oldValues,
                newValues: $line->fresh()->only([
                    'match_status',
                    'payment_transaction_id',
                    'refund_id',
                    'matched_by',
                    'matched_at',
                    'match_notes',
                ]),
                properties: [
                    'statement_id' => $statement->id,
                    'transaction_type' => $transactionType,
                    'transaction_id' => $transaction->id,
                ],
            );

            return $line->fresh([
                'bankStatement',
                'paymentTransaction',
                'refund',
                'matchedBy',
            ]);
        });
    }

    /**
     * Validate and retrieve a payment transaction.
     */
    protected function matchPayment(
        BankStatementLine $line,
        BankStatement $statement,
        int $transactionId
    ): PaymentTransaction {
        if ($line->transaction_type !== 'credit') {
            throw ValidationException::withMessages([
                'transaction_type' => [
                    'Payment hanya dapat dicocokkan dengan transaksi credit.',
                ],
            ]);
        }

        $transaction = PaymentTransaction::query()
            ->lockForUpdate()
            ->findOrFail($transactionId);

        if ($transaction->status !== 'verified') {
            throw ValidationException::withMessages([
                'transaction_id' => [
                    'Payment transaction harus berstatus verified.',
                ],
            ]);
        }

        if ($transaction->currency !== $statement->currency) {
            throw ValidationException::withMessages([
                'transaction_id' => [
                    'Mata uang payment tidak sesuai dengan statement.',
                ],
            ]);
        }

        if (!$this->amountsMatch(
            $line->amount,
            $transaction->amount
        )) {
            throw ValidationException::withMessages([
                'transaction_id' => [
                    'Nominal payment tidak sama dengan nominal transaksi bank.',
                ],
            ]);
        }

        $alreadyMatched = BankStatementLine::query()
            ->where('payment_transaction_id', $transaction->id)
            ->exists();

        if ($alreadyMatched) {
            throw ValidationException::withMessages([
                'transaction_id' => [
                    'Payment transaction sudah dicocokkan dengan statement lain.',
                ],
            ]);
        }

        return $transaction;
    }

    /**
     * Validate and retrieve a refund transaction.
     */
    protected function matchRefund(
        BankStatementLine $line,
        BankStatement $statement,
        int $transactionId
    ): Refund {
        if ($line->transaction_type !== 'debit') {
            throw ValidationException::withMessages([
                'transaction_type' => [
                    'Refund hanya dapat dicocokkan dengan transaksi debit.',
                ],
            ]);
        }

        $transaction = Refund::query()
            ->lockForUpdate()
            ->findOrFail($transactionId);

        if ($transaction->status !== 'completed') {
            throw ValidationException::withMessages([
                'transaction_id' => [
                    'Refund harus berstatus completed.',
                ],
            ]);
        }

        if ($transaction->currency !== $statement->currency) {
            throw ValidationException::withMessages([
                'transaction_id' => [
                    'Mata uang refund tidak sesuai dengan statement.',
                ],
            ]);
        }

        if (!$this->amountsMatch(
            $line->amount,
            $transaction->refund_amount
        )) {
            throw ValidationException::withMessages([
                'transaction_id' => [
                    'Nominal refund tidak sama dengan nominal transaksi bank.',
                ],
            ]);
        }

        $alreadyMatched = BankStatementLine::query()
            ->where('refund_id', $transaction->id)
            ->exists();

        if ($alreadyMatched) {
            throw ValidationException::withMessages([
                'transaction_id' => [
                    'Refund sudah dicocokkan dengan statement lain.',
                ],
            ]);
        }

        return $transaction;
    }

    /**
     * Cancel an existing transaction match.
     */
    public function unmatchLine(
        int $lineId,
        ?string $reason = null
    ): BankStatementLine {
        return DB::transaction(function () use (
            $lineId,
            $reason
        ) {
            $line = BankStatementLine::query()
                ->lockForUpdate()
                ->findOrFail($lineId);

            $statement = BankStatement::query()
                ->lockForUpdate()
                ->findOrFail($line->bank_statement_id);

            if ($statement->status === 'reconciled') {
                throw ValidationException::withMessages([
                    'statement' => [
                        'Statement yang sudah direkonsiliasi tidak dapat diubah.',
                    ],
                ]);
            }

            if ($line->match_status !== 'matched') {
                throw ValidationException::withMessages([
                    'line' => [
                        'Baris transaksi belum memiliki pencocokan.',
                    ],
                ]);
            }

            $oldValues = $line->only([
                'match_status',
                'payment_transaction_id',
                'refund_id',
                'matched_by',
                'matched_at',
                'match_notes',
            ]);

            $line->update([
                'payment_transaction_id' => null,
                'refund_id' => null,
                'match_status' => 'unmatched',
                'matched_by' => null,
                'matched_at' => null,
                'match_notes' => $reason,
            ]);

            $this->refreshStatementCounters($statement);

            $this->auditLogService->log(
                event: 'unmatched',
                module: 'bank_reconciliation',
                description: "Pencocokan transaksi bank nomor {$line->line_number} dibatalkan.",
                auditable: $line,
                oldValues: $oldValues,
                newValues: $line->fresh()->only([
                    'match_status',
                    'payment_transaction_id',
                    'refund_id',
                    'matched_by',
                    'matched_at',
                    'match_notes',
                ]),
                properties: [
                    'statement_id' => $statement->id,
                    'reason' => $reason,
                ],
            );

            return $line->fresh([
                'bankStatement',
                'paymentTransaction',
                'refund',
                'matchedBy',
            ]);
        });
    }

    /**
     * Refresh statement transaction counters.
     */
    protected function refreshStatementCounters(
        BankStatement $statement
    ): void {
        $matchedRows = $statement->lines()
            ->where('match_status', 'matched')
            ->count();

        $totalRows = $statement->lines()->count();

        $statement->update([
            'total_rows' => $totalRows,
            'matched_rows' => $matchedRows,
            'unmatched_rows' => $totalRows - $matchedRows,
            'status' => $matchedRows > 0
                ? 'reconciling'
                : 'draft',
        ]);
    }

    /**
     * Compare monetary values to two decimal places.
     */
    protected function amountsMatch(
        mixed $first,
        mixed $second
    ): bool {
        return number_format((float) $first, 2, '.', '') ===
            number_format((float) $second, 2, '.', '');
    }
}
