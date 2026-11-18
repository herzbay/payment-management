<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Quotation;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

    /**
     * Display a paginated list of invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                'string',
                'in:draft,sent,overdue,paid,partially_paid,cancelled',
            ],
            'payment_status' => [
                'nullable',
                'string',
                'in:unpaid,partially_paid,paid,overpaid',
            ],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],
            'quotation_id' => [
                'nullable',
                'integer',
                'exists:quotations,id',
            ],
            'issue_date_from' => ['nullable', 'date'],
            'issue_date_to' => [
                'nullable',
                'date',
                'after_or_equal:issue_date_from',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $invoices = $this->invoiceService->getAll($validated);

        return response()->json([
            'success' => true,
            'message' => 'Daftar invoice berhasil diambil.',
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Display invoice details.
     */
    public function show(int $id): JsonResponse
    {
        $invoice = $this->invoiceService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail invoice berhasil diambil.',
            'data' => $invoice,
        ]);
    }

    /**
     * Create an invoice from an approved quotation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quotation_id' => [
                'required',
                'integer',
                'exists:quotations,id',
            ],
            'agreement_id' => [
                'nullable',
                'integer',
                'exists:agreements,id',
            ],
            'subject' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'issue_date' => ['required', 'date'],
            'due_date' => [
                'required',
                'date',
                'after_or_equal:issue_date',
            ],
            'notes' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
        ]);

        $invoice = $this->invoiceService->createFromQuotation($validated);

        return response()->json([
            'success' => true,
            'message' => 'Invoice berhasil dibuat.',
            'data' => $invoice,
        ], 201);
    }

    /**
     * Update an existing draft invoice.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['sometimes', 'nullable', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'issue_date' => ['sometimes', 'required', 'date'],
            'due_date' => [
                'sometimes',
                'required',
                'date',
                'after_or_equal:issue_date',
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
            'customer_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if (empty($validated)) {
            throw ValidationException::withMessages([
                'invoice' => [
                    'Minimal satu data harus diisi untuk memperbarui invoice.',
                ],
            ]);
        }

        $invoice = $this->invoiceService->update($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Invoice berhasil diperbarui.',
            'data' => $invoice,
        ]);
    }
}