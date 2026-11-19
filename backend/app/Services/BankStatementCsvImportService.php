<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BankStatementCsvImportService
{
    /**
     * Membaca file CSV dan mengubahnya menjadi struktur data
     * yang dapat diproses oleh BankStatementService::importLines().
     */
    public function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibuka.');
        }

        try {
            $delimiter = $this->detectDelimiter($handle);

            rewind($handle);

            $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');

            if ($headers === false || empty($headers)) {
                throw ValidationException::withMessages([
                    'file' => 'File CSV kosong atau tidak memiliki header.',
                ]);
            }

            $headers = $this->normalizeHeaders($headers);
            $columnMap = $this->resolveColumns($headers);

            $rows = [];
            $lineNumber = 1;

            while (($record = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($record)) {
                    continue;
                }

                if (count($record) !== count($headers)) {
                    throw ValidationException::withMessages([
                        'file' => "Jumlah kolom pada baris {$lineNumber} tidak sesuai dengan header CSV.",
                    ]);
                }

                $record = array_map(
                    fn ($value) => is_string($value) ? trim($value) : $value,
                    $record
                );

                $row = [];

                foreach ($columnMap as $field => $index) {
                    $row[$field] = $index !== null
                        ? ($record[$index] ?? null)
                        : null;
                }

                try {
                    $rows[] = $this->normalizeRow($row, $lineNumber);
                } catch (ValidationException $exception) {
                    throw $exception;
                }
            }

            if (empty($rows)) {
                throw ValidationException::withMessages([
                    'file' => 'Tidak ditemukan data transaksi pada file CSV.',
                ]);
            }

            return [
                'rows' => $rows,
                'metadata' => [
                    'file_name' => $file->getClientOriginalName(),
                    'file_format' => 'csv',
                ],
            ];
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter($handle): string
    {
        $firstLine = fgets($handle);

        if ($firstLine === false) {
            return ',';
        }

        $delimiters = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];

        arsort($delimiters);

        return array_key_first($delimiters) ?: ',';
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
            $header = mb_strtolower(trim($header));

            return preg_replace('/[\s\-]+/', '_', $header);
        }, $headers);
    }

    private function resolveColumns(array $headers): array
    {
        $aliases = [
            'transaction_date' => [
                'transaction_date',
                'date',
                'tanggal',
                'tanggal_transaksi',
            ],
            'description' => [
                'description',
                'details',
                'keterangan',
                'deskripsi',
                'uraian',
            ],
            'transaction_type' => [
                'transaction_type',
                'type',
                'jenis',
                'jenis_transaksi',
                'debit_credit',
            ],
            'amount' => [
                'amount',
                'nominal',
                'jumlah',
                'nilai',
            ],
            'running_balance' => [
                'running_balance',
                'balance',
                'saldo',
                'saldo_akhir',
            ],
            'reference_number' => [
                'reference_number',
                'reference',
                'ref',
                'referensi',
                'nomor_referensi',
            ],
        ];

        $map = [];

        foreach ($aliases as $field => $possibleHeaders) {
            $map[$field] = null;

            foreach ($possibleHeaders as $alias) {
                $index = array_search($alias, $headers, true);

                if ($index !== false) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        $required = [
            'transaction_date',
            'description',
            'transaction_type',
            'amount',
        ];

        $missing = [];

        foreach ($required as $field) {
            if ($map[$field] === null) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'file' => 'Header CSV tidak lengkap. Kolom yang wajib tersedia: '
                    . implode(', ', $missing) . '.',
            ]);
        }

        return $map;
    }

    private function normalizeRow(array $row, int $lineNumber): array
    {
        $date = $this->normalizeDate($row['transaction_date']);
        $type = $this->normalizeType($row['transaction_type']);
        $amount = $this->normalizeAmount($row['amount']);

        $data = [
            'transaction_date' => $date,
            'description' => $row['description'],
            'transaction_type' => $type,
            'amount' => $amount,
            'running_balance' => $this->normalizeOptionalAmount(
                $row['running_balance']
            ),
            'reference_number' => $row['reference_number'] ?: null,
        ];

        $validator = Validator::make($data, [
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'transaction_type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'running_balance' => ['nullable', 'numeric'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'file' => "Data CSV pada baris {$lineNumber} tidak valid: "
                    . implode(' ', $validator->errors()->all()),
            ]);
        }

        return $data;
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        $formats = [
            'Y-m-d',
            'Y/m/d',
            'd/m/Y',
            'd-m-Y',
            'd.m.Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);

                if ($date !== false && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Lanjutkan ke format tanggal berikutnya.
            }
        }

        return null;
    }

    private function normalizeType(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return match ($value) {
            'credit', 'cr', 'c', 'kredit', 'in', 'masuk' => 'credit',
            'debit', 'dr', 'd', 'keluar' => 'debit',
            default => null,
        };
    }

    private function normalizeAmount(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        $value = str_replace([' ', 'Rp', 'rp'], '', $value);

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                // Format Indonesia: 1.250.000,50
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                // Format internasional: 1,250,000.50
                $value = str_replace(',', '', $value);
            }
        } elseif ($lastComma !== false) {
            $decimalDigits = strlen($value) - $lastComma - 1;

            if ($decimalDigits > 0 && $decimalDigits <= 2) {
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        }

        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }

    private function normalizeOptionalAmount(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->normalizeAmount($value);
    }

    private function isEmptyRow(array $record): bool
    {
        foreach ($record as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}