<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountReceivableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountReceivableController extends Controller
{
    public function __construct(
        private AccountReceivableService $accountReceivableService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                Rule::in(['open', 'overdue', 'paid', 'overpaid']),
            ],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => [
                'nullable',
                'date',
                'after_or_equal:due_date_from',
            ],
            'aging_bucket' => [
                'nullable',
                Rule::in([
                    'current',
                    '1_30_days',
                    '31_60_days',
                    '61_90_days',
                    'over_90_days',
                ]),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $receivables = $this->accountReceivableService->getAll($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data accounts receivable berhasil diambil.',
            'data' => $receivables,
        ]);
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Ringkasan accounts receivable berhasil diambil.',
            'data' => $this->accountReceivableService->getSummary(),
        ]);
    }

    public function aging(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Laporan aging piutang berhasil diambil.',
            'data' => $this->accountReceivableService->getAgingReport(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $receivable = $this->accountReceivableService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail accounts receivable berhasil diambil.',
            'data' => $receivable,
        ]);
    }
}
