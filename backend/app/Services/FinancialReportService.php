<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public function dashboard(array $filters = []): array
    {
        [$from, $to] = $this->resolveDateRange($filters);

        $invoices = DB::table('invoices')
            ->selectRaw('currency, SUM(total_amount) AS total_amount, COUNT(*) AS total_count')
            ->whereDate('issue_date', '>=', $from)
            ->whereDate('issue_date', '<=', $to)
            ->where('status', '!=', 'cancelled')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $payments = DB::table('payment_transactions')
            ->selectRaw('currency, SUM(amount) AS total_amount, COUNT(*) AS total_count')
            ->where('status', 'verified')
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $refunds = DB::table('refunds')
            ->selectRaw('currency, SUM(refund_amount) AS total_amount, COUNT(*) AS total_count')
            ->where('status', 'completed')
            ->whereDate('refund_date', '>=', $from)
            ->whereDate('refund_date', '<=', $to)
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $currencies = collect()
            ->merge($invoices->keys())
            ->merge($payments->keys())
            ->merge($refunds->keys())
            ->unique()
            ->values();

        $financialSummary = $currencies->map(function ($currency) use (
            $invoices,
            $payments,
            $refunds
        ) {
            $invoiceAmount = (float) ($invoices[$currency]->total_amount ?? 0);
            $paymentAmount = (float) ($payments[$currency]->total_amount ?? 0);
            $refundAmount = (float) ($refunds[$currency]->total_amount ?? 0);

            return [
                'currency' => $currency,
                'total_invoiced' => round($invoiceAmount, 2),
                'verified_payments' => round($paymentAmount, 2),
                'completed_refunds' => round($refundAmount, 2),
                'net_receipts' => round($paymentAmount - $refundAmount, 2),
                'invoice_count' => (int) ($invoices[$currency]->total_count ?? 0),
                'verified_payment_count' => (int) ($payments[$currency]->total_count ?? 0),
                'completed_refund_count' => (int) ($refunds[$currency]->total_count ?? 0),
            ];
        })->values();

        $receivables = DB::table('accounts_receivable')
            ->selectRaw('currency, SUM(outstanding_amount) AS total_amount')
            ->groupBy('currency')
            ->get();

        $overdue = DB::table('accounts_receivable')
            ->selectRaw('currency, SUM(outstanding_amount) AS total_amount, COUNT(*) AS total_count')
            ->where('status', 'overdue')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $receivableSummary = $receivables->map(function ($item) use ($overdue) {
            return [
                'currency' => $item->currency,
                'outstanding_amount' => round((float) $item->total_amount, 2),
                'overdue_amount' => round(
                    (float) ($overdue[$item->currency]->total_amount ?? 0),
                    2
                ),
                'overdue_invoice_count' => (int) (
                    $overdue[$item->currency]->total_count ?? 0
                ),
            ];
        })->values();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'financial_summary' => $financialSummary,
            'receivables_summary' => $receivableSummary,
            'pending_verifications' => DB::table('payment_transactions')
                ->where('status', 'pending_verification')
                ->count(),
            'generated_at' => now(),
        ];
    }

    public function paymentReport(array $filters = []): LengthAwarePaginator
    {
        $query = DB::table('payment_transactions')
            ->leftJoin('customers', 'payment_transactions.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'payment_transactions.recorded_by', '=', 'users.id')
            ->select([
                'payment_transactions.id',
                'payment_transactions.transaction_number',
                'payment_transactions.payment_date',
                'payment_transactions.amount',
                'payment_transactions.currency',
                'payment_transactions.payment_method',
                'payment_transactions.payment_channel',
                'payment_transactions.payment_reference',
                'payment_transactions.status',
                'payment_transactions.verified_at',
                'customers.company_name as customer_name',
                'users.name as recorded_by_name',
            ]);

        $this->applyDateFilters($query, 'payment_transactions.payment_date', $filters);

        $query
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('payment_transactions.status', $v))
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('payment_transactions.customer_id', $v))
            ->when($filters['currency'] ?? null, fn ($q, $v) => $q->where('payment_transactions.currency', $v))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('payment_transactions.transaction_number', 'ilike', "%{$search}%")
                        ->orWhere('payment_transactions.payment_reference', 'ilike', "%{$search}%")
                        ->orWhere('customers.company_name', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('payment_transactions.payment_date')
            ->orderByDesc('payment_transactions.id');

        return $query->paginate($this->perPage($filters));
    }

    public function revenueReport(array $filters = []): array
    {
        [$from, $to] = $this->resolveDateRange($filters);

        $payments = DB::table('payment_transactions')
            ->selectRaw("
                TO_CHAR(payment_date, 'YYYY-MM') AS period,
                currency,
                SUM(amount) AS total_amount,
                COUNT(*) AS transaction_count
            ")
            ->where('status', 'verified')
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->groupByRaw("TO_CHAR(payment_date, 'YYYY-MM'), currency")
            ->get();

        $refunds = DB::table('refunds')
            ->selectRaw("
                TO_CHAR(refund_date, 'YYYY-MM') AS period,
                currency,
                SUM(refund_amount) AS total_amount,
                COUNT(*) AS transaction_count
            ")
            ->where('status', 'completed')
            ->whereDate('refund_date', '>=', $from)
            ->whereDate('refund_date', '<=', $to)
            ->groupByRaw("TO_CHAR(refund_date, 'YYYY-MM'), currency")
            ->get();

        $paymentMap = $payments->keyBy(fn ($row) => $row->period . '|' . $row->currency);
        $refundMap = $refunds->keyBy(fn ($row) => $row->period . '|' . $row->currency);

        $currencies = $payments->pluck('currency')
            ->merge($refunds->pluck('currency'))
            ->unique()
            ->values();

        $data = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $lastMonth = Carbon::parse($to)->startOfMonth();

        while ($cursor->lessThanOrEqualTo($lastMonth)) {
            $period = $cursor->format('Y-m');

            foreach ($currencies as $currency) {
                $key = $period . '|' . $currency;
                $paymentAmount = (float) ($paymentMap[$key]->total_amount ?? 0);
                $refundAmount = (float) ($refundMap[$key]->total_amount ?? 0);

                $data[] = [
                    'period' => $period,
                    'currency' => $currency,
                    'total_payments' => round($paymentAmount, 2),
                    'payment_count' => (int) ($paymentMap[$key]->transaction_count ?? 0),
                    'total_refunds' => round($refundAmount, 2),
                    'refund_count' => (int) ($refundMap[$key]->transaction_count ?? 0),
                    'net_receipts' => round($paymentAmount - $refundAmount, 2),
                ];
            }

            $cursor->addMonth();
        }

        $totals = $currencies->map(function ($currency) use ($payments, $refunds) {
            $paymentAmount = (float) $payments
                ->where('currency', $currency)->sum('total_amount');
            $refundAmount = (float) $refunds
                ->where('currency', $currency)->sum('total_amount');

            return [
                'currency' => $currency,
                'payments' => round($paymentAmount, 2),
                'refunds' => round($refundAmount, 2),
                'net_receipts' => round($paymentAmount - $refundAmount, 2),
            ];
        })->values();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'totals' => $totals,
            'data' => $data,
        ];
    }

    public function receivableAgingReport(array $filters = []): array
    {
        $records = DB::table('accounts_receivable')
            ->join('customers', 'accounts_receivable.customer_id', '=', 'customers.id')
            ->join('invoices', 'accounts_receivable.invoice_id', '=', 'invoices.id')
            ->select([
                'accounts_receivable.invoice_id',
                'accounts_receivable.customer_id',
                'accounts_receivable.currency',
                'accounts_receivable.invoice_amount',
                'accounts_receivable.paid_amount',
                'accounts_receivable.outstanding_amount',
                'accounts_receivable.due_date',
                'accounts_receivable.status',
                'customers.company_name as customer_name',
                'invoices.invoice_number',
            ])
            ->where('accounts_receivable.outstanding_amount', '>', 0)
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('accounts_receivable.customer_id', $v))
            ->when($filters['currency'] ?? null, fn ($q, $v) => $q->where('accounts_receivable.currency', $v))
            ->orderBy('accounts_receivable.due_date')
            ->get();

        $today = Carbon::today();
        $buckets = [
            'not_due',
            'overdue_1_30',
            'overdue_31_60',
            'overdue_61_90',
            'overdue_over_90',
        ];

        $summary = [];
        $details = [];

        foreach ($records as $record) {
            $currency = $record->currency;
            $summary[$currency] ??= array_fill_keys($buckets, 0.0);

            $daysOverdue = 0;
            $bucket = 'not_due';

            if ($record->due_date) {
                $dueDate = Carbon::parse($record->due_date)->startOfDay();

                if ($dueDate->lessThan($today)) {
                    $daysOverdue = (int) $dueDate->diffInDays($today);

                    $bucket = match (true) {
                        $daysOverdue <= 30 => 'overdue_1_30',
                        $daysOverdue <= 60 => 'overdue_31_60',
                        $daysOverdue <= 90 => 'overdue_61_90',
                        default => 'overdue_over_90',
                    };
                }
            }

            $summary[$currency][$bucket] += (float) $record->outstanding_amount;

            $details[] = [
                'invoice_id' => $record->invoice_id,
                'invoice_number' => $record->invoice_number,
                'customer_id' => $record->customer_id,
                'customer_name' => $record->customer_name,
                'currency' => $currency,
                'invoice_amount' => (float) $record->invoice_amount,
                'paid_amount' => (float) $record->paid_amount,
                'outstanding_amount' => (float) $record->outstanding_amount,
                'due_date' => $record->due_date,
                'days_overdue' => $daysOverdue,
                'aging_bucket' => $bucket,
                'status' => $record->status,
            ];
        }

        foreach ($summary as $currency => $values) {
            foreach ($values as $bucket => $amount) {
                $summary[$currency][$bucket] = round($amount, 2);
            }

            $summary[$currency]['total_outstanding'] = round(
                array_sum($summary[$currency]),
                2
            );
        }

        return [
            'as_of' => $today->toDateString(),
            'summary' => $summary,
            'total_invoices' => count($details),
            'data' => $details,
        ];
    }

    public function refundReport(array $filters = []): LengthAwarePaginator
    {
        $query = DB::table('refunds')
            ->leftJoin('customers', 'refunds.customer_id', '=', 'customers.id')
            ->leftJoin('payment_transactions', 'refunds.payment_transaction_id', '=', 'payment_transactions.id')
            ->leftJoin('users as requesters', 'refunds.requested_by', '=', 'requesters.id')
            ->leftJoin('users as processors', 'refunds.processed_by', '=', 'processors.id')
            ->select([
                'refunds.id',
                'refunds.refund_number',
                'refunds.refund_date',
                'refunds.refund_amount',
                'refunds.currency',
                'refunds.refund_method',
                'refunds.refund_reference',
                'refunds.status',
                'refunds.reason',
                'refunds.completed_at',
                'customers.company_name as customer_name',
                'payment_transactions.transaction_number as payment_number',
                'requesters.name as requested_by_name',
                'processors.name as processed_by_name',
            ]);

        $this->applyDateFilters($query, 'refunds.refund_date', $filters);

        $query
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('refunds.status', $v))
            ->when($filters['customer_id'] ?? null, fn ($q, $v) => $q->where('refunds.customer_id', $v))
            ->when($filters['currency'] ?? null, fn ($q, $v) => $q->where('refunds.currency', $v))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('refunds.refund_number', 'ilike', "%{$search}%")
                        ->orWhere('refunds.refund_reference', 'ilike', "%{$search}%")
                        ->orWhere('customers.company_name', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('refunds.refund_date')
            ->orderByDesc('refunds.id');

        return $query->paginate($this->perPage($filters));
    }

    public function bankReconciliationReport(array $filters = []): LengthAwarePaginator
    {
        $query = DB::table('bank_statements')
            ->join('bank_accounts', 'bank_statements.bank_account_id', '=', 'bank_accounts.id')
            ->leftJoin('users', 'bank_statements.imported_by', '=', 'users.id')
            ->select([
                'bank_statements.id',
                'bank_statements.statement_number',
                'bank_statements.period_start',
                'bank_statements.period_end',
                'bank_statements.currency',
                'bank_statements.opening_balance',
                'bank_statements.closing_balance',
                'bank_statements.total_rows',
                'bank_statements.matched_rows',
                'bank_statements.unmatched_rows',
                'bank_statements.status',
                'bank_statements.imported_at',
                'bank_statements.reconciled_at',
                'bank_accounts.bank_name',
                'bank_accounts.account_name',
                'bank_accounts.account_code',
                'users.name as imported_by_name',
            ]);

        $query
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('bank_statements.period_start', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('bank_statements.period_end', '<=', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('bank_statements.status', $v))
            ->when($filters['bank_account_id'] ?? null, fn ($q, $v) => $q->where('bank_statements.bank_account_id', $v))
            ->when($filters['currency'] ?? null, fn ($q, $v) => $q->where('bank_statements.currency', $v))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('bank_statements.statement_number', 'ilike', "%{$search}%")
                        ->orWhere('bank_accounts.bank_name', 'ilike', "%{$search}%")
                        ->orWhere('bank_accounts.account_code', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('bank_statements.period_end')
            ->orderByDesc('bank_statements.id');

        return $query->paginate($this->perPage($filters));
    }

    private function applyDateFilters($query, string $column, array $filters): void
    {
        if (!empty($filters['from'])) {
            $query->whereDate($column, '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate($column, '<=', $filters['to']);
        }
    }

    private function resolveDateRange(array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return [$from, $to];
    }

    private function perPage(array $filters): int
    {
        return min(max((int) ($filters['per_page'] ?? 15), 1), 100);
    }
}