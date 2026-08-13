<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'service_id', 'status']);
            $table->index(['tenant_id', 'addon_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_addons');
    }
};
