<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReceiptController extends Controller
{
    public function __construct(
        protected ReceiptService $receiptService,
    ) {
    }

    /**
     * Display a paginated list of receipts.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => [
                'nullable',
                'string',
                Rule::in(['issued', 'voided']),
            ],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'receipt_date_from' => ['nullable', 'date'],
            'receipt_date_to' => [
                'nullable',
                'date',
                'after_or_equal:receipt_date_from',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $receipts = $this->receiptService->getAll($filters);

        return response()->json([
            'success' => true,
            'message' => 'Daftar receipt berhasil diambil.',
            'data' => $receipts,
        ]);
    }

    /**
     * Display the specified receipt.
     */
    public function show(int $id): JsonResponse
    {
        $receipt = $this->receiptService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail receipt berhasil diambil.',
            'data' => $receipt,
        ]);
    }

    /**
     * Issue a receipt from a verified payment transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_transaction_id' => [
                'required',
                'integer',
                'exists:payment_transactions,id',
            ],
            'receipt_date' => ['nullable', 'date'],
            'document_path' => ['nullable', 'string', 'max:2048'],
            'notes' => ['nullable', 'string'],
        ]);

        $receipt = $this->receiptService->createFromPaymentTransaction(
            $validated['payment_transaction_id'],
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Receipt berhasil diterbitkan.',
            'data' => $receipt,
        ], 201);
    }
}
