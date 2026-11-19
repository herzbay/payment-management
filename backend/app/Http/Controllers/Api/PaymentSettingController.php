<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    public function __construct(
        private readonly PaymentSettingService $paymentSettingService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'setting_group' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengaturan pembayaran berhasil diambil.',
            'data' => $this->paymentSettingService->getAll($filters),
        ]);
    }

    public function show(string $key): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail pengaturan pembayaran berhasil diambil.',
            'data' => $this->paymentSettingService->getByKey($key),
        ]);
    }

    public function updateValue(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'setting_value' => ['present', 'nullable'],
        ]);

        $setting = $this->paymentSettingService->updateValue(
            $key,
            $validated['setting_value']
        );

        return response()->json([
            'success' => true,
            'message' => 'Nilai pengaturan pembayaran berhasil diperbarui.',
            'data' => $setting,
        ]);
    }

    public function updateStatus(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $setting = $this->paymentSettingService->updateStatus(
            $key,
            (bool) $validated['is_active']
        );

        return response()->json([
            'success' => true,
            'message' => 'Status pengaturan pembayaran berhasil diperbarui.',
            'data' => $setting,
        ]);
    }
}
