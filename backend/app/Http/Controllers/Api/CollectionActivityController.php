<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CollectionActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectionActivityController extends Controller
{
    public function __construct(
        private CollectionActivityService $collectionActivityService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'activity_type' => ['nullable', 'string', 'max:50'],
            'activity_date_from' => ['nullable', 'date'],
            'activity_date_to' => [
                'nullable',
                'date',
                'after_or_equal:activity_date_from',
            ],
            'has_follow_up' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $activities = $this->collectionActivityService->getAll($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data collection activities berhasil diambil.',
            'data' => $activities,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $activity = $this->collectionActivityService->getById($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail collection activity berhasil diambil.',
            'data' => $activity,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => [
                'required',
                'integer',
                'exists:invoices,id',
            ],
            'assigned_to' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'activity_type' => [
                'required',
                'string',
                'max:50',
            ],
            'activity_date' => ['nullable', 'date'],
            'contact_method' => [
                'nullable',
                'string',
                'max:50',
            ],
            'outcome' => [
                'nullable',
                'string',
                'max:100',
            ],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $activity = $this->collectionActivityService->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Collection activity berhasil dibuat.',
            'data' => $activity,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'activity_type' => [
                'sometimes',
                'required',
                'string',
                'max:50',
            ],
            'activity_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'contact_method' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'outcome' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'next_follow_up_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        $activity = $this->collectionActivityService->update(
            $id,
            $validated,
        );

        return response()->json([
            'success' => true,
            'message' => 'Collection activity berhasil diperbarui.',
            'data' => $activity,
        ]);
    }
}
