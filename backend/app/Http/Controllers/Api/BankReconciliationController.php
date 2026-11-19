<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BankReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankReconciliationController extends Controller
{
    public function __construct(
        protected BankReconciliationService $bankReconciliationService
    ) {}

    /**
     * Display unmatched bank statement lines.
     */
    public function unmatchedLines(
        Request $request,
        int $statementId
    ): JsonResponse {
        $validated = $request->validate([
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $lines = $this->bankReconciliationService
            ->getUnmatchedLines($statementId);

        return response()->json([
            'success' => true,
            'message' => 'Data transaksi yang belum dicocokkan berhasil diambil.',
            'data' => $lines,
        ]);
    }

    /**
     * Display possible internal transaction candidates.
     */
    public function candidates(
        Request $request,
        int $lineId
    ): JsonResponse {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $result = $this->bankReconciliationService
            ->getCandidates($lineId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Kandidat transaksi berhasil diambil.',
            'data' => $result,
        ]);
    }

    /**
     * Match a bank statement line with an internal transaction.
     */
    public function match(
        Request $request,
        int $lineId
    ): JsonResponse {
        $validated = $request->validate([
            'transaction_type' => [
                'required',
                Rule::in(['payment', 'refund']),
            ],
            'transaction_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $line = $this->bankReconciliationService->matchLine(
            $lineId,
            $validated['transaction_type'],
            (int) $validated['transaction_id'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaksi bank berhasil dicocokkan.',
            'data' => $line,
        ]);
    }

    /**
     * Cancel an existing transaction match.
     */
    public function unmatch(
        Request $request,
        int $lineId
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $line = $this->bankReconciliationService->unmatchLine(
            $lineId,
            $validated['reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Pencocokan transaksi berhasil dibatalkan.',
            'data' => $line,
        ]);
    }
}
