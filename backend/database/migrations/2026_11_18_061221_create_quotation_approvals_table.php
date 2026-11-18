<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quotation_id')
                ->constrained('quotations')
                ->restrictOnDelete();

            $table->foreignId('approver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedInteger('approval_level')->default(1);

            $table->string('status', 30)->default('pending');

            $table->text('comments')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['quotation_id', 'approval_level'],
                'quotation_approval_level_unique'
            );

            $table->index(['approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_approvals');
    }
};
