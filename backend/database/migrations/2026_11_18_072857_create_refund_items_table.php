<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('refund_id')
                ->constrained('refunds')
                ->restrictOnDelete();

            $table->foreignId('payment_allocation_id')
                ->constrained('payment_allocations')
                ->restrictOnDelete();

            // Refund line details
            $table->string('description', 255)->nullable();
            $table->decimal('refund_amount', 18, 2);

            $table->timestamps();

            // One allocation can appear once in each refund
            $table->unique(
                ['refund_id', 'payment_allocation_id'],
                'refund_item_allocation_unique'
            );

            $table->index('payment_allocation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
    }
};