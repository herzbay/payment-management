<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentVerificationController extends Controller
{
    public function __construct(
        private PaymentVerificationService $paymentVerificationService
    ) {}

    /**
     * Display payment transactions for verification.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'pending_verification',
                    'verified',
                    'rejected',
                ]),
            ],
            'payment_date_from' => ['nullable', 'date'],
            'payment_date_to' => [
                'nullable',
                'date',
                'after_or_equal:payment_date_from',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $transactions = $this->paymentVerificationService->getAll($validated);

        return response()->json([
            'success' => true,
            'message' => 'Daftar verifikasi pembayaran berhasil diambil.',
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Display payment transaction detail.
     */
    public function show(int $id): JsonResponse
    {
        $transaction = $this->paymentVerificationService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail verifikasi pembayaran berhasil diambil.',
            'data' => $transaction,
        ]);
    }

    /**
     * Approve a payment transaction.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'confirmed_amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
            'confirmed_reference' => [
                'nullable',
                'string',
                'max:150',
            ],
            'notes' => ['nullable', 'string'],
        ]);

        $transaction = $this->paymentVerificationService->approve(
            $id,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil diverifikasi.',
            'data' => $transaction,
        ]);
    }

    /**
     * Reject a payment transaction.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'confirmed_amount' => [
                'nullable',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
            'confirmed_reference' => [
                'nullable',
                'string',
                'max:150',
            ],
            'notes' => ['nullable', 'string'],
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $transaction = $this->paymentVerificationService->reject(
            $id,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil ditolak.',
            'data' => $transaction,
        ]);
    }
}