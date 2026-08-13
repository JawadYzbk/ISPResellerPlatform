<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_usage_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('metric', 32)->default('total_octets');
            $table->unsignedBigInteger('included_bytes')->default(0);
            $table->unsignedBigInteger('unit_bytes')->default(1000000000);
            $table->unsignedInteger('amount_minor')->default(0);
            $table->char('currency', 3);
            $table->string('rounding', 16)->default('ceil');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'plan_id', 'status', 'effective_from']);
            $table->index(['tenant_id', 'metric', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_usage_rates');
    }
};
