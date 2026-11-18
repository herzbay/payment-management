<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationApproval;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class QuotationWorkflowService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    /**
     * Mengajukan quotation untuk mendapatkan persetujuan.
     */
    public function submitForApproval(
        int $quotationId,
        int $approverId
    ): Quotation {
        return DB::transaction(function () use ($quotationId, $approverId) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($quotationId);

            if ($quotation->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => [
                        'Hanya quotation dengan status draft yang dapat diajukan untuk persetujuan.',
                    ],
                ]);
            }

            $approver = User::query()
                ->with('role')
                ->findOrFail($approverId);

            if (!$approver->role || !$approver->role->is_active) {
                throw ValidationException::withMessages([
                    'approver_id' => [
                        'Approver harus memiliki role yang aktif.',
                    ],
                ]);
            }

            $oldValues = [
                'status' => $quotation->status,
                'sent_at' => $quotation->sent_at,
            ];

            $now = now();

            $quotation->update([
                'status' => 'pending_approval',
                'sent_at' => $now,
            ]);

            $quotation->approvals()->create([
                'approver_id' => $approver->id,
                'approval_level' => 1,
                'status' => 'pending',
                'assigned_at' => $now,
            ]);

            $this->recordStatusHistory(
                quotation: $quotation,
                previousStatus: 'draft',
                newStatus: 'pending_approval',
                notes: 'Quotation submitted for approval.'
            );

            $this->auditLog->updated(
                model: $quotation,
                module: 'quotations',
                description: "Quotation {$quotation->quotation_number} was submitted for approval.",
                oldValues: $oldValues,
                newValues: [
                    'status' => 'pending_approval',
                    'sent_at' => $now->toDateTimeString(),
                ]
            );

            return $this->getQuotation($quotation->id);
        });
    }

    /**
     * Menyetujui quotation yang ditugaskan kepada pengguna saat ini.
     */
    public function approve(
        int $quotationId,
        ?string $comments = null
    ): Quotation {
        return $this->decide(
            quotationId: $quotationId,
            decision: 'approved',
            comments: $comments
        );
    }

    /**
     * Menolak quotation yang ditugaskan kepada pengguna saat ini.
     */
    public function reject(
        int $quotationId,
        string $comments
    ): Quotation {
        return $this->decide(
            quotationId: $quotationId,
            decision: 'rejected',
            comments: $comments
        );
    }

    /**
     * Memproses keputusan persetujuan atau penolakan.
     */
    private function decide(
        int $quotationId,
        string $decision,
        ?string $comments
    ): Quotation {
        return DB::transaction(function () use (
            $quotationId,
            $decision,
            $comments
        ) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($quotationId);

            if ($quotation->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'status' => [
                        'Quotation tidak sedang menunggu persetujuan.',
                    ],
                ]);
            }

            $approval = QuotationApproval::query()
                ->where('quotation_id', $quotation->id)
                ->where('approval_level', 1)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$approval) {
                throw ValidationException::withMessages([
                    'approval' => [
                        'Data persetujuan quotation tidak ditemukan atau sudah diproses.',
                    ],
                ]);
            }

            if ((int) $approval->approver_id !== (int) Auth::id()) {
                throw new AuthorizationException(
                    'Anda tidak memiliki wewenang untuk memproses persetujuan quotation ini.'
                );
            }

            $now = now();

            $approval->update([
                'status' => $decision,
                'comments' => $comments,
                'decided_at' => $now,
            ]);

            $oldValues = [
                'status' => $quotation->status,
                'approved_at' => $quotation->approved_at,
                'rejected_at' => $quotation->rejected_at,
            ];

            $quotation->update([
                'status' => $decision,
                'approved_at' => $decision === 'approved' ? $now : null,
                'rejected_at' => $decision === 'rejected' ? $now : null,
            ]);

            $this->recordStatusHistory(
                quotation: $quotation,
                previousStatus: 'pending_approval',
                newStatus: $decision,
                notes: $comments
            );

            $this->auditLog->updated(
                model: $quotation,
                module: 'quotations',
                description: "Quotation {$quotation->quotation_number} was {$decision}.",
                oldValues: $oldValues,
                newValues: [
                    'status' => $decision,
                    'approved_at' => $decision === 'approved'
                        ? $now->toDateTimeString()
                        : null,
                    'rejected_at' => $decision === 'rejected'
                        ? $now->toDateTimeString()
                        : null,
                ],
                properties: [
                    'approval_id' => $approval->id,
                    'approval_level' => $approval->approval_level,
                    'approver_id' => Auth::id(),
                    'comments' => $comments,
                ]
            );

            return $this->getQuotation($quotation->id);
        });
    }

    /**
     * Menyimpan riwayat perubahan status quotation.
     */
    private function recordStatusHistory(
        Quotation $quotation,
        ?string $previousStatus,
        string $newStatus,
        ?string $notes = null
    ): void {
        $quotation->statusHistories()->create([
            'changed_by' => Auth::id(),
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'changed_at' => now(),
        ]);
    }

    /**
     * Mengambil quotation beserta relasi yang dibutuhkan.
     */
    private function getQuotation(int $id): Quotation
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
}