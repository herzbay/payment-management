<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BankStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankStatementController extends Controller
{
    public function __construct(
        protected BankStatementService $bankStatementService
    ) {}

    /**
     * Display a paginated list of bank statements.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'status' => [
                'nullable',
                Rule::in(['draft', 'imported', 'reconciling', 'reconciled']),
            ],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $statements = $this->bankStatementService->getAll($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data bank statement berhasil diambil.',
            'data' => $statements,
        ]);
    }

    /**
     * Display the specified bank statement.
     */
    public function show(int $id): JsonResponse
    {
        $statement = $this->bankStatementService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail bank statement berhasil diambil.',
            'data' => $statement,
        ]);
    }

    /**
     * Store a newly created bank statement.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'statement_number' => [
                'required',
                'string',
                'max:50',
                'unique:bank_statements,statement_number',
            ],
            'bank_account_id' => [
                'required',
                'integer',
                'exists:bank_accounts,id',
            ],
            'period_start' => ['required', 'date'],
            'period_end' => [
                'required',
                'date',
                'after_or_equal:period_start',
            ],
            'currency' => ['nullable', 'string', 'size:3'],
            'opening_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'closing_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'file_name' => ['nullable', 'string', 'max:255'],
            'file_path' => ['nullable', 'string', 'max:255'],
            'file_format' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        $statement = $this->bankStatementService->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bank statement berhasil dibuat.',
            'data' => $statement,
        ], 201);
    }

    /**
     * Import transaction rows into a bank statement.
     */
    public function import(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rows' => [
                'required',
                'array',
                'min:1',
                'max:10000',
            ],

            'rows.*.transaction_date' => [
                'required',
                'date',
            ],

            'rows.*.description' => [
                'required',
                'string',
                'max:255',
            ],

            'rows.*.transaction_type' => [
                'required',
                'string',
                Rule::in([
                    'credit',
                    'debit',
                    'cr',
                    'dr',
                    'c',
                    'd',
                    'kredit',
                    'in',
                    'out',
                    'masuk',
                    'keluar',
                ]),
            ],

            'rows.*.amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'rows.*.running_balance' => [
                'nullable',
                'numeric',
            ],

            'rows.*.reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'file_metadata' => [
                'nullable',
                'array',
            ],

            'file_metadata.file_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'file_metadata.file_path' => [
                'nullable',
                'string',
                'max:255',
            ],

            'file_metadata.file_format' => [
                'nullable',
                'string',
                'max:20',
            ],
        ]);

        $statement = $this->bankStatementService->importLines(
            $id,
            $validated['rows'],
            $validated['file_metadata'] ?? []
        );

        return response()->json([
            'success' => true,
            'message' => count($validated['rows'])
                . ' baris transaksi berhasil diimpor.',
            'data' => $statement,
        ]);
    }
}
