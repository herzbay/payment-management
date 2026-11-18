<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();

            // Bank account information
            $table->string('bank_name', 150);
            $table->string('account_name', 150);
            $table->string('account_number', 100);
            $table->string('branch_name', 150)->nullable();

            $table->string('currency', 3)->default('IDR');

            // Account identification
            $table->string('account_code', 50)->unique();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('bank_name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
