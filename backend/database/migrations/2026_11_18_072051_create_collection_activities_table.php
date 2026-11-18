<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_activities', function (Blueprint $table) {
            $table->id();

            // Related invoice and customer
            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            // Responsible users
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Collection activity information
            $table->string('activity_type', 50);
            $table->timestamp('activity_date');
            $table->string('contact_method', 50)->nullable();

            $table->string('outcome', 100)->nullable();
            $table->timestamp('next_follow_up_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('customer_id');
            $table->index('assigned_to');
            $table->index('activity_type');
            $table->index('activity_date');
            $table->index('next_follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_activities');
    }
};