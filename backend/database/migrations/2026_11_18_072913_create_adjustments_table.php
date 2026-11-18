<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();

            // Adjustment identification
            $table->string('adjustment_number', 50)->unique();

            // Related invoice and customer
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            // User information
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('applied_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Adjustment details
            $table->string('adjustment_type', 50);
            $table->string('direction', 20);
            $table->string('currency', 3)->default('IDR');

            $table->decimal('amount', 18, 2);

            $table->string('reference_number', 100)->nullable();
            $table->date('adjustment_date');

            $table->string('status', 30)->default('pending_approval');

            // Explanation and approval information
            $table->string('reason', 255);
            $table->text('description')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('customer_id');
            $table->index('adjustment_type');
            $table->index('direction');
            $table->index('status');
            $table->index('adjustment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};