<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankStatementFinalizationService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * Memvalidasi dan memfinalisasi bank statement.
     */
    public function finalize(int $statementId): array
    {
        return DB::transaction(function () use ($statementId) {
            $statement = BankStatement::query()
                ->whereKey($statementId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->status === 'reconciled') {
                throw ValidationException::withMessages([
                    'statement' => 'Bank statement ini sudah difinalisasi.',
                ]);
            }

            $lines = BankStatementLine::query()
                ->where('bank_statement_id', $statement->id)
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'statement' => 'Bank statement belum memiliki baris transaksi.',
                ]);
            }

            $totalRows = $lines->count();

            $matchedRows = $lines->where('match_status', 'matched')->count();

            $unmatchedRows = $totalRows - $matchedRows;

            if ($unmatchedRows > 0) {
                throw ValidationException::withMessages([
                    'statement' => "Rekonsiliasi belum dapat difinalisasi. Masih terdapat {$unmatchedRows} transaksi yang belum dicocokkan.",
                ]);
            }

            $totalCredit = 0;
            $totalDebit = 0;

            foreach ($lines as $line) {
                $amount = $this->toMinorUnits($line->amount);

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        'statement' => "Nominal transaksi pada baris {$line->line_number} tidak valid.",
                    ]);
                }

                if ($line->transaction_type === 'credit') {
                    $totalCredit += $amount;
                } elseif ($line->transaction_type === 'debit') {
                    $totalDebit += $amount;
                } else {
                    throw ValidationException::withMessages([
                        'statement' => "Jenis transaksi pada baris {$line->line_number} tidak valid.",
                    ]);
                }
            }

            $openingBalance = $this->toMinorUnits(
                $statement->opening_balance
            );

            $closingBalance = $this->toMinorUnits(
                $statement->closing_balance
            );

            $calculatedBalance = $openingBalance + $totalCredit - $totalDebit;

            if ($calculatedBalance !== $closingBalance) {
                $difference = $calculatedBalance - $closingBalance;

                throw ValidationException::withMessages([
                    'balance' => [
                        'Saldo tidak sesuai. '
                        . 'Saldo akhir berdasarkan perhitungan: '
                        . $this->formatMinorUnits($calculatedBalance)
                        . '. Saldo akhir pada statement: '
                        . $this->formatMinorUnits($closingBalance)
                        . '. Selisih: '
                        . $this->formatMinorUnits($difference)
                        . '.',
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
                'status' => $statement->status,
                'matched_rows' => $statement->matched_rows,
                'unmatched_rows' => $statement->unmatched_rows,
                'reconciled_at' => $statement->reconciled_at,
                'total_credit' => $this->formatMinorUnits($totalCredit),
                'total_debit' => $this->formatMinorUnits($totalDebit),
                'calculated_closing_balance' => $this->formatMinorUnits(
                    $calculatedBalance
                ),
            ];

            $this->auditLogService->log(
                event: 'reconciled',
                module: 'bank_reconciliation',
                description: "Bank statement {$statement->statement_number} berhasil difinalisasi.",
                auditable: $statement,
                oldValues: $oldValues,
                newValues: $newValues,
                properties: [
                    'bank_account_id' => $statement->bank_account_id,
                    'total_rows' => $totalRows,
                    'total_credit' => $newValues['total_credit'],
                    'total_debit' => $newValues['total_debit'],
                    'opening_balance' => $this->formatMinorUnits($openingBalance),
                    'closing_balance' => $this->formatMinorUnits($closingBalance),
                ],
            );

            return [
                'statement' => $statement->fresh([
                    'bankAccount',
                    'importedBy',
                ]),
                'reconciliation' => [
                    'total_rows' => $totalRows,
                    'matched_rows' => $matchedRows,
                    'unmatched_rows' => 0,
                    'opening_balance' => $this->formatMinorUnits($openingBalance),
                    'total_credit' => $this->formatMinorUnits($totalCredit),
                    'total_debit' => $this->formatMinorUnits($totalDebit),
                    'calculated_closing_balance' => $this->formatMinorUnits(
                        $calculatedBalance
                    ),
                    'statement_closing_balance' => $this->formatMinorUnits(
                        $closingBalance
                    ),
                    'balance_difference' => '0.00',
                    'is_balanced' => true,
                    'reconciled_at' => $statement->reconciled_at,
                ],
            ];
        });
    }

    /**
     * Mengubah nominal decimal menjadi satuan terkecil (sen)
     * tanpa perhitungan floating point.
     */
    private function toMinorUnits(mixed $value): int
    {
        $value = trim((string) $value);

        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'balance' => 'Terdapat nominal dengan format yang tidak valid.',
            ]);
        }

        $negative = str_starts_with($value, '-');

        $unsigned = ltrim($value, '-');
        [$whole, $fraction] = array_pad(
            explode('.', $unsigned, 2),
            2,
            '0'
        );

        $fraction = str_pad($fraction, 2, '0');

        $minorUnits = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        return $negative ? -$minorUnits : $minorUnits;
    }

    private function formatMinorUnits(int $value): string
    {
        $negative = $value < 0;
        $absolute = abs($value);

        $formatted = intdiv($absolute, 100)
            . '.'
            . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-' . $formatted : $formatted;
    }
}