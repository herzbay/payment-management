<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BankStatementCsvImportService;
use App\Services\BankStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankStatementCsvImportController extends Controller
{
    public function __construct(
        protected BankStatementCsvImportService $csvImportService,
        protected BankStatementService $bankStatementService
    ) {
    }

    /**
     * Import bank statement from a CSV file.
     */
    public function import(
        Request $request,
        int $statementId
    ): JsonResponse {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240',
            ],
        ]);

        // Parse and validate CSV contents.
        $parsed = $this->csvImportService->parse(
            $validated['file']
        );

        // Save parsed transactions into the bank statement.
        $statement = $this->bankStatementService->importLines(
            $statementId,
            $parsed['rows'],
            $parsed['metadata']
        );

        return response()->json([
            'success' => true,
            'message' => 'Bank statement CSV imported successfully.',
            'data' => $statement,
        ], 201);
    }
}
