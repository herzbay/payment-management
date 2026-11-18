<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();

            // Receipt identification
            $table->string('receipt_number', 50)->unique();

            // One receipt for each payment transaction
            $table->foreignId('payment_transaction_id')
                ->unique()
                ->constrained('payment_transactions')
                ->restrictOnDelete();

            // Receipt information
            $table->date('receipt_date');
            $table->string('currency', 3)->default('IDR');
            $table->decimal('total_amount', 18, 2);

            // Issuance information
            $table->foreignId('issued_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status', 30)->default('issued');
            $table->string('document_path')->nullable();

            // Void information
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('receipt_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
