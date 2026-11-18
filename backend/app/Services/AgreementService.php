<?php

namespace App\Services;

use App\Models\Agreement;
use App\Models\Quotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgreementService
{
    public function __construct(
        private readonly DocumentNumberingService $numberingService,
        private readonly AuditLogService $auditLog
    ) {}

    /**
     * Menampilkan daftar agreement.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return Agreement::query()
            ->with([
                'quotation.customer',
                'createdBy',
            ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('agreement_number', 'ilike', "%{$search}%")
                        ->orWhere('title', 'ilike', "%{$search}%")
                        ->orWhereHas('quotation', function ($quotationQuery) use ($search) {
                            $quotationQuery
                                ->where('quotation_number', 'ilike', "%{$search}%")
                                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                                    $customerQuery->where(
                                        'company_name',
                                        'ilike',
                                        "%{$search}%"
                                    );
                                });
                        });
                });
            })
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Mengambil detail agreement.
     */
    public function getById(int $id): Agreement
    {
        return Agreement::query()
            ->with([
                'quotation.customer',
                'quotation.service',
                'quotation.items.service',
                'createdBy',
            ])
            ->findOrFail($id);
    }

    /**
     * Membuat agreement dari quotation yang telah disetujui.
     */
    public function create(array $data): Agreement
    {
        return DB::transaction(function () use ($data) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($data['quotation_id']);

            if ($quotation->status !== 'approved') {
                throw ValidationException::withMessages([
                    'quotation_id' => [
                        'Agreement hanya dapat dibuat dari quotation yang telah disetujui.',
                    ],
                ]);
            }

            if ($quotation->agreement()->exists()) {
                throw ValidationException::withMessages([
                    'quotation_id' => [
                        'Quotation ini sudah memiliki agreement.',
                    ],
                ]);
            }

            $agreementNumber = $this->numberingService->generate('agreement');

            $agreement = Agreement::create([
                'agreement_number' => $agreementNumber,
                'quotation_id' => $quotation->id,
                'created_by' => Auth::id(),
                'title' => $data['title'],
                'issue_date' => $data['issue_date'],
                'effective_date' => $data['effective_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'status' => 'draft',
                'customer_signatory' => $data['customer_signatory'] ?? null,
                'company_signatory' => $data['company_signatory'] ?? null,
                'document_path' => $data['document_path'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLog->created(
                model: $agreement,
                module: 'agreements',
                description: "Agreement {$agreement->agreement_number} was created.",
                properties: [
                    'quotation_id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                ]
            );

            return $this->getById($agreement->id);
        });
    }

    /**
     * Mengubah agreement yang masih berstatus draft.
     */
    public function update(int $id, array $data): Agreement
    {
        return DB::transaction(function () use ($id, $data) {
            $agreement = Agreement::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($agreement->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [
                        'Hanya agreement dengan status draft yang dapat diubah.',
                    ],
                ]);
            }

            $oldValues = $agreement->getAttributes();

            $agreement->fill([
                'title' => $data['title'] ?? $agreement->title,
                'issue_date' => $data['issue_date'] ?? $agreement->issue_date,
                'effective_date' => array_key_exists('effective_date', $data)
                    ? $data['effective_date']
                    : $agreement->effective_date,
                'end_date' => array_key_exists('end_date', $data)
                    ? $data['end_date']
                    : $agreement->end_date,
                'customer_signatory' => array_key_exists('customer_signatory', $data)
                    ? $data['customer_signatory']
                    : $agreement->customer_signatory,
                'company_signatory' => array_key_exists('company_signatory', $data)
                    ? $data['company_signatory']
                    : $agreement->company_signatory,
                'document_path' => array_key_exists('document_path', $data)
                    ? $data['document_path']
                    : $agreement->document_path,
                'notes' => array_key_exists('notes', $data)
                    ? $data['notes']
                    : $agreement->notes,
            ]);

            $changes = $agreement->getDirty();

            if (!empty($changes)) {
                $agreement->save();

                $newValues = $agreement->getChanges();

                $this->auditLog->updated(
                    model: $agreement,
                    module: 'agreements',
                    description: "Agreement {$agreement->agreement_number} was updated.",
                    oldValues: array_intersect_key($oldValues, $newValues),
                    newValues: $newValues
                );
            }

            return $this->getById($agreement->id);
        });
    }
}