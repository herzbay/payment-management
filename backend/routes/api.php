<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\AgreementController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentTransactionController;
use App\Http\Controllers\Api\PaymentVerificationController;
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

        // Quotation endpoints.
        Route::get('/quotations', [QuotationController::class, 'index']);
        Route::get('/quotations/{id}', [QuotationController::class, 'show']);

        // Agreement endpoints.
        Route::get('/agreements', [AgreementController::class, 'index']);
        Route::get('/agreements/{id}', [AgreementController::class, 'show']);

        // Invoice endpoints.
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);

        // Payment Transaction endpoints.
        Route::get('/payment-transactions', [PaymentTransactionController::class, 'index']);
        Route::get('/payment-transactions/{id}', [PaymentTransactionController::class, 'show']);

        // Payment Verification endpoints.
        Route::get('/payment-verifications', [PaymentVerificationController::class, 'index']);
        Route::get('/payment-verifications/{id}', [PaymentVerificationController::class, 'show']);
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

        // Quotation endpoints.
        Route::post('/quotations', [QuotationController::class, 'store']);
        Route::put('/quotations/{id}', [QuotationController::class, 'update']);
        Route::post('/quotations/{id}/submit', [QuotationController::class, 'submit']);
        Route::post('/quotations/{id}/approve', [QuotationController::class, 'approve']);
        Route::post('/quotations/{id}/reject', [QuotationController::class, 'reject']);

        // Agreement endpoints.
        Route::post('/agreements', [AgreementController::class, 'store']);
        Route::put('/agreements/{id}', [AgreementController::class, 'update']);

        // Invoice endpoints.
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::put('/invoices/{id}', [InvoiceController::class, 'update']);

        // Payment Transaction endpoints.
        Route::post('/payment-transactions', [PaymentTransactionController::class, 'store']);
        Route::put('/payment-transactions/{id}', [PaymentTransactionController::class, 'update']);

        // Payment Verification endpoints.
        Route::middleware('role:super-admin,finance-manager')->group(function () {
            Route::post('/payment-verifications/{id}/approve', [
                PaymentVerificationController::class,
                'approve',
            ]);

            Route::post('/payment-verifications/{id}/reject', [
                PaymentVerificationController::class,
                'reject',
            ]);
        });
    });
});
