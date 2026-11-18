<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\QuotationWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly QuotationWorkflowService $workflowService
    ) {}

    /**
     * Menampilkan daftar quotation.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => [
                'nullable',
                Rule::in([
                    'draft',
                    'pending_approval',
                    'approved',
                    'rejected',
                ]),
            ],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $quotations = $this->quotationService->getAll($filters);

        return response()->json([
            'message' => 'Quotation list retrieved successfully.',
            'data' => $quotations->items(),
            'meta' => [
                'current_page' => $quotations->currentPage(),
                'last_page' => $quotations->lastPage(),
                'per_page' => $quotations->perPage(),
                'total' => $quotations->total(),
            ],
        ]);
    }

    /**
     * Menampilkan detail quotation.
     */
    public function show(int $id): JsonResponse
    {
        $quotation = $this->quotationService->getById($id);

        return response()->json([
            'message' => 'Quotation detail retrieved successfully.',
            'data' => $quotation,
        ]);
    }

    /**
     * Membuat quotation baru.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'issue_date' => ['required', 'date'],
            'valid_until' => [
                'required',
                'date',
                'after_or_equal:issue_date',
            ],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'internal_notes' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => [
                'required',
                'integer',
                'exists:services,id',
            ],
            'items.*.description' => [
                'required',
                'string',
                'max:1000',
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'items.*.unit' => [
                'required',
                'string',
                'max:50',
            ],
            'items.*.unit_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'items.*.discount_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'items.*.tax_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'terms' => ['nullable', 'array'],
            'terms.*.title' => [
                'required',
                'string',
                'max:200',
            ],
            'terms.*.content' => [
                'required',
                'string',
            ],
            'terms.*.is_required' => [
                'nullable',
                'boolean',
            ],
        ]);

        $quotation = $this->quotationService->create($data);

        return response()->json([
            'message' => 'Quotation created successfully.',
            'data' => $quotation,
        ], 201);
    }

    /**
     * Mengubah quotation yang masih draft.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:customers,id',
            ],
            'subject' => [
                'sometimes',
                'required',
                'string',
                'max:200',
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'issue_date' => [
                'sometimes',
                'required',
                'date',
            ],
            'valid_until' => [
                'sometimes',
                'required',
                'date',
            ],
            'currency' => [
                'sometimes',
                'required',
                'string',
                'size:3',
            ],
            'internal_notes' => ['sometimes', 'nullable', 'string'],
            'customer_notes' => ['sometimes', 'nullable', 'string'],

            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.service_id' => [
                'required_with:items',
                'integer',
                'exists:services,id',
            ],
            'items.*.description' => [
                'required_with:items',
                'string',
                'max:1000',
            ],
            'items.*.quantity' => [
                'required_with:items',
                'numeric',
                'gt:0',
            ],
            'items.*.unit' => [
                'required_with:items',
                'string',
                'max:50',
            ],
            'items.*.unit_price' => [
                'required_with:items',
                'numeric',
                'min:0',
            ],
            'items.*.discount_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'items.*.tax_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'terms' => ['sometimes', 'nullable', 'array'],
            'terms.*.title' => [
                'required_with:terms',
                'string',
                'max:200',
            ],
            'terms.*.content' => [
                'required_with:terms',
                'string',
            ],
            'terms.*.is_required' => [
                'nullable',
                'boolean',
            ],
        ]);

        if (
            isset($data['issue_date'], $data['valid_until'])
            && $data['valid_until'] < $data['issue_date']
        ) {
            return response()->json([
                'message' => 'The valid until date must be on or after the issue date.',
            ], 422);
        }

        $quotation = $this->quotationService->update($id, $data);

        return response()->json([
            'message' => 'Quotation updated successfully.',
            'data' => $quotation,
        ]);
    }

    /**
     * Mengajukan quotation untuk persetujuan.
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'approver_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $quotation = $this->workflowService->submitForApproval(
            quotationId: $id,
            approverId: $data['approver_id']
        );

        return response()->json([
            'message' => 'Quotation submitted for approval successfully.',
            'data' => $quotation,
        ]);
    }

    /**
     * Menyetujui quotation.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $quotation = $this->workflowService->approve(
            quotationId: $id,
            comments: $data['comments'] ?? null
        );

        return response()->json([
            'message' => 'Quotation approved successfully.',
            'data' => $quotation,
        ]);
    }

    /**
     * Menolak quotation.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'comments' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $quotation = $this->workflowService->reject(
            quotationId: $id,
            comments: $data['comments']
        );

        return response()->json([
            'message' => 'Quotation rejected successfully.',
            'data' => $quotation,
        ]);
    }
}