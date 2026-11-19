<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts_receivable', function (Blueprint $table) {
            $table->id();

            // Related invoice and customer
            $table->foreignId('invoice_id')
                ->unique()
                ->constrained('invoices')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            // Receivable amounts
            $table->string('currency', 3)->default('IDR');
            $table->decimal('invoice_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2);

            // Due date and receivable status
            $table->date('due_date');
            $table->string('status', 30)->default('open');

            // Collection tracking
            $table->date('last_payment_date')->nullable();
            $table->timestamp('last_reminder_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('due_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_receivable');
    }
};
