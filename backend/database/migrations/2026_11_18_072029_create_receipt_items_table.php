<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('receipt_id')
                ->constrained('receipts')
                ->restrictOnDelete();

            $table->foreignId('payment_allocation_id')
                ->unique()
                ->constrained('payment_allocations')
                ->restrictOnDelete();

            // Receipt line information
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 18, 2);

            $table->timestamps();

            // Indexes
            $table->index('receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
    }
};