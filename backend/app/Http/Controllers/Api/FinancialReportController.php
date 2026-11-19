<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $financialReportService,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan dashboard keuangan berhasil diambil.',
            'data' => $this->financialReportService->dashboard($filters),
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Laporan pembayaran berhasil diambil.',
            'data' => $this->financialReportService->paymentReport($filters),
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Laporan tren penerimaan berhasil diambil.',
            'data' => $this->financialReportService->revenueReport($filters),
        ]);
    }

    public function receivables(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Laporan aging piutang berhasil diambil.',
            'data' => $this->financialReportService->receivableAgingReport($filters),
        ]);
    }

    public function refunds(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Laporan refund berhasil diambil.',
            'data' => $this->financialReportService->refundReport($filters),
        ]);
    }

    public function bankReconciliation(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Laporan rekonsiliasi bank berhasil diambil.',
            'data' => $this->financialReportService->bankReconciliationReport($filters),
        ]);
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:30'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}