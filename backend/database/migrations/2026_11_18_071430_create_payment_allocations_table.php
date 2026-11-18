<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();

            // Related payment and invoice
            $table->foreignId('payment_transaction_id')
                ->constrained('payment_transactions')
                ->restrictOnDelete();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->restrictOnDelete();

            // Allocated amount
            $table->decimal('allocated_amount', 18, 2);

            $table->text('notes')->nullable();

            $table->timestamps();

            // One allocation per invoice in a transaction
            $table->unique(
                ['payment_transaction_id', 'invoice_id'],
                'payment_allocation_unique'
            );

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};