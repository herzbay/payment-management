<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();

            // Setting identification
            $table->string('setting_key', 150)->unique();
            $table->string('setting_group', 100);

            // Setting value
            $table->json('setting_value')->nullable();
            $table->string('value_type', 30)->default('string');

            // Setting information
            $table->string('label', 150);
            $table->text('description')->nullable();

            // Access and visibility
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);

            // Last user who updated the setting
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index('setting_group');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
