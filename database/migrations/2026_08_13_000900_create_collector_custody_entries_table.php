<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_custody_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cash_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('direction', 8);
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->text('description');
            $table->string('reference', 120)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->index(['tenant_id', 'collector_id', 'status'], 'collector_custody_assignee_idx');
            $table->index(['tenant_id', 'occurred_at'], 'collector_custody_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_custody_entries');
    }
};
