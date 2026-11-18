<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Service as ServiceModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly DocumentNumberingService $numberingService,
        private readonly AuditLogService $auditLog
    ) {}

    /**
     * Get paginated quotations with filters.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return Quotation::query()
            ->with(['customer', 'service', 'createdBy'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('quotation_number', 'ilike', "%{$search}%")
                        ->orWhere('subject', 'ilike', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where(
                                'company_name',
                                'ilike',
                                "%{$search}%"
                            );
                        });
                });
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($filters['customer_id'] ?? null, function ($query, $customerId) {
                $query->where('customer_id', $customerId);
            })
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Get quotation detail.
     */
    public function getById(int $id): Quotation
    {
        return Quotation::query()
            ->with([
                'customer',
                'service',
                'createdBy',
                'items.service',
                'terms',
                'approvals.approver',
                'statusHistories.changedBy',
                'agreement',
            ])
            ->findOrFail($id);
    }

    /**
     * Create a new quotation with items and terms.
     */
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::query()
                ->where('is_active', true)
                ->findOrFail($data['customer_id']);

            $items = $data['items'];

            $this->validateActiveServices($items);

            $totals = $this->calculateTotals($items);

            $quotationNumber = $this->numberingService->generate('quotation');

            $quotation = Quotation::create([
                'quotation_number' => $quotationNumber,
                'customer_id' => $customer->id,
                'service_id' => $items[0]['service_id'],
                'created_by' => Auth::id(),
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'],
                'currency' => $data['currency'] ?? 'IDR',
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_percentage' => $data['tax_percentage'] ?? 0,
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'status' => 'draft',
                'internal_notes' => $data['internal_notes'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
            ]);

            foreach ($items as $index => $item) {
                $lineTotal = $this->calculateLineTotal($item);

                $quotation->items()->create([
                    'service_id' => $item['service_id'],
                    'line_number' => $index + 1,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_percentage' => $item['tax_percentage'] ?? 0,
                    'line_total' => $lineTotal,
                ]);
            }

            foreach ($data['terms'] ?? [] as $index => $term) {
                $quotation->terms()->create([
                    'sort_order' => $index + 1,
                    'title' => $term['title'],
                    'content' => $term['content'],
                    'is_required' => $term['is_required'] ?? true,
                ]);
            }

            $quotation->statusHistories()->create([
                'changed_by' => Auth::id(),
                'previous_status' => null,
                'new_status' => 'draft',
                'notes' => 'Quotation created.',
                'changed_at' => now(),
            ]);

            $this->auditLog->created(
                model: $quotation,
                module: 'quotations',
                description: "Quotation {$quotation->quotation_number} was created."
            );

            return $this->getById($quotation->id);
        });
    }

    /**
     * Update a quotation while it is still a draft.
     */
    public function update(int $id, array $data): Quotation
    {
        return DB::transaction(function () use ($id, $data) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($quotation->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [
                        'Only draft quotations can be edited.',
                    ],
                ]);
            }

            if (isset($data['customer_id'])) {
                Customer::query()
                    ->where('is_active', true)
                    ->findOrFail($data['customer_id']);
            }

            $oldValues = $quotation->getAttributes();

            $items = $data['items'] ?? null;

            if ($items !== null) {
                $this->validateActiveServices($items);
                $totals = $this->calculateTotals($items);

                $data['subtotal'] = $totals['subtotal'];
                $data['discount_amount'] = $totals['discount_amount'];
                $data['tax_amount'] = $totals['tax_amount'];
                $data['total_amount'] = $totals['total_amount'];
                $data['service_id'] = $items[0]['service_id'];
            }

            $quotation->fill([
                'customer_id' => $data['customer_id'] ?? $quotation->customer_id,
                'service_id' => $data['service_id'] ?? $quotation->service_id,
                'subject' => $data['subject'] ?? $quotation->subject,
                'description' => $data['description'] ?? $quotation->description,
                'issue_date' => $data['issue_date'] ?? $quotation->issue_date,
                'valid_until' => $data['valid_until'] ?? $quotation->valid_until,
                'currency' => $data['currency'] ?? $quotation->currency,
                'subtotal' => $data['subtotal'] ?? $quotation->subtotal,
                'discount_amount' => $data['discount_amount'] ?? $quotation->discount_amount,
                'tax_percentage' => $data['tax_percentage'] ?? $quotation->tax_percentage,
                'tax_amount' => $data['tax_amount'] ?? $quotation->tax_amount,
                'total_amount' => $data['total_amount'] ?? $quotation->total_amount,
                'internal_notes' => $data['internal_notes'] ?? $quotation->internal_notes,
                'customer_notes' => $data['customer_notes'] ?? $quotation->customer_notes,
            ]);

            $newValues = $quotation->getDirty();

            if ($items !== null) {
                $quotation->items()->delete();

                foreach ($items as $index => $item) {
                    $quotation->items()->create([
                        'service_id' => $item['service_id'],
                        'line_number' => $index + 1,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'],
                        'unit_price' => $item['unit_price'],
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'tax_percentage' => $item['tax_percentage'] ?? 0,
                        'line_total' => $this->calculateLineTotal($item),
                    ]);
                }
            }

            if (array_key_exists('terms', $data)) {
                $quotation->terms()->delete();

                foreach ($data['terms'] ?? [] as $index => $term) {
                    $quotation->terms()->create([
                        'sort_order' => $index + 1,
                        'title' => $term['title'],
                        'content' => $term['content'],
                        'is_required' => $term['is_required'] ?? true,
                    ]);
                }
            }

            if (!empty($newValues)) {
                $quotation->save();
            }

            $newValues = $quotation->getChanges();

            if (!empty($newValues) || $items !== null || array_key_exists('terms', $data)) {
                $this->auditLog->updated(
                    model: $quotation,
                    module: 'quotations',
                    description: "Quotation {$quotation->quotation_number} was updated.",
                    oldValues: array_intersect_key($oldValues, $newValues),
                    newValues: $newValues
                );
            }

            return $this->getById($quotation->id);
        });
    }

    /**
     * Calculate all quotation totals.
     */
    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $totalDiscount = 0;
        $taxableAmount = 0;

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $discount = (float) ($item['discount_amount'] ?? 0);
            $taxPercentage = (float) ($item['tax_percentage'] ?? 0);

            $gross = $quantity * $unitPrice;

            if ($discount < 0 || $discount > $gross) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Item discount must be between zero and its gross amount.',
                    ],
                ]);
            }

            $net = $gross - $discount;

            $subtotal += $gross;
            $totalDiscount += $discount;
            $taxableAmount += $net * ($taxPercentage / 100);
        }

        $taxAmount = round($taxableAmount, 2);
        $totalAmount = round(
            $subtotal - $totalDiscount + $taxAmount,
            2
        );

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($totalDiscount, 2),
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Calculate an individual item total.
     */
    private function calculateLineTotal(array $item): float
    {
        $gross = (float) $item['quantity'] * (float) $item['unit_price'];
        $discount = (float) ($item['discount_amount'] ?? 0);

        if ($discount < 0 || $discount > $gross) {
            throw ValidationException::withMessages([
                'items' => [
                    'Item discount must be between zero and its gross amount.',
                ],
            ]);
        }

        $net = $gross - $discount;
        $tax = $net * ((float) ($item['tax_percentage'] ?? 0) / 100);

        return round($net + $tax, 2);
    }

    /**
     * Ensure all referenced services are active.
     */
    private function validateActiveServices(array $items): void
    {
        $serviceIds = collect($items)
            ->pluck('service_id')
            ->unique()
            ->values();

        $activeCount = ServiceModel::query()
            ->whereIn('id', $serviceIds)
            ->where('is_active', true)
            ->count();

        if ($activeCount !== $serviceIds->count()) {
            throw ValidationException::withMessages([
                'items' => [
                    'One or more selected services do not exist or are inactive.',
                ],
            ]);
        }
    }
}