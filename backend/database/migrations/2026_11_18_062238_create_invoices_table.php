<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Invoice identification
            $table->string('invoice_number', 50)->unique();

            // Related records
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('quotation_id')
                ->nullable()
                ->constrained('quotations')
                ->restrictOnDelete();

            $table->foreignId('agreement_id')
                ->nullable()
                ->constrained('agreements')
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Invoice information
            $table->string('subject', 200)->nullable();
            $table->text('description')->nullable();

            $table->date('issue_date');
            $table->date('due_date');

            $table->string('currency', 3)->default('IDR');

            // Financial information
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);

            // Invoice and payment statuses
            $table->string('status', 30)->default('draft');
            $table->string('payment_status', 30)->default('unpaid');

            // Timestamps for invoice lifecycle
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Additional information
            $table->text('notes')->nullable();
            $table->text('customer_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('quotation_id');
            $table->index('agreement_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('issue_date');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
