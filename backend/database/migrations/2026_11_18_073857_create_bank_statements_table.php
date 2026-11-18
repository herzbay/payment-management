<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();

            // Statement identification
            $table->string('statement_number', 50)->unique();

            // Related bank account
            $table->foreignId('bank_account_id')
                ->constrained('bank_accounts')
                ->restrictOnDelete();

            // Statement period
            $table->date('period_start');
            $table->date('period_end');

            // Balance information
            $table->string('currency', 3)->default('IDR');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('closing_balance', 18, 2)->default(0);

            // Import information
            $table->string('file_name', 255)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_format', 20)->nullable();

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('matched_rows')->default(0);
            $table->unsignedInteger('unmatched_rows')->default(0);

            // Import and reconciliation status
            $table->string('status', 30)->default('draft');

            $table->foreignId('imported_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('imported_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('bank_account_id');
            $table->index(['period_start', 'period_end']);
            $table->index('status');
            $table->index('imported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
