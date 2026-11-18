<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    public function __construct(
        private readonly AgreementService $agreementService
    ) {}

    /**
     * Menampilkan daftar agreement.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => [
                'nullable',
                'string',
                'max:30',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $agreements = $this->agreementService->getAll($filters);

        return response()->json([
            'message' => 'Agreement list retrieved successfully.',
            'data' => $agreements->items(),
            'meta' => [
                'current_page' => $agreements->currentPage(),
                'last_page' => $agreements->lastPage(),
                'per_page' => $agreements->perPage(),
                'total' => $agreements->total(),
            ],
        ]);
    }

    /**
     * Menampilkan detail agreement.
     */
    public function show(int $id): JsonResponse
    {
        $agreement = $this->agreementService->getById($id);

        return response()->json([
            'message' => 'Agreement detail retrieved successfully.',
            'data' => $agreement,
        ]);
    }

    /**
     * Membuat agreement baru.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quotation_id' => [
                'required',
                'integer',
                'exists:quotations,id',
            ],
            'title' => ['required', 'string', 'max:200'],
            'issue_date' => ['required', 'date'],
            'effective_date' => [
                'nullable',
                'date',
                'after_or_equal:issue_date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:effective_date',
            ],
            'customer_signatory' => [
                'nullable',
                'string',
                'max:200',
            ],
            'company_signatory' => [
                'nullable',
                'string',
                'max:200',
            ],
            'document_path' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => ['nullable', 'string'],
        ]);

        $agreement = $this->agreementService->create($data);

        return response()->json([
            'message' => 'Agreement created successfully.',
            'data' => $agreement,
        ], 201);
    }

    /**
     * Mengubah agreement draft.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:200',
            ],
            'issue_date' => [
                'sometimes',
                'required',
                'date',
            ],
            'effective_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'end_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'customer_signatory' => [
                'sometimes',
                'nullable',
                'string',
                'max:200',
            ],
            'company_signatory' => [
                'sometimes',
                'nullable',
                'string',
                'max:200',
            ],
            'document_path' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        $effectiveDate = $data['effective_date']
            ?? null;

        $issueDate = $data['issue_date']
            ?? null;

        $endDate = $data['end_date']
            ?? null;

        if (
            $issueDate !== null
            && $effectiveDate !== null
            && $effectiveDate < $issueDate
        ) {
            return response()->json([
                'message' => 'The effective date must be on or after the issue date.',
            ], 422);
        }

        if (
            $endDate !== null
            && $effectiveDate !== null
            && $endDate < $effectiveDate
        ) {
            return response()->json([
                'message' => 'The end date must be on or after the effective date.',
            ], 422);
        }

        $agreement = $this->agreementService->update($id, $data);

        return response()->json([
            'message' => 'Agreement updated successfully.',
            'data' => $agreement,
        ]);
    }
}