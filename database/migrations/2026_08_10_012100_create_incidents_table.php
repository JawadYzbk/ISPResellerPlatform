<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 64);
            $table->string('severity', 16)->default('warning');
            $table->string('status', 16)->default('open');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'severity']);
            $table->index(['tenant_id', 'router_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
