<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric', 64);
            $table->string('status', 16)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->index(['tenant_id', 'router_id', 'observed_at']);
            $table->index(['tenant_id', 'metric', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_metrics');
    }
};
