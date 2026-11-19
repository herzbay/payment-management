<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceService $serviceService
    ) {}

    /**
     * Display a paginated list of services.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->serviceService->getAll($filters)
        );
    }

    /**
     * Display the specified service.
     */
    public function show(int $id): JsonResponse
    {
        $service = $this->serviceService->getById($id);

        return response()->json([
            'message' => 'Service retrieved successfully.',
            'data' => $service,
        ]);
    }

    /**
     * Store a newly created service.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->validationRules());

        $service = $this->serviceService->create($data);

        return response()->json([
            'message' => 'Service created successfully.',
            'data' => $service,
        ], 201);
    }

    /**
     * Update the specified service.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = $this->serviceService->getById($id);

        $data = $request->validate(
            $this->validationRules($service->id, true)
        );

        $updatedService = $this->serviceService->update(
            $service->id,
            $data
        );

        return response()->json([
            'message' => 'Service updated successfully.',
            'data' => $updatedService,
        ]);
    }

    /**
     * Activate or deactivate the specified service.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $service = $this->serviceService->setActive(
            $id,
            (bool) $data['is_active']
        );

        return response()->json([
            'message' => 'Service status updated successfully.',
            'data' => $service,
        ]);
    }

    /**
     * Service validation rules.
     */
    private function validationRules(
        ?int $serviceId = null,
        bool $isUpdate = false
    ): array {
        $requiredRules = $isUpdate
            ? ['sometimes', 'required']
            : ['required'];

        $nullableRules = $isUpdate
            ? ['sometimes', 'nullable']
            : ['nullable'];

        return [
            'service_code' => [
                ...$requiredRules,
                'string',
                'max:50',
                Rule::unique('services', 'service_code')->ignore($serviceId),
            ],

            'name' => [
                ...$requiredRules,
                'string',
                'max:255',
            ],

            'category' => [
                ...$nullableRules,
                'string',
                'max:100',
            ],

            'description' => [
                ...$nullableRules,
                'string',
            ],

            'default_unit' => [
                ...$nullableRules,
                'string',
                'max:50',
            ],

            'default_unit_price' => [
                ...$nullableRules,
                'numeric',
                'min:0',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
