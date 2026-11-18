<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();

            $table->string('agreement_number', 50)->unique();

            $table->foreignId('quotation_id')
                ->unique()
                ->constrained('quotations')
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title', 200);

            $table->date('issue_date');
            $table->date('effective_date')->nullable();
            $table->date('end_date')->nullable();

            $table->string('status', 30)->default('draft');

            $table->string('customer_signatory', 150)->nullable();
            $table->string('company_signatory', 150)->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('customer_signed_at')->nullable();
            $table->timestamp('company_signed_at')->nullable();

            $table->string('document_path')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('effective_date');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};