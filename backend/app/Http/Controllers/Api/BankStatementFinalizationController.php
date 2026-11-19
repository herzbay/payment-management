<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BankStatementFinalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankStatementFinalizationController extends Controller
{
    public function __construct(
        private readonly BankStatementFinalizationService $finalizationService,
    ) {
    }

    public function finalize(
        Request $request,
        int $statementId
    ): JsonResponse {
        $result = $this->finalizationService->finalize($statementId);

        return response()->json([
            'success' => true,
            'message' => 'Bank statement berhasil difinalisasi. Rekonsiliasi dan saldo telah tervalidasi.',
            'data' => $result,
        ]);
    }
}