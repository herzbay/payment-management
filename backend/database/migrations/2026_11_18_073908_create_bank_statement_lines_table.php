<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();

            // Related statement
            $table->foreignId('bank_statement_id')
                ->constrained('bank_statements')
                ->restrictOnDelete();

            // Statement line identification
            $table->unsignedInteger('line_number');
            $table->date('transaction_date');
            $table->string('description', 255);

            // Transaction information
            $table->string('transaction_type', 20);
            $table->decimal('amount', 18, 2);
            $table->decimal('running_balance', 18, 2)->nullable();

            $table->string('reference_number', 150)->nullable();

            // Matching with internal records
            $table->foreignId('payment_transaction_id')
                ->nullable()
                ->constrained('payment_transactions')
                ->restrictOnDelete();

            $table->foreignId('refund_id')
                ->nullable()
                ->constrained('refunds')
                ->restrictOnDelete();

            // Reconciliation status
            $table->string('match_status', 30)->default('unmatched');

            $table->foreignId('matched_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('matched_at')->nullable();

            $table->text('match_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(
                ['bank_statement_id', 'line_number'],
                'bank_statement_line_unique'
            );

            $table->unique(
                'payment_transaction_id',
                'bank_line_payment_transaction_unique'
            );

            $table->unique(
                'refund_id',
                'bank_line_refund_unique'
            );

            $table->index('transaction_date');
            $table->index('transaction_type');
            $table->index('reference_number');
            $table->index('match_status');
            $table->index('bank_statement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
