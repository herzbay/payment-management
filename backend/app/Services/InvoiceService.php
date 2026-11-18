<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private DocumentNumberingService $documentNumberingService,
        private AuditLogService $auditLogService,
    ) {}

    /**
     * Retrieve invoices with filters and pagination.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with([
                'customer',
                'quotation',
                'agreement',
                'createdBy',
            ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function (Builder $builder) use ($search) {
                $builder->where('invoice_number', 'ilike', "%{$search}%")
                    ->orWhere('subject', 'ilike', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                        $customerQuery->where(
                            'company_name',
                            'ilike',
                            "%{$search}%"
                        );
                    });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['quotation_id'])) {
            $query->where('quotation_id', $filters['quotation_id']);
        }

        if (!empty($filters['issue_date_from'])) {
            $query->whereDate(
                'issue_date',
                '>=',
                $filters['issue_date_from']
            );
        }

        if (!empty($filters['issue_date_to'])) {
            $query->whereDate(
                'issue_date',
                '<=',
                $filters['issue_date_to']
            );
        }

        return $query
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Retrieve a single invoice with its related data.
     */
    public function getById(int $invoiceId): Invoice
    {
        return Invoice::with([
            'customer',
            'quotation.customer',
            'quotation.items.service',
            'agreement',
            'createdBy',
            'items.service',
        ])->findOrFail($invoiceId);
    }

    /**
     * Create an invoice from an approved quotation.
     */
    public function createFromQuotation(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $quotation = Quotation::query()
                ->with(['customer', 'items.service'])
                ->lockForUpdate()
                ->findOrFail($data['quotation_id']);

            if ($quotation->status !== 'approved') {
                throw ValidationException::withMessages([
                    'quotation_id' => [
                        'Invoice hanya dapat dibuat dari quotation yang sudah disetujui.',
                    ],
                ]);
            }

            if (!$quotation->customer || !$quotation->customer->is_active) {
                throw ValidationException::withMessages([
                    'quotation_id' => [
                        'Customer pada quotation tidak aktif atau tidak ditemukan.',
                    ],
                ]);
            }

            if ($quotation->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'quotation_id' => [
                        'Quotation belum memiliki item.',
                    ],
                ]);
            }

            // Prevent duplicate invoices for the same quotation.
            $existingInvoice = Invoice::query()
                ->where('quotation_id', $quotation->id)
                ->lockForUpdate()
                ->exists();

            if ($existingInvoice) {
                throw ValidationException::withMessages([
                    'quotation_id' => [
                        'Quotation ini sudah memiliki invoice.',
                    ],
                ]);
            }

            // Agreement is optional, but must belong to the quotation.
            $agreement = null;

            if (!empty($data['agreement_id'])) {
                $agreement = Agreement::query()
                    ->lockForUpdate()
                    ->findOrFail($data['agreement_id']);

                if ((int) $agreement->quotation_id !== (int) $quotation->id) {
                    throw ValidationException::withMessages([
                        'agreement_id' => [
                            'Agreement tidak sesuai dengan quotation yang dipilih.',
                        ],
                    ]);
                }
            }

            $totals = $this->calculateTotals($quotation);

            $invoice = Invoice::create([
                'invoice_number' => $this->documentNumberingService
                    ->generate('invoice'),

                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'agreement_id' => $agreement?->id,
                'created_by' => Auth::id(),

                'subject' => $data['subject']
                    ?? $quotation->subject,

                'description' => $data['description']
                    ?? $quotation->description,

                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $quotation->currency,

                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_percentage' => $quotation->tax_percentage,
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],

                'status' => 'draft',
                'payment_status' => 'unpaid',

                'notes' => $data['notes'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
            ]);

            // Copy quotation items into invoice items.
            foreach ($quotation->items as $item) {
                $grossAmount = round(
                    (float) $item->quantity * (float) $item->unit_price,
                    2
                );

                $discountAmount = (float) $item->discount_amount;

                $taxableAmount = round(
                    $grossAmount - $discountAmount,
                    2
                );

                $taxAmount = round(
                    $taxableAmount * (float) $item->tax_percentage / 100,
                    2
                );

                $lineTotal = round(
                    $taxableAmount + $taxAmount,
                    2
                );

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'service_id' => $item->service_id,
                    'line_number' => $item->line_number,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $discountAmount,
                    'tax_percentage' => $item->tax_percentage,
                    'line_total' => $lineTotal,
                ]);
            }

            $this->auditLogService->log(
                event: 'created',
                module: 'invoice',
                description: "Invoice {$invoice->invoice_number} dibuat dari quotation {$quotation->quotation_number}.",
                auditable: $invoice,
                newValues: $invoice->toArray(),
            );

            return $this->getById($invoice->id);
        });
    }

    /**
     * Update a draft invoice.
     *
     * Invoice items and financial totals are kept immutable here.
     */
    public function update(int $invoiceId, array $data): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $data) {
            $invoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages([
                    'invoice' => [
                        'Hanya invoice berstatus draft yang dapat diperbarui.',
                    ],
                ]);
            }

            $oldValues = $invoice->only([
                'subject',
                'description',
                'issue_date',
                'due_date',
                'notes',
                'customer_notes',
            ]);

            $invoice->fill([
                'subject' => $data['subject'] ?? $invoice->subject,
                'description' => $data['description'] ?? $invoice->description,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'notes' => $data['notes'] ?? $invoice->notes,
                'customer_notes' => $data['customer_notes']
                    ?? $invoice->customer_notes,
            ]);

            $invoice->save();

            $newValues = $invoice->only([
                'subject',
                'description',
                'issue_date',
                'due_date',
                'notes',
                'customer_notes',
            ]);

            $this->auditLogService->log(
                event: 'updated',
                module: 'invoice',
                description: "Invoice {$invoice->invoice_number} diperbarui.",
                auditable: $invoice,
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return $this->getById($invoice->id);
        });
    }

    /**
     * Calculate invoice totals from quotation items.
     */
    private function calculateTotals(Quotation $quotation): array
    {
        $subtotal = 0;
        $discountAmount = 0;
        $taxAmount = 0;
        $totalAmount = 0;

        foreach ($quotation->items as $item) {
            $grossAmount = round(
                (float) $item->quantity * (float) $item->unit_price,
                2
            );

            $discount = (float) $item->discount_amount;

            $taxableAmount = round(
                $grossAmount - $discount,
                2
            );

            $tax = round(
                $taxableAmount * (float) $item->tax_percentage / 100,
                2
            );

            $lineTotal = round(
                $taxableAmount + $tax,
                2
            );

            $subtotal += $grossAmount;
            $discountAmount += $discount;
            $taxAmount += $tax;
            $totalAmount += $lineTotal;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }
}