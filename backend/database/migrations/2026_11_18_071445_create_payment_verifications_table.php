<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_verifications', function (Blueprint $table) {
            $table->id();

            // Related payment transaction
            $table->foreignId('payment_transaction_id')
                ->constrained('payment_transactions')
                ->restrictOnDelete();

            // Verifier information
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Verification decision
            $table->string('status', 30);

            // Verification details
            $table->decimal('confirmed_amount', 18, 2)->nullable();
            $table->string('confirmed_reference', 150)->nullable();

            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('payment_transaction_id');
            $table->index('verified_by');
            $table->index('status');
            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_verifications');
    }
};