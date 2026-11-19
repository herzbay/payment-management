<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountReceivableService
{
    /**
     * Get paginated receivables.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = AccountReceivable::query()
            ->with([
                'invoice',
                'customer',
            ]);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->whereHas(
                        'invoice',
                        fn (Builder $invoiceQuery) => $invoiceQuery
                            ->where('invoice_number', 'ilike', "%{$search}%")
                    )
                    ->orWhereHas(
                        'customer',
                        fn (Builder $customerQuery) => $customerQuery
                            ->where('company_name', 'ilike', "%{$search}%")
                    );
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_date_from']);
        }

        if (!empty($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_date_to']);
        }

        if (!empty($filters['aging_bucket'])) {
            $this->applyAgingFilter($query, $filters['aging_bucket']);
        }

        return $query
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get receivable detail.
     */
    public function getById(int $id): AccountReceivable
    {
        return AccountReceivable::query()
            ->with([
                'invoice.customer',
                'invoice.items.service',
                'customer',
            ])
            ->findOrFail($id);
    }

    /**
     * Create or synchronize receivable data from an invoice.
     *
     * Paid amount is calculated from verified payment allocations only.
     */
    public function syncFromInvoice(Invoice $invoice): AccountReceivable
    {
        return DB::transaction(function () use ($invoice) {
            $lockedInvoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $paidAmount = DB::table('payment_allocations as pa')
                ->join(
                    'payment_transactions as pt',
                    'pt.id',
                    '=',
                    'pa.payment_transaction_id'
                )
                ->where('pa.invoice_id', $lockedInvoice->id)
                ->where('pt.status', 'verified')
                ->sum('pa.allocated_amount');

            $invoiceMinor = $this->toMinorUnits(
                $lockedInvoice->total_amount
            );

            $paidMinor = $this->toMinorUnits($paidAmount ?? 0);

            $outstandingMinor = max(0, $invoiceMinor - $paidMinor);

            if ($paidMinor === 0) {
                $status = now()->toDateString() > $lockedInvoice->due_date->toDateString()
                    ? 'overdue'
                    : 'open';
            } elseif ($paidMinor < $invoiceMinor) {
                $status = now()->toDateString() > $lockedInvoice->due_date->toDateString()
                    ? 'overdue'
                    : 'open';
            } elseif ($paidMinor === $invoiceMinor) {
                $status = 'paid';
            } else {
                $status = 'overpaid';
            }

            $lastPaymentDate = DB::table('payment_transactions as pt')
                ->join(
                    'payment_allocations as pa',
                    'pa.payment_transaction_id',
                    '=',
                    'pt.id'
                )
                ->where('pa.invoice_id', $lockedInvoice->id)
                ->where('pt.status', 'verified')
                ->max('pt.payment_date');

            $receivable = AccountReceivable::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->lockForUpdate()
                ->first();

            $values = [
                'customer_id' => $lockedInvoice->customer_id,
                'currency' => $lockedInvoice->currency,
                'invoice_amount' => $this->fromMinorUnits($invoiceMinor),
                'paid_amount' => $this->fromMinorUnits($paidMinor),
                'outstanding_amount' => $this->fromMinorUnits($outstandingMinor),
                'due_date' => $lockedInvoice->due_date,
                'status' => $status,
                'last_payment_date' => $lastPaymentDate,
            ];

            if ($receivable) {
                $receivable->update($values);
            } else {
                $receivable = AccountReceivable::create([
                    'invoice_id' => $lockedInvoice->id,
                    ...$values,
                ]);
            }

            return $receivable->load([
                'invoice',
                'customer',
            ]);
        });
    }

    /**
     * Return summary totals for receivables.
     */
    public function getSummary(): array
    {
        $receivables = AccountReceivable::query();

        return [
            'total_receivables' => (clone $receivables)->count(),
            'total_invoice_amount' => (clone $receivables)->sum('invoice_amount'),
            'total_paid_amount' => (clone $receivables)->sum('paid_amount'),
            'total_outstanding_amount' => (clone $receivables)->sum('outstanding_amount'),
            'open_count' => (clone $receivables)->where('status', 'open')->count(),
            'overdue_count' => (clone $receivables)->where('status', 'overdue')->count(),
            'paid_count' => (clone $receivables)->where('status', 'paid')->count(),
            'overpaid_count' => (clone $receivables)->where('status', 'overpaid')->count(),
        ];
    }

    /**
     * Return outstanding balances grouped by aging bucket.
     */
    public function getAgingReport(): array
    {
        $today = now()->toDateString();

        $baseQuery = AccountReceivable::query()
            ->where('outstanding_amount', '>', 0)
            ->whereDate('due_date', '<', $today);

        $buckets = [
            '1_30_days' => [1, 30],
            '31_60_days' => [31, 60],
            '61_90_days' => [61, 90],
        ];

        $report = [
            'current' => $this->agingRow(
                AccountReceivable::query()
                    ->where('outstanding_amount', '>', 0)
                    ->whereDate('due_date', '>=', $today)
            ),
        ];

        foreach ($buckets as $key => [$minimum, $maximum]) {
            $report[$key] = $this->agingRow(
                (clone $baseQuery)
                    ->whereDate(
                        'due_date',
                        '>=',
                        now()->subDays($maximum)->toDateString()
                    )
                    ->whereDate(
                        'due_date',
                        '<=',
                        now()->subDays($minimum)->toDateString()
                    )
            );
        }

        $report['over_90_days'] = $this->agingRow(
            (clone $baseQuery)
                ->whereDate(
                    'due_date',
                    '<',
                    now()->subDays(90)->toDateString()
                )
        );

        return $report;
    }

    /**
     * Apply aging bucket filter to a receivable query.
     */
    private function applyAgingFilter(
        Builder $query,
        string $bucket
    ): void {
        $today = now()->toDateString();

        $query->where('outstanding_amount', '>', 0);

        match ($bucket) {
            'current' => $query->whereDate('due_date', '>=', $today),

            '1_30_days' => $query
                ->whereDate('due_date', '<', $today)
                ->whereDate('due_date', '>=', now()->subDays(30)->toDateString()),

            '31_60_days' => $query
                ->whereDate('due_date', '<', now()->subDays(30)->toDateString())
                ->whereDate('due_date', '>=', now()->subDays(60)->toDateString()),

            '61_90_days' => $query
                ->whereDate('due_date', '<', now()->subDays(60)->toDateString())
                ->whereDate('due_date', '>=', now()->subDays(90)->toDateString()),

            'over_90_days' => $query
                ->whereDate('due_date', '<', now()->subDays(90)->toDateString()),

            default => throw ValidationException::withMessages([
                'aging_bucket' => ['Kategori aging piutang tidak valid.'],
            ]),
        };
    }

    /**
     * Build an aging summary row.
     */
    private function agingRow(Builder $query): array
    {
        return [
            'count' => (clone $query)->count(),
            'outstanding_amount' => (clone $query)->sum('outstanding_amount'),
        ];
    }

    /**
     * Convert decimal money to integer minor units.
     */
    private function toMinorUnits(string|int|float $amount): int
    {
        $value = trim((string) $amount);

        if (!preg_match('/^\d{1,16}(\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => ['Format nominal tidak valid.'],
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

    /**
     * Convert integer minor units to a decimal string.
     */
    private function fromMinorUnits(int $amount): string
    {
        return intdiv($amount, 100)
            . '.'
            . str_pad((string) ($amount % 100), 2, '0');
    }
}
