<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'module' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:100'],
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'auditable_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat aktivitas berhasil diambil.',
            'data' => $this->auditTrailService->getAll($filters),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail riwayat aktivitas berhasil diambil.',
            'data' => $this->auditTrailService->getById($id),
        ]);
    }
}
