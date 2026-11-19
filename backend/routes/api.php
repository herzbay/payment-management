<?php

use App\Http\Controllers\Api\AccountReceivableController;
use App\Http\Controllers\Api\AdjustmentController;
use App\Http\Controllers\Api\AgreementController;
use App\Http\Controllers\Api\AuditTrailController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BankReconciliationController;
use App\Http\Controllers\Api\BankStatementController;
use App\Http\Controllers\Api\BankStatementCsvImportController;
use App\Http\Controllers\Api\BankStatementFinalizationController;
use App\Http\Controllers\Api\CollectionActivityController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\FinancialReportController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentSettingController;
use App\Http\Controllers\Api\PaymentTransactionController;
use App\Http\Controllers\Api\PaymentVerificationController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ApproverController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// API Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Payment Management API is running.',
        'application' => 'Payment Management System',
    ]);
});

// Authentication
Route::post('/login', [AuthController::class, 'login']);


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);


    /*
    |--------------------------------------------------------------------------
    | READ ACCESS
    |--------------------------------------------------------------------------
    | All authorized roles, including Auditor.
    */

    Route::middleware(
        'role:super-admin,finance-manager,finance-staff,sales-staff,auditor'
    )->group(function () {

        // Customer Management
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);

        // Service Management
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{id}', [ServiceController::class, 'show']);

        // Quotation Management
        Route::get('/quotations', [QuotationController::class, 'index']);
        Route::get('/quotations/{id}', [QuotationController::class, 'show']);

        // Agreement Management
        Route::get('/agreements', [AgreementController::class, 'index']);
        Route::get('/agreements/{id}', [AgreementController::class, 'show']);

        // Invoice Management
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);

        // Payment Transaction Management
        Route::get('/payment-transactions', [
            PaymentTransactionController::class,
            'index',
        ]);

        Route::get('/payment-transactions/{id}', [
            PaymentTransactionController::class,
            'show',
        ]);

        // Payment Verification
        Route::get('/payment-verifications', [
            PaymentVerificationController::class,
            'index',
        ]);

        Route::get('/payment-verifications/{id}', [
            PaymentVerificationController::class,
            'show',
        ]);

        // Receipt Management
        Route::get('/receipts', [ReceiptController::class, 'index']);
        Route::get('/receipts/{id}', [ReceiptController::class, 'show']);

        // Approver Management
        Route::get('/approvers', [ApproverController::class, 'index']);

        /*
        |--------------------------------------------------------------------------
        | Accounts Receivable
        |--------------------------------------------------------------------------
        */

        // Static routes must be declared before /{id}.
        Route::get('/accounts-receivable/summary', [
            AccountReceivableController::class,
            'summary',
        ]);

        Route::get('/accounts-receivable/aging', [
            AccountReceivableController::class,
            'aging',
        ]);

        Route::get('/accounts-receivable', [
            AccountReceivableController::class,
            'index',
        ]);

        Route::get('/accounts-receivable/{id}', [
            AccountReceivableController::class,
            'show',
        ]);

        // Collection Activities
        Route::get('/collection-activities', [
            CollectionActivityController::class,
            'index',
        ]);

        Route::get('/collection-activities/{id}', [
            CollectionActivityController::class,
            'show',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Adjustment Management
        |--------------------------------------------------------------------------
        */

        Route::get('/adjustments', [
            AdjustmentController::class,
            'index',
        ]);

        Route::get('/adjustments/{id}', [
            AdjustmentController::class,
            'show',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Bank Account Management
        |--------------------------------------------------------------------------
        */

        Route::get('/bank-accounts', [
            BankAccountController::class,
            'index',
        ]);

        Route::get('/bank-accounts/{id}', [
            BankAccountController::class,
            'show',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Bank Statement Management
        |--------------------------------------------------------------------------
        */

        Route::get('/bank-statements', [
            BankStatementController::class,
            'index',
        ]);

        Route::get('/bank-statements/{id}', [
            BankStatementController::class,
            'show',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Bank Reconciliation
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/bank-statements/{statementId}/unmatched-lines',
            [BankReconciliationController::class, 'unmatchedLines']
        );

        Route::get(
            '/bank-statement-lines/{lineId}/candidates',
            [BankReconciliationController::class, 'candidates']
        );


        /*
        |--------------------------------------------------------------------------
        | Financial Reports & Analytics
        |--------------------------------------------------------------------------
        */

        Route::prefix('reports')->group(function () {
            Route::get('/dashboard', [
                FinancialReportController::class,
                'dashboard',
            ]);

            Route::get('/payments', [
                FinancialReportController::class,
                'payments',
            ]);

            Route::get('/revenue', [
                FinancialReportController::class,
                'revenue',
            ]);

            Route::get('/receivables', [
                FinancialReportController::class,
                'receivables',
            ]);

            Route::get('/refunds', [
                FinancialReportController::class,
                'refunds',
            ]);

            Route::get('/bank-reconciliation', [
                FinancialReportController::class,
                'bankReconciliation',
            ]);
        });


        /*
        |--------------------------------------------------------------------------
        | Audit Trail
        |--------------------------------------------------------------------------
        */

        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [
                AuditTrailController::class,
                'index',
            ]);

            Route::get('/{id}', [
                AuditTrailController::class,
                'show',
            ]);
        });


        /*
        |--------------------------------------------------------------------------
        | Payment Settings - Read Access
        |--------------------------------------------------------------------------
        */

        Route::get('/payment-settings', [
            PaymentSettingController::class,
            'index',
        ])->middleware('role:super-admin,finance-manager');

        Route::get('/payment-settings/{key}', [
            PaymentSettingController::class,
            'show',
        ])->middleware('role:super-admin,finance-manager');
    });


    /*
    |--------------------------------------------------------------------------
    | OPERATIONAL WRITE ACCESS
    |--------------------------------------------------------------------------
    | Auditor is excluded.
    */

    Route::middleware(
        'role:super-admin,finance-manager,finance-staff,sales-staff'
    )->group(function () {

        // Customer Management
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::put('/customers/{id}', [CustomerController::class, 'update']);

        Route::patch('/customers/{id}/status', [
            CustomerController::class,
            'updateStatus',
        ]);

        // Service Management
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{id}', [ServiceController::class, 'update']);

        Route::patch('/services/{id}/status', [
            ServiceController::class,
            'updateStatus',
        ]);

        // Quotation Management
        Route::post('/quotations', [QuotationController::class, 'store']);
        Route::put('/quotations/{id}', [QuotationController::class, 'update']);

        Route::post('/quotations/{id}/submit', [
            QuotationController::class,
            'submit',
        ]);

        Route::post('/quotations/{id}/approve', [
            QuotationController::class,
            'approve',
        ]);

        Route::post('/quotations/{id}/reject', [
            QuotationController::class,
            'reject',
        ]);

        // Agreement Management
        Route::post('/agreements', [AgreementController::class, 'store']);
        Route::put('/agreements/{id}', [AgreementController::class, 'update']);

        // Invoice Management
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::put('/invoices/{id}', [InvoiceController::class, 'update']);

        // Payment Transaction Management
        Route::post('/payment-transactions', [
            PaymentTransactionController::class,
            'store',
        ]);

        Route::put('/payment-transactions/{id}', [
            PaymentTransactionController::class,
            'update',
        ]);

        // Receipt Management
        Route::post('/receipts', [ReceiptController::class, 'store']);

        // Collection Activities
        Route::post('/collection-activities', [
            CollectionActivityController::class,
            'store',
        ]);

        Route::put('/collection-activities/{id}', [
            CollectionActivityController::class,
            'update',
        ]);

        // Adjustment Management
        Route::post('/adjustments', [
            AdjustmentController::class,
            'store',
        ]);

        Route::post('/adjustments/{id}/apply', [
            AdjustmentController::class,
            'apply',
        ]);

        // Bank Account Management
        Route::post('/bank-accounts', [
            BankAccountController::class,
            'store',
        ]);

        Route::put('/bank-accounts/{id}', [
            BankAccountController::class,
            'update',
        ]);

        Route::patch('/bank-accounts/{id}/status', [
            BankAccountController::class,
            'updateStatus',
        ]);

        Route::patch('/bank-accounts/{id}/default', [
            BankAccountController::class,
            'setDefault',
        ]);

        // Bank Statement Management
        Route::post('/bank-statements', [
            BankStatementController::class,
            'store',
        ]);

        Route::post('/bank-statements/{id}/import', [
            BankStatementController::class,
            'import',
        ]);

        // CSV Bank Statement Import
        Route::post('/bank-statements/{statementId}/import-csv', [
            BankStatementCsvImportController::class,
            'import',
        ]);

        // Bank Transaction Matching
        Route::post('/bank-statement-lines/{lineId}/match', [
            BankReconciliationController::class,
            'match',
        ]);

        Route::post('/bank-statement-lines/{lineId}/unmatch', [
            BankReconciliationController::class,
            'unmatch',
        ]);

        // Bank Statement Finalization
        Route::post('/bank-statements/{statementId}/finalize', [
            BankStatementFinalizationController::class,
            'finalize',
        ]);
    });


    /*
    |--------------------------------------------------------------------------
    | PAYMENT VERIFICATION
    |--------------------------------------------------------------------------
    | Only Super Admin and Finance Manager can approve or reject payments.
    */

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


    /*
    |--------------------------------------------------------------------------
    | ADJUSTMENT APPROVAL
    |--------------------------------------------------------------------------
    | Only Super Admin and Finance Manager can approve, reject, or cancel.
    */

    Route::middleware('role:super-admin,finance-manager')->group(function () {

        Route::post('/adjustments/{id}/approve', [
            AdjustmentController::class,
            'approve',
        ]);

        Route::post('/adjustments/{id}/reject', [
            AdjustmentController::class,
            'reject',
        ]);

        Route::post('/adjustments/{id}/cancel', [
            AdjustmentController::class,
            'cancel',
        ]);
    });


    /*
    |--------------------------------------------------------------------------
    | PAYMENT SETTINGS - WRITE ACCESS
    |--------------------------------------------------------------------------
    | Only Super Admin and Finance Manager.
    */

    Route::middleware('role:super-admin,finance-manager')->group(function () {

        Route::put('/payment-settings/{key}/value', [
            PaymentSettingController::class,
            'updateValue',
        ]);

        Route::patch('/payment-settings/{key}/status', [
            PaymentSettingController::class,
            'updateStatus',
        ]);
    });
});
