<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            // Transaction identification
            $table->string('transaction_number', 50)->unique();

            // Customer information
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            // Payment details
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('payment_method', 50);
            $table->string('payment_channel', 100)->nullable();

            // Bank and payer information
            $table->string('bank_name', 150)->nullable();
            $table->string('account_name', 150)->nullable();
            $table->string('payment_reference', 150)->nullable();
            $table->string('payer_name', 150)->nullable();

            // Payment proof
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();

            // Verification status
            $table->string('status', 30)->default('pending_verification');
            $table->timestamp('verified_at')->nullable();

            // User who recorded the transaction
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('payment_date');
            $table->index('payment_method');
            $table->index('status');
            $table->index('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
