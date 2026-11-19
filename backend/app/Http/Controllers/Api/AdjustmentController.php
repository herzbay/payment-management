<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdjustmentController extends Controller
{
    public function __construct(
        private AdjustmentService $adjustmentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                Rule::in([
                    'pending_approval',
                    'approved',
                    'rejected',
                    'applied',
                    'cancelled',
                ]),
            ],
            'adjustment_type' => [
                'nullable',
                Rule::in([
                    'credit_note',
                    'debit_note',
                    'discount',
                    'write_off',
                    'correction',
                ]),
            ],
            'direction' => [
                'nullable',
                Rule::in(['decrease', 'increase']),
            ],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'adjustment_date_from' => ['nullable', 'date'],
            'adjustment_date_to' => [
                'nullable',
                'date',
                'after_or_equal:adjustment_date_from',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->adjustmentService->getAll($filters)
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            $this->adjustmentService->getById($id)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => [
                'required',
                'integer',
                'exists:invoices,id',
            ],
            'approver_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'adjustment_type' => [
                'required',
                Rule::in([
                    'credit_note',
                    'debit_note',
                    'discount',
                    'write_off',
                    'correction',
                ]),
            ],
            'direction' => [
                'required',
                Rule::in(['decrease', 'increase']),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'adjustment_date' => ['nullable', 'date'],
            'reason' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $adjustment = $this->adjustmentService->create($data);

        return response()->json([
            'message' => 'Adjustment berhasil dibuat dan menunggu persetujuan.',
            'data' => $adjustment,
        ], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'comments' => ['nullable', 'string'],
        ]);

        $adjustment = $this->adjustmentService->approve(
            $id,
            $data['comments'] ?? null
        );

        return response()->json([
            'message' => 'Adjustment berhasil disetujui.',
            'data' => $adjustment,
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $adjustment = $this->adjustmentService->reject(
            $id,
            $data['reason']
        );

        return response()->json([
            'message' => 'Adjustment berhasil ditolak.',
            'data' => $adjustment,
        ]);
    }

    public function apply(int $id): JsonResponse
    {
        $adjustment = $this->adjustmentService->apply($id);

        return response()->json([
            'message' => 'Adjustment berhasil diterapkan.',
            'data' => $adjustment,
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $adjustment = $this->adjustmentService->cancel(
            $id,
            $data['reason']
        );

        return response()->json([
            'message' => 'Adjustment berhasil dibatalkan.',
            'data' => $adjustment,
        ]);
    }
}
