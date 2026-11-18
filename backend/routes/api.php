<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Payment Management API is running.',
        'application' => 'Payment Management System',
    ]);
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Read access: all authorized roles, including Auditor.
    Route::middleware(
        'role:super-admin,finance-manager,finance-staff,sales-staff,auditor'
    )->group(function () {
        // Customer endpoints.
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);

        // Service endpoints.
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{id}', [ServiceController::class, 'show']);
    });

    // Write access: Auditor is intentionally excluded.
    Route::middleware(
        'role:super-admin,finance-manager,finance-staff,sales-staff'
    )->group(function () {
        // Customer endpoints.
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::put('/customers/{id}', [CustomerController::class, 'update']);
        Route::patch('/customers/{id}/status', [
            CustomerController::class,
            'updateStatus',
        ]);

        // Service endpoints.
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{id}', [ServiceController::class, 'update']);
        Route::patch('/services/{id}/status', [
            ServiceController::class,
            'updateStatus',
        ]);
    });
});
