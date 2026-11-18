<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // User who performed the activity
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Activity information
            $table->string('event', 50);
            $table->string('module', 100);
            $table->string('description', 255);

            // Related record (polymorphic reference)
            $table->nullableMorphs('auditable');

            // Before and after values
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request information
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('http_method', 10)->nullable();

            // Additional context
            $table->json('properties')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('event');
            $table->index('module');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
