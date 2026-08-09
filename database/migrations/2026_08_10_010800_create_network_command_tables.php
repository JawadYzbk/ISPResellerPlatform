<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('desired_state_version');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'service_id', 'status', 'id']);
            $table->unique(['service_id', 'desired_state_version', 'action']);
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->string('aggregate_type', 128);
            $table->string('aggregate_id', 128);
            $table->json('payload');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'published_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('network_commands');
    }
};
