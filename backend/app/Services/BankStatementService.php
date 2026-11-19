<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BankStatementService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Get paginated bank statements.
     */
    public function getAll(array $filters = [])
    {
        $query = BankStatement::query()
            ->with(['bankAccount', 'importedBy']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function (Builder $builder) use ($search) {
                $builder->where(
                    'statement_number',
                    'ilike',
                    "%{$search}%"
                )->orWhereHas('bankAccount', function (Builder $accountQuery) use ($search) {
                    $accountQuery
                        ->where('bank_name', 'ilike', "%{$search}%")
                        ->orWhere('account_name', 'ilike', "%{$search}%")
                        ->orWhere('account_number', 'ilike', "%{$search}%");
                });
            });
        }

        if (!empty($filters['bank_account_id'])) {
            $query->where(
                'bank_account_id',
                $filters['bank_account_id']
            );
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['period_start'])) {
            $query->whereDate(
                'period_start',
                '>=',
                $filters['period_start']
            );
        }

        if (!empty($filters['period_end'])) {
            $query->whereDate(
                'period_end',
                '<=',
                $filters['period_end']
            );
        }

        if (
            isset($filters['year']) &&
            $filters['year'] !== ''
        ) {
            $query->whereYear(
                'period_start',
                $filters['year']
            );
        }

        $perPage = min(
            max((int) ($filters['per_page'] ?? 15), 1),
            100
        );

        return $query
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Get bank statement detail.
     */
    public function getById(int $id): BankStatement
    {
        return BankStatement::query()
            ->with([
                'bankAccount',
                'importedBy',
                'lines' => function ($query) {
                    $query->orderBy('line_number');
                },
                'lines.paymentTransaction',
                'lines.refund',
                'lines.matchedBy',
            ])
            ->findOrFail($id);
    }

    /**
     * Create a bank statement.
     */
    public function create(array $data): BankStatement
    {
        return DB::transaction(function () use ($data) {
            $bankAccount = BankAccount::query()
                ->lockForUpdate()
                ->findOrFail($data['bank_account_id']);

            if (!$bankAccount->is_active) {
                throw ValidationException::withMessages([
                    'bank_account_id' => [
                        'Rekening bank yang dipilih sedang tidak aktif.',
                    ],
                ]);
            }

            if (
                isset($data['currency']) &&
                $bankAccount->currency !== $data['currency']
            ) {
                throw ValidationException::withMessages([
                    'currency' => [
                        'Mata uang statement harus sama dengan mata uang rekening bank.',
                    ],
                ]);
            }

            $periodStart = Carbon::parse($data['period_start']);
            $periodEnd = Carbon::parse($data['period_end']);

            if ($periodStart->gt($periodEnd)) {
                throw ValidationException::withMessages([
                    'period_end' => [
                        'Tanggal akhir periode tidak boleh lebih awal dari tanggal awal.',
                    ],
                ]);
            }

            $statement = BankStatement::create([
                'statement_number' => $data['statement_number'],
                'bank_account_id' => $bankAccount->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'currency' => $bankAccount->currency,
                'opening_balance' => $data['opening_balance'] ?? 0,
                'closing_balance' => $data['closing_balance'] ?? 0,
                'file_name' => $data['file_name'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'file_format' => $data['file_format'] ?? null,
                'total_rows' => 0,
                'matched_rows' => 0,
                'unmatched_rows' => 0,
                'status' => 'draft',
                'imported_by' => null,
                'imported_at' => null,
                'reconciled_at' => null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLogService->log(
                event: 'created',
                module: 'bank_reconciliation',
                description: "Bank statement {$statement->statement_number} berhasil dibuat.",
                auditable: $statement,
                newValues: $statement->toArray(),
            );

            return $statement->load([
                'bankAccount',
                'importedBy',
            ]);
        });
    }

    /**
     * Import transaction rows into a bank statement.
     *
     * Expected row format:
     * [
     *     'transaction_date' => '2026-11-01',
     *     'description' => 'Customer payment',
     *     'transaction_type' => 'credit',
     *     'amount' => 1500000,
     *     'running_balance' => 5000000,
     *     'reference_number' => 'TRX-001',
     * ]
     */
    public function importLines(
        int $statementId,
        array $rows,
        array $fileMetadata = []
    ): BankStatement {
        if (empty($rows)) {
            throw ValidationException::withMessages([
                'file' => [
                    'File statement tidak memiliki baris transaksi.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $statementId,
            $rows,
            $fileMetadata
        ) {
            $statement = BankStatement::query()
                ->lockForUpdate()
                ->findOrFail($statementId);

            if ($statement->status !== 'draft') {
                throw ValidationException::withMessages([
                    'statement' => [
                        'Import hanya dapat dilakukan pada statement berstatus draft.',
                    ],
                ]);
            }

            if ($statement->lines()->exists()) {
                throw ValidationException::withMessages([
                    'statement' => [
                        'Statement sudah memiliki baris transaksi. Import ulang tidak diperbolehkan.',
                    ],
                ]);
            }

            $periodStart = Carbon::parse(
                $statement->period_start
            )->startOfDay();

            $periodEnd = Carbon::parse(
                $statement->period_end
            )->endOfDay();

            $now = now();
            $lineData = [];

            foreach ($rows as $index => $row) {
                $lineNumber = $index + 1;

                if (empty($row['transaction_date'])) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.transaction_date" => [
                            "Tanggal transaksi pada baris {$lineNumber} wajib diisi.",
                        ],
                    ]);
                }

                try {
                    $transactionDate = Carbon::parse(
                        $row['transaction_date']
                    );
                } catch (Throwable) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.transaction_date" => [
                            "Tanggal transaksi pada baris {$lineNumber} tidak valid.",
                        ],
                    ]);
                }

                if (
                    $transactionDate->lt($periodStart) ||
                    $transactionDate->gt($periodEnd)
                ) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.transaction_date" => [
                            "Tanggal transaksi pada baris {$lineNumber} berada di luar periode statement.",
                        ],
                    ]);
                }

                $description = trim(
                    (string) ($row['description'] ?? '')
                );

                if ($description === '') {
                    throw ValidationException::withMessages([
                        "rows.{$index}.description" => [
                            "Deskripsi transaksi pada baris {$lineNumber} wajib diisi.",
                        ],
                    ]);
                }

                $transactionType = $this->normalizeTransactionType(
                    (string) ($row['transaction_type'] ?? '')
                );

                if (!$transactionType) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.transaction_type" => [
                            "Tipe transaksi pada baris {$lineNumber} harus credit atau debit.",
                        ],
                    ]);
                }

                if (
                    !isset($row['amount']) ||
                    !is_numeric($row['amount']) ||
                    (float) $row['amount'] <= 0
                ) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.amount" => [
                            "Nominal transaksi pada baris {$lineNumber} harus lebih besar dari 0.",
                        ],
                    ]);
                }

                $runningBalance = $row['running_balance'] ?? null;

                if (
                    $runningBalance !== null &&
                    $runningBalance !== '' &&
                    !is_numeric($runningBalance)
                ) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.running_balance" => [
                            "Saldo berjalan pada baris {$lineNumber} tidak valid.",
                        ],
                    ]);
                }

                $lineData[] = [
                    'bank_statement_id' => $statement->id,
                    'line_number' => $lineNumber,
                    'transaction_date' => $transactionDate->toDateString(),
                    'description' => $description,
                    'transaction_type' => $transactionType,
                    'amount' => $row['amount'],
                    'running_balance' => (
                        $runningBalance === '' ? null : $runningBalance
                    ),
                    'reference_number' => (
                        !empty($row['reference_number'])
                            ? trim((string) $row['reference_number'])
                            : null
                    ),
                    'match_status' => 'unmatched',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($lineData, 500) as $chunk) {
                BankStatementLine::insert($chunk);
            }

            $totalRows = count($lineData);

            $oldValues = [
                'total_rows' => $statement->total_rows,
                'matched_rows' => $statement->matched_rows,
                'unmatched_rows' => $statement->unmatched_rows,
                'file_name' => $statement->file_name,
                'file_path' => $statement->file_path,
                'file_format' => $statement->file_format,
            ];

            $statement->update([
                'file_name' => $fileMetadata['file_name']
                    ?? $statement->file_name,
                'file_path' => $fileMetadata['file_path']
                    ?? $statement->file_path,
                'file_format' => $fileMetadata['file_format']
                    ?? $statement->file_format,
                'total_rows' => $totalRows,
                'matched_rows' => 0,
                'unmatched_rows' => $totalRows,
                'imported_by' => auth()->id(),
                'imported_at' => now(),
            ]);

            $this->auditLogService->log(
                event: 'imported',
                module: 'bank_reconciliation',
                description: "{$totalRows} baris transaksi berhasil diimpor ke statement {$statement->statement_number}.",
                auditable: $statement,
                oldValues: $oldValues,
                newValues: [
                    'total_rows' => $statement->total_rows,
                    'matched_rows' => $statement->matched_rows,
                    'unmatched_rows' => $statement->unmatched_rows,
                    'file_name' => $statement->file_name,
                    'file_path' => $statement->file_path,
                    'file_format' => $statement->file_format,
                ],
                properties: [
                    'imported_rows' => $totalRows,
                ],
            );

            return $statement->load([
                'bankAccount',
                'importedBy',
                'lines',
            ]);
        });
    }

    /**
     * Normalize common bank transaction type labels.
     */
    protected function normalizeTransactionType(string $type): ?string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'credit', 'cr', 'c', 'kredit', 'in', 'masuk' => 'credit',
            'debit', 'dr', 'd', 'out', 'keluar' => 'debit',
            default => null,
        };
    }
}
