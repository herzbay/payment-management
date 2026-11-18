<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->restrictOnDelete();

            $table->foreignId('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            // Item information
            $table->unsignedInteger('line_number');
            $table->string('description', 255);

            // Pricing information
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 50)->default('Lot');
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);

            $table->timestamps();

            // Indexes
            $table->unique(
                ['invoice_id', 'line_number'],
                'invoice_item_line_unique'
            );

            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
