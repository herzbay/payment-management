<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    public function __construct(
        private BankAccountService $bankAccountService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'size:3'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->bankAccountService->getAll($filters)
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            $this->bankAccountService->getById($id)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:150'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:100'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'currency' => ['nullable', 'string', 'size:3'],
            'account_code' => [
                'required',
                'string',
                'max:50',
                'unique:bank_accounts,account_code',
            ],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $account = $this->bankAccountService->create($data);

        return response()->json([
            'message' => 'Rekening bank berhasil ditambahkan.',
            'data' => $account,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'bank_name' => ['sometimes', 'required', 'string', 'max:150'],
            'account_name' => ['sometimes', 'required', 'string', 'max:150'],
            'account_number' => ['sometimes', 'required', 'string', 'max:100'],
            'branch_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'account_code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('bank_accounts', 'account_code')->ignore($id),
            ],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $account = $this->bankAccountService->update($id, $data);

        return response()->json([
            'message' => 'Rekening bank berhasil diperbarui.',
            'data' => $account,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $account = $this->bankAccountService->updateStatus(
            $id,
            $data['is_active']
        );

        return response()->json([
            'message' => 'Status rekening bank berhasil diperbarui.',
            'data' => $account,
        ]);
    }

    public function setDefault(int $id): JsonResponse
    {
        $account = $this->bankAccountService->setDefault($id);

        return response()->json([
            'message' => 'Rekening bank default berhasil diperbarui.',
            'data' => $account,
        ]);
    }
}
