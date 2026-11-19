<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService
    ) {}

    /**
     * Display a paginated list of customers.
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
            $this->customerService->getAll($filters)
        );
    }

    /**
     * Display the specified customer.
     */
    public function show(int $id): JsonResponse
    {
        $customer = $this->customerService->getById($id);

        return response()->json([
            'message' => 'Customer retrieved successfully.',
            'data' => $customer,
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->validationRules());

        $customer = $this->customerService->create($data);

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $customer = $this->customerService->getById($id);

        $data = $request->validate(
            $this->validationRules($customer->id, true)
        );

        $updatedCustomer = $this->customerService->update(
            $customer->id,
            $data
        );

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => $updatedCustomer,
        ]);
    }

    /**
     * Activate or deactivate the specified customer.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $customer = $this->customerService->setActive(
            $id,
            (bool) $data['is_active']
        );

        return response()->json([
            'message' => 'Customer status updated successfully.',
            'data' => $customer,
        ]);
    }

    /**
     * Customer validation rules.
     */
    private function validationRules(
        ?int $customerId = null,
        bool $isUpdate = false
    ): array {
        $required = $isUpdate
            ? ['sometimes', 'required']
            : ['required'];

        $nullable = $isUpdate
            ? ['sometimes', 'nullable']
            : ['nullable'];

        return [
            'customer_code' => [
                ...$required,
                'string',
                'max:50',
                Rule::unique('customers', 'customer_code')->ignore($customerId),
            ],
            'company_name' => [
                ...$required,
                'string',
                'max:255',
            ],
            'contact_person' => [
                ...$nullable,
                'string',
                'max:255',
            ],
            'email' => [
                ...$nullable,
                'email',
                'max:255',
            ],
            'phone' => [
                ...$nullable,
                'string',
                'max:50',
            ],
            'tax_identification_number' => [
                ...$nullable,
                'string',
                'max:100',
            ],
            'billing_address' => [
                ...$nullable,
                'string',
            ],
            'city' => [
                ...$nullable,
                'string',
                'max:100',
            ],
            'province' => [
                ...$nullable,
                'string',
                'max:100',
            ],
            'postal_code' => [
                ...$nullable,
                'string',
                'max:20',
            ],
            'country' => [
                ...$nullable,
                'string',
                'max:100',
            ],
            'notes' => [
                ...$nullable,
                'string',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
