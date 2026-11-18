<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Payment Management API is running.',
        'application' => 'Payment Management System',
    ]);
});

// Public authentication routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Temporary RBAC test endpoints
    Route::prefix('rbac-test')->group(function () {
        Route::get('/super-admin', function () {
            return response()->json([
                'message' => 'Super Admin access granted.',
            ]);
        })->middleware('role:super-admin');

        Route::get('/finance', function () {
            return response()->json([
                'message' => 'Finance access granted.',
            ]);
        })->middleware('role:super-admin,finance-manager,finance-staff');

        Route::get('/sales', function () {
            return response()->json([
                'message' => 'Sales access granted.',
            ]);
        })->middleware('role:super-admin,sales-staff');

        Route::get('/audit', function () {
            return response()->json([
                'message' => 'Audit access granted.',
            ]);
        })->middleware('role:super-admin,auditor');
    });
});