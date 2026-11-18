<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustment_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('adjustment_id')
                ->constrained('adjustments')
                ->restrictOnDelete();

            $table->foreignId('approver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Approval level and decision
            $table->unsignedInteger('approval_level')->default(1);
            $table->string('status', 30)->default('pending');

            $table->text('comments')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            // One approval record per level for each adjustment
            $table->unique(
                ['adjustment_id', 'approval_level'],
                'adjustment_approval_level_unique'
            );

            $table->index(['approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustment_approvals');
    }
};