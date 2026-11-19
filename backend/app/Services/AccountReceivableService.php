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
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Total pembayaran yang sudah terverifikasi.
            $verifiedPayments = DB::table('payment_allocations as pa')
                ->join(
                    'payment_transactions as pt',
                    'pt.id',
                    '=',
                    'pa.payment_transaction_id'
                )
                ->where('pa.invoice_id', $lockedInvoice->id)
                ->where('pt.status', 'verified')
                ->sum('pa.allocated_amount');

            // 2. Total refund yang sudah selesai, berdasarkan alokasi invoice.
            $completedRefunds = DB::table('refund_items as ri')
                ->join('refunds as r', 'r.id', '=', 'ri.refund_id')
                ->join(
                    'payment_allocations as pa',
                    'pa.id',
                    '=',
                    'ri.payment_allocation_id'
                )
                ->where('pa.invoice_id', $lockedInvoice->id)
                ->where('r.status', 'completed')
                ->sum('ri.refund_amount');

            // 3. Adjustment yang sudah diterapkan.
            $decreaseAdjustments = DB::table('adjustments')
                ->where('invoice_id', $lockedInvoice->id)
                ->where('status', 'applied')
                ->where('direction', 'decrease')
                ->sum('amount');

            $increaseAdjustments = DB::table('adjustments')
                ->where('invoice_id', $lockedInvoice->id)
                ->where('status', 'applied')
                ->where('direction', 'increase')
                ->sum('amount');

            // 4. Semua perhitungan uang dilakukan dalam satuan minor (sen).
            $originalInvoiceAmount = $this->toMinorUnits(
                $lockedInvoice->total_amount
            );

            $decreaseAmount = $this->toMinorUnits($decreaseAdjustments);
            $increaseAmount = $this->toMinorUnits($increaseAdjustments);

            $effectiveInvoiceAmount = $originalInvoiceAmount
                + $increaseAmount
                - $decreaseAmount;

            if ($effectiveInvoiceAmount < 0) {
                throw new \RuntimeException(
                    'Total adjustment tidak boleh membuat nilai invoice negatif.'
                );
            }

            $grossPaidAmount = $this->toMinorUnits($verifiedPayments);
            $refundedAmount = $this->toMinorUnits($completedRefunds);

            if ($refundedAmount > $grossPaidAmount) {
                throw new \RuntimeException(
                    'Total refund selesai melebihi pembayaran terverifikasi untuk invoice ini.'
                );
            }

            $netPaidAmount = $grossPaidAmount - $refundedAmount;

            $outstandingAmount = max(
                0,
                $effectiveInvoiceAmount - $netPaidAmount
            );

            // 5. Tentukan status piutang.
            if ($netPaidAmount > $effectiveInvoiceAmount) {
                $status = 'overpaid';
                $paymentStatus = 'overpaid';
            } elseif ($outstandingAmount === 0) {
                $status = 'paid';
                $paymentStatus = 'paid';
            } else {
                $isOverdue = $lockedInvoice->due_date
                    && today()->gt($lockedInvoice->due_date);

                $status = $isOverdue ? 'overdue' : 'open';

                $paymentStatus = $netPaidAmount > 0
                    ? 'partially_paid'
                    : 'unpaid';
            }

            // 6. Tanggal pembayaran terakhir yang terverifikasi.
            $lastPaymentDate = DB::table('payment_allocations as pa')
                ->join(
                    'payment_transactions as pt',
                    'pt.id',
                    '=',
                    'pa.payment_transaction_id'
                )
                ->where('pa.invoice_id', $lockedInvoice->id)
                ->where('pt.status', 'verified')
                ->max('pt.payment_date');

            // 7. Simpan saldo Accounts Receivable.
            $receivable = AccountReceivable::query()->updateOrCreate(
                ['invoice_id' => $lockedInvoice->id],
                [
                    'customer_id' => $lockedInvoice->customer_id,
                    'currency' => $lockedInvoice->currency,
                    'invoice_amount' => $this->fromMinorUnits(
                        $effectiveInvoiceAmount
                    ),
                    'paid_amount' => $this->fromMinorUnits(
                        $netPaidAmount
                    ),
                    'outstanding_amount' => $this->fromMinorUnits(
                        $outstandingAmount
                    ),
                    'due_date' => $lockedInvoice->due_date,
                    'status' => $status,
                    'last_payment_date' => $lastPaymentDate,
                ]
            );

            // Payment status invoice ikut disinkronkan.
            $lockedInvoice->update([
                'payment_status' => $paymentStatus,
            ]);

            return $receivable->fresh([
                'invoice.customer',
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
