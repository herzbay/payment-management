<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\PaymentTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentTransactionController extends Controller
{
    public function __construct(
        private PaymentTransactionService $paymentTransactionService
    ) {}

    /**
     * Display a listing of payment transactions.
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
            'payment_method' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'payment_date_from' => ['nullable', 'date'],
            'payment_date_to' => [
                'nullable',
                'date',
                'after_or_equal:payment_date_from',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $transactions = $this->paymentTransactionService->getAll($validated);

        return response()->json([
            'success' => true,
            'message' => 'Daftar transaksi pembayaran berhasil diambil.',
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
     * Display the specified payment transaction.
     */
    public function show(int $id): JsonResponse
    {
        $transaction = $this->paymentTransactionService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail transaksi pembayaran berhasil diambil.',
            'data' => $transaction,
        ]);
    }

    /**
     * Store a newly created payment transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => [
                'required',
                'string',
                'max:50',
                Rule::in([
                    'bank_transfer',
                    'cash',
                    'credit_card',
                    'debit_card',
                    'virtual_account',
                    'e_wallet',
                    'other',
                ]),
            ],
            'payment_channel' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'payment_reference' => ['nullable', 'string', 'max:150'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'proof_path' => ['nullable', 'string', 'max:255'],
            'proof_original_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => [
                'required',
                'integer',
                'distinct',
                'exists:invoices,id',
            ],
            'allocations.*.allocated_amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
            'allocations.*.notes' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->findOrFail($validated['customer_id']);

        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer_id' => [
                    'Pelanggan tidak aktif dan tidak dapat digunakan untuk transaksi pembayaran.',
                ],
            ]);
        }

        $transaction = $this->paymentTransactionService->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi pembayaran berhasil dicatat.',
            'data' => $transaction,
        ], 201);
    }

    /**
     * Update the specified payment transaction.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'payment_date' => ['sometimes', 'required', 'date'],
            'payment_method' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::in([
                    'bank_transfer',
                    'cash',
                    'credit_card',
                    'debit_card',
                    'virtual_account',
                    'e_wallet',
                    'other',
                ]),
            ],
            'payment_channel' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'account_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'payment_reference' => ['sometimes', 'nullable', 'string', 'max:150'],
            'payer_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'proof_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'proof_original_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if (empty($validated)) {
            throw ValidationException::withMessages([
                'payment' => [
                    'Minimal satu data transaksi harus diisi untuk pembaruan.',
                ],
            ]);
        }

        $transaction = $this->paymentTransactionService->update(
            $id,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaksi pembayaran berhasil diperbarui.',
            'data' => $transaction,
        ]);
    }
}