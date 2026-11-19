<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BankStatementFinalizationService;
use Illuminate\Http\JsonResponse;

class BankStatementFinalizationController extends Controller
{
    public function __construct(
        protected BankStatementFinalizationService $finalizationService
    ) {}

    /**
     * Finalize bank statement reconciliation.
     */
    public function finalize(int $statementId): JsonResponse
    {
        $statement = $this->finalizationService->finalize(
            $statementId
        );

        return response()->json([
            'message' => 'Bank statement berhasil direkonsiliasi.',
            'data' => $statement,
        ]);
    }
}