<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            // Refund identification
            $table->string('refund_number', 50)->unique();

            // Original payment transaction
            $table->foreignId('payment_transaction_id')
                ->constrained('payment_transactions')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            // User information
            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Refund details
            $table->date('refund_date')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->decimal('refund_amount', 18, 2);

            $table->string('refund_method', 50)->nullable();
            $table->string('destination_account', 150)->nullable();
            $table->string('refund_reference', 150)->nullable();

            // Refund workflow
            $table->string('status', 30)->default('pending_approval');

            $table->text('reason');
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            // Processing information
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->string('proof_path')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('payment_transaction_id');
            $table->index('customer_id');
            $table->index('requested_by');
            $table->index('status');
            $table->index('refund_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
