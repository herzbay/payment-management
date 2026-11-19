<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankStatementFinalizationService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Finalize a bank statement after all transactions have been matched.
     */
    public function finalize(int $statementId): BankStatement
    {
        return DB::transaction(function () use ($statementId) {
            $statement = BankStatement::query()
                ->lockForUpdate()
                ->findOrFail($statementId);

            if ($statement->status === 'reconciled') {
                throw ValidationException::withMessages([
                    'statement' => [
                        'Bank statement ini sudah direkonsiliasi.',
                    ],
                ]);
            }

            $lines = $statement->lines()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => [
                        'Bank statement belum memiliki baris transaksi.',
                    ],
                ]);
            }

            $totalRows = $lines->count();

            $matchedRows = $lines->where(
                'match_status',
                'matched'
            )->count();

            $unmatchedRows = $totalRows - $matchedRows;

            if ($unmatchedRows > 0) {
                throw ValidationException::withMessages([
                    'lines' => [
                        "Masih terdapat {$unmatchedRows} transaksi yang belum dicocokkan.",
                    ],
                ]);
            }

            $totalCredit = 0;
            $totalDebit = 0;

            foreach ($lines as $line) {
                $amountInCents = $this->toCents($line->amount);

                if ($line->transaction_type === 'credit') {
                    $totalCredit += $amountInCents;
                } elseif ($line->transaction_type === 'debit') {
                    $totalDebit += $amountInCents;
                } else {
                    throw ValidationException::withMessages([
                        'transaction_type' => [
                            "Tipe transaksi pada baris {$line->line_number} tidak valid.",
                        ],
                    ]);
                }
            }

            $openingBalance = $this->toCents(
                $statement->opening_balance
            );

            $closingBalance = $this->toCents(
                $statement->closing_balance
            );

            $calculatedClosingBalance =
                $openingBalance + $totalCredit - $totalDebit;

            if ($calculatedClosingBalance !== $closingBalance) {
                $calculated = $this->fromCents(
                    $calculatedClosingBalance
                );

                $expected = $this->fromCents($closingBalance);

                throw ValidationException::withMessages([
                    'closing_balance' => [
                        "Saldo akhir tidak sesuai. Saldo akhir berdasarkan transaksi adalah {$calculated}, sedangkan saldo akhir pada statement adalah {$expected}.",
                    ],
                ]);
            }

            $oldValues = [
                'status' => $statement->status,
                'matched_rows' => $statement->matched_rows,
                'unmatched_rows' => $statement->unmatched_rows,
                'reconciled_at' => $statement->reconciled_at,
            ];

            $statement->update([
                'total_rows' => $totalRows,
                'matched_rows' => $matchedRows,
                'unmatched_rows' => 0,
                'status' => 'reconciled',
                'reconciled_at' => now(),
            ]);

            $newValues = [
                'status' => 'reconciled',
                'total_rows' => $totalRows,
                'matched_rows' => $matchedRows,
                'unmatched_rows' => 0,
                'opening_balance' => $statement->opening_balance,
                'total_credit' => $this->fromCents($totalCredit),
                'total_debit' => $this->fromCents($totalDebit),
                'calculated_closing_balance' => $this->fromCents(
                    $calculatedClosingBalance
                ),
                'closing_balance' => $statement->closing_balance,
                'reconciled_at' => $statement->reconciled_at,
            ];

            $this->auditLogService->log(
                event: 'bank_statement.reconciled',
                module: 'bank_reconciliation',
                description: "Bank statement {$statement->statement_number} berhasil direkonsiliasi.",
                auditable: $statement,
                oldValues: $oldValues,
                newValues: $newValues,
                properties: [
                    'bank_account_id' => $statement->bank_account_id,
                    'statement_number' => $statement->statement_number,
                    'total_rows' => $totalRows,
                    'matched_rows' => $matchedRows,
                    'total_credit' => $this->fromCents($totalCredit),
                    'total_debit' => $this->fromCents($totalDebit),
                ],
            );

            return $statement->fresh([
                'bankAccount',
                'importedBy',
                'lines.paymentTransaction',
                'lines.refund',
                'lines.matchedBy',
            ]);
        });
    }

    /**
     * Convert a decimal monetary value into integer cents.
     */
    protected function toCents(string|int|float|null $value): int
    {
        $value = trim((string) ($value ?? '0'));

        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => [
                    'Format nominal pada bank statement tidak valid.',
                ],
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '0'
        );

        $fraction = str_pad($fraction, 2, '0');

        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        return $negative ? -$cents : $cents;
    }

    /**
     * Convert integer cents into a decimal string.
     */
    protected function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);

        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return ($negative ? '-' : '')
            . $whole
            . '.'
            . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}