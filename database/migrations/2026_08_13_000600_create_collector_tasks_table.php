<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('assigned');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->index(['tenant_id', 'collector_id', 'status'], 'collector_task_assignee_idx');
            $table->index(['tenant_id', 'due_at'], 'collector_task_due_idx');
        });

        Schema::create('collector_task_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('collector_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->index(['tenant_id', 'collector_task_id', 'created_at'], 'collector_task_message_idx');
        });

        Schema::create('collector_task_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at');
            $table->timestamps();

            $table->unique(['collector_task_id', 'user_id']);
            $table->index(['tenant_id', 'user_id', 'last_read_at'], 'collector_task_read_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_task_reads');
        Schema::dropIfExists('collector_task_messages');
        Schema::dropIfExists('collector_tasks');
    }
};
